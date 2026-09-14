<?php

namespace App\Services;

use App\Exceptions\NexusGithubException;
use App\Models\Proyecto;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class NexusGithubService
{
    private const API = 'https://api.github.com';

    public function repository(?Proyecto $project = null, ?string $owner = null, ?string $repo = null): array
    {
        [$owner, $repo] = $this->repositoryParts($project, $owner, $repo);
        return $this->request('get', "/repos/{$owner}/{$repo}", [], [], false)->json();
    }

    public function branches(?Proyecto $project = null, ?string $owner = null, ?string $repo = null): array
    {
        [$owner, $repo] = $this->repositoryParts($project, $owner, $repo);
        return $this->request('get', "/repos/{$owner}/{$repo}/branches", ['per_page' => 100], [], false)->json();
    }

    public function commits(?Proyecto $project = null, ?string $owner = null, ?string $repo = null, ?string $branch = null): array
    {
        [$owner, $repo] = $this->repositoryParts($project, $owner, $repo);
        return $this->request('get', "/repos/{$owner}/{$repo}/commits", array_filter([
            'sha' => $branch,
            'per_page' => 30,
        ]), [], false)->json();
    }

    public function files(?Proyecto $project = null, ?string $owner = null, ?string $repo = null, ?string $branch = null): array
    {
        [$owner, $repo] = $this->repositoryParts($project, $owner, $repo);
        $repository = $this->repository($project, $owner, $repo);
        $branch ??= $repository['default_branch'] ?? 'main';
        $tree = $this->request('get', "/repos/{$owner}/{$repo}/git/trees/".rawurlencode($branch), [
            'recursive' => '1',
        ], [], false)->json();

        return [
            'repository' => "{$owner}/{$repo}",
            'branch' => $branch,
            'truncated' => (bool) ($tree['truncated'] ?? false),
            'files' => collect($tree['tree'] ?? [])
                ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'blob')
                ->map(fn (array $item): array => [
                    'path' => $item['path'] ?? '',
                    'sha' => $item['sha'] ?? null,
                    'size' => $item['size'] ?? null,
                    'url' => $item['url'] ?? null,
                ])->values()->all(),
        ];
    }

    public function file(
        ?Proyecto $project,
        string $path,
        ?string $owner = null,
        ?string $repo = null,
        ?string $branch = null,
    ): array {
        [$owner, $repo] = $this->repositoryParts($project, $owner, $repo);
        $response = $this->request('get', "/repos/{$owner}/{$repo}/contents/".ltrim($path, '/'), array_filter([
            'ref' => $branch,
        ]), [], false);
        $data = $response->json();
        if (isset($data['content'])) {
            $data['decoded_content'] = base64_decode(str_replace(["\r", "\n"], '', (string) $data['content']), true);
        }

        return $data;
    }

    public function issues(?Proyecto $project = null, ?string $owner = null, ?string $repo = null): array
    {
        [$owner, $repo] = $this->repositoryParts($project, $owner, $repo);
        return $this->request('get', "/repos/{$owner}/{$repo}/issues", [
            'state' => 'open',
            'per_page' => 100,
        ], [], false)->json();
    }

    public function pullRequests(?Proyecto $project = null, ?string $owner = null, ?string $repo = null): array
    {
        [$owner, $repo] = $this->repositoryParts($project, $owner, $repo);
        return $this->request('get', "/repos/{$owner}/{$repo}/pulls", [
            'state' => 'open',
            'per_page' => 100,
        ], [], false)->json();
    }

    public function releases(?Proyecto $project = null, ?string $owner = null, ?string $repo = null): array
    {
        [$owner, $repo] = $this->repositoryParts($project, $owner, $repo);
        return $this->request('get', "/repos/{$owner}/{$repo}/releases", [
            'per_page' => 30,
        ], [], false)->json();
    }

    public function changes(?Proyecto $project = null, ?string $owner = null, ?string $repo = null, ?string $branch = null): array
    {
        $commits = $this->commits($project, $owner, $repo, $branch);
        return [
            'latest_commit' => $commits[0] ?? null,
            'commits' => $commits,
            'count' => count($commits),
        ];
    }

    public function createOrUpdateFile(
        Proyecto $project,
        string $path,
        string $content,
        string $message,
        ?string $branch = null,
        ?string $expectedSha = null,
    ): array {
        [$owner, $repo] = $this->repositoryParts($project, null, null);
        $integration = $project->integracionGithub;
        $branch ??= $integration?->rama_principal ?: 'main';
        $existing = null;
        try {
            $existing = $this->file($project, $path, $owner, $repo, $branch);
        } catch (NexusGithubException $exception) {
            if ($exception->errorCode !== 'github_not_found') {
                throw $exception;
            }
        }

        $currentSha = $existing['sha'] ?? null;
        if ($currentSha !== null && $expectedSha === null) {
            throw new NexusGithubException(
                'El archivo ya existe. Lee su SHA actual y envía expected_sha para confirmar que no cambió.',
                'github_conflict',
                409,
                ['current_sha' => $currentSha]
            );
        }
        if ($expectedSha !== null && $currentSha !== $expectedSha) {
            throw new NexusGithubException(
                'El archivo cambió en GitHub desde la última lectura; no se sobrescribirán cambios humanos.',
                'github_conflict',
                409,
                ['expected_sha' => $expectedSha, 'current_sha' => $currentSha]
            );
        }

        $payload = [
            'message' => $message,
            'content' => base64_encode($content),
            'branch' => $branch,
        ];
        if ($currentSha !== null) {
            $payload['sha'] = $currentSha;
        }

        $result = $this->request('put', "/repos/{$owner}/{$repo}/contents/".ltrim($path, '/'), [], $payload)->json();
        return [
            'repository' => "{$owner}/{$repo}",
            'branch' => $branch,
            'path' => ltrim($path, '/'),
            'sha' => $result['content']['sha'] ?? null,
            'commit' => $result['commit'] ?? null,
            'human_change_checked' => true,
        ];
    }

    public function createIssue(Proyecto $project, string $title, string $body = '', array $labels = []): array
    {
        [$owner, $repo] = $this->repositoryParts($project, null, null);
        return $this->request('post', "/repos/{$owner}/{$repo}/issues", [], [
            'title' => $title,
            'body' => $body,
            'labels' => $labels,
        ])->json();
    }

    public function createPullRequest(
        Proyecto $project,
        string $title,
        string $head,
        string $base,
        string $body = '',
    ): array {
        [$owner, $repo] = $this->repositoryParts($project, null, null);
        return $this->request('post', "/repos/{$owner}/{$repo}/pulls", [], [
            'title' => $title,
            'head' => $head,
            'base' => $base,
            'body' => $body,
        ])->json();
    }

    public function createBranch(Proyecto $project, string $name, ?string $fromBranch = null): array
    {
        [$owner, $repo] = $this->repositoryParts($project, null, null);
        if (preg_match('/^[A-Za-z0-9._\/-]+$/', $name) !== 1 || str_contains($name, '..')) {
            throw new NexusGithubException('El nombre de la rama no es válido.', 'github_invalid_branch');
        }
        $repository = $this->repository($project, $owner, $repo);
        $fromBranch ??= $repository['default_branch'] ?? 'main';
        $reference = $this->request('get', "/repos/{$owner}/{$repo}/git/ref/heads/".rawurlencode($fromBranch), [], [], false)->json();

        return $this->request('post', "/repos/{$owner}/{$repo}/git/refs", [], [
            'ref' => 'refs/heads/'.$name,
            'sha' => $reference['object']['sha'] ?? null,
        ])->json();
    }

    private function repositoryParts(?Proyecto $project, ?string $owner, ?string $repo): array
    {
        if ($owner && $repo) {
            return [$this->safeSegment($owner), $this->safeSegment($repo)];
        }
        $url = $project?->integracionGithub?->repositorio_url ?: $project?->repositorio_url;
        if (! $url || ! preg_match('#^https://github\.com/([^/]+)/([^/#]+?)(?:\.git)?$#i', rtrim($url, '/'), $matches)) {
            throw new NexusGithubException('No hay un repositorio GitHub válido configurado.', 'github_repository_not_configured');
        }

        return [$this->safeSegment($matches[1]), $this->safeSegment($matches[2])];
    }

    private function safeSegment(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $value) !== 1) {
            throw new NexusGithubException('El propietario o repositorio GitHub no es válido.', 'github_invalid_repository');
        }
        return $value;
    }

    private function request(string $method, string $path, array $query = [], array $payload = [], bool $authenticated = true): Response
    {
        if ($authenticated && ! config('services.github.token')) {
            throw new NexusGithubException('GITHUB_TOKEN no está configurado.', 'github_authentication_required');
        }

        $request = Http::acceptJson()
            ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
            ->withOptions(['verify' => config('services.github.ca_bundle') ?: true])
            ->timeout(15);
        if (config('services.github.token')) {
            $request = $request->withToken(config('services.github.token'));
        }
        try {
            $response = $request->{$method}(self::API.$path, $method === 'get' ? $query : $payload);
        } catch (ConnectionException $exception) {
            throw new NexusGithubException(
                'No se pudo conectar con GitHub.',
                'github_connection_failed',
                null,
                ['exception' => $exception->getMessage()]
            );
        }

        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $message = $response->json('message') ?: 'GitHub rechazó la solicitud.';
        $code = match ($status) {
            401, 403 => $response->header('X-RateLimit-Remaining') === '0' || str_contains(strtolower($message), 'rate')
                ? 'github_rate_limited'
                : 'github_forbidden',
            404 => 'github_not_found',
            409 => 'github_conflict',
            default => $status >= 500 ? 'github_unavailable' : 'github_api_error',
        };

        throw new NexusGithubException($message, $code, $status, [
            'documentation_url' => $response->json('documentation_url'),
            'rate_limit_remaining' => $response->header('X-RateLimit-Remaining'),
            'rate_limit_reset' => $response->header('X-RateLimit-Reset'),
        ]);
    }
}
