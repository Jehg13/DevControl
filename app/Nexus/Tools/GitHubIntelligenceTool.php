<?php

namespace App\Nexus\Tools;

use App\Models\Bug;
use App\Models\Integracion;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Throwable;

final class GitHubIntelligenceTool extends AbstractNexusTool
{
    private const OPERATIONS = [
        'repository',
        'branches',
        'commits',
        'commit',
        'pull_requests',
        'pull_request',
        'changes',
        'files',
        'history',
        'issues',
        'status',
        'correlate',
    ];

    public function name(): string
    {
        return 'github.intelligence.read';
    }

    public function description(): string
    {
        return 'Consulta repositorios, ramas, commits, pull requests, cambios, archivos, historial, issues y estado de GitHub en modo solo lectura, correlacionándolos con DevControl.';
    }

    public function parameters(): array
    {
        return [
            'operation' => ['type' => 'string', 'required' => true, 'enum' => self::OPERATIONS],
            'project_id' => ['type' => 'integer', 'required' => false],
            'owner' => ['type' => 'string', 'required' => false],
            'repo' => ['type' => 'string', 'required' => false],
            'ref' => ['type' => 'string', 'required' => false],
            'sha' => ['type' => 'string', 'required' => false],
            'number' => ['type' => 'integer', 'required' => false],
            'base' => ['type' => 'string', 'required' => false],
            'head' => ['type' => 'string', 'required' => false],
            'per_page' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['devcontrol.read'];
    }

    protected function validationRules(): array
    {
        return [
            'operation' => ['required', 'string', 'in:'.implode(',', self::OPERATIONS)],
            'project_id' => ['nullable', 'integer', 'exists:proyectos,id'],
            'owner' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'repo' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'ref' => ['nullable', 'string', 'max:255', 'not_regex:/\.\./'],
            'sha' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{7,64}$/'],
            'number' => ['nullable', 'integer', 'min:1'],
            'base' => ['nullable', 'string', 'max:255', 'not_regex:/\.\./'],
            'head' => ['nullable', 'string', 'max:255', 'not_regex:/\.\./'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        try {
            [$owner, $repo, $project] = $this->repository($parameters);
            $operation = $parameters['operation'];
            $client = $this->client();
            $perPage = $parameters['per_page'] ?? 30;

            $data = match ($operation) {
                'repository' => $this->get($client, "/repos/{$owner}/{$repo}"),
                'branches' => $this->get($client, "/repos/{$owner}/{$repo}/branches", ['per_page' => $perPage]),
                'commits', 'history' => $this->get($client, "/repos/{$owner}/{$repo}/commits", [
                    'sha' => $parameters['ref'] ?? null,
                    'per_page' => $perPage,
                ]),
                'commit' => $this->get($client, "/repos/{$owner}/{$repo}/commits/".($parameters['sha'] ?? '')),
                'pull_requests' => $this->get($client, "/repos/{$owner}/{$repo}/pulls", [
                    'state' => 'all',
                    'per_page' => $perPage,
                ]),
                'pull_request' => $this->pullRequest($client, $owner, $repo, $parameters['number'] ?? 0),
                'changes' => $this->get($client, "/repos/{$owner}/{$repo}/compare/".($parameters['base'] ?? '').'...'.($parameters['head'] ?? '')),
                'files' => $this->files($client, $owner, $repo, $parameters['ref'] ?? null),
                'issues' => $this->get($client, "/repos/{$owner}/{$repo}/issues", [
                    'state' => 'all',
                    'per_page' => $perPage,
                ]),
                'status' => $this->status($client, $owner, $repo, $parameters['ref'] ?? null, $project),
                'correlate' => $this->correlate($client, $owner, $repo, $project, $perPage),
            };

            return NexusToolResult::success([
                'operation' => $operation,
                'repository' => "{$owner}/{$repo}",
                'project' => $project?->only(['id', 'nombre', 'repositorio_url']),
                'read_only' => true,
                'source' => 'github_api',
                'data' => $data,
            ], ['tool' => $this->name()]);
        } catch (Throwable $exception) {
            return NexusToolResult::failure(
                'github_read_failed',
                'No se pudo obtener la información de GitHub.',
                ['detail' => $exception->getMessage(), 'read_only' => true]
            );
        }
    }

    /** @return array{0: string, 1: string, 2: ?Proyecto} */
    private function repository(array $parameters): array
    {
        $project = isset($parameters['project_id'])
            ? Proyecto::with('integracionGithub')->findOrFail($parameters['project_id'])
            : null;
        $url = $project?->integracionGithub?->repositorio_url ?: $project?->repositorio_url;
        $parts = $this->parseRepositoryUrl((string) $url);
        $owner = $parameters['owner'] ?? $parts['owner'] ?? null;
        $repo = $parameters['repo'] ?? $parts['repo'] ?? null;

        if (! $owner || ! $repo) {
            throw new \InvalidArgumentException('Se requiere project_id o owner y repo.');
        }
        return [$owner, $repo, $project];
    }

    private function client(): PendingRequest
    {
        $client = Http::acceptJson()
            ->withHeaders(['User-Agent' => 'DevControl-Nexus'])
            ->withOptions(['verify' => config('services.github.ca_bundle') ?: true])
            ->timeout(15);
        return config('services.github.token')
            ? $client->withToken(config('services.github.token'))
            : $client;
    }

    private function get(PendingRequest $client, string $endpoint, array $query = []): mixed
    {
        return $client->get("https://api.github.com{$endpoint}", array_filter($query, fn ($value) => $value !== null))
            ->throw()
            ->json();
    }

    private function pullRequest(PendingRequest $client, string $owner, string $repo, int $number): array
    {
        if ($number < 1) {
            throw new \InvalidArgumentException('pull_request requiere number.');
        }
        return [
            'pull_request' => $this->get($client, "/repos/{$owner}/{$repo}/pulls/{$number}"),
            'files' => $this->get($client, "/repos/{$owner}/{$repo}/pulls/{$number}/files"),
        ];
    }

    private function files(PendingRequest $client, string $owner, string $repo, ?string $ref): array
    {
        $repository = $this->get($client, "/repos/{$owner}/{$repo}");
        return $this->get($client, "/repos/{$owner}/{$repo}/git/trees/".($ref ?: $repository['default_branch']), [
            'recursive' => '1',
        ]);
    }

    private function status(PendingRequest $client, string $owner, string $repo, ?string $ref, ?Proyecto $project): array
    {
        $repository = $this->get($client, "/repos/{$owner}/{$repo}");
        $commit = $ref ?: ($repository['default_branch'] ?? 'main');
        return [
            'repository' => $repository,
            'commit_status' => $this->get($client, "/repos/{$owner}/{$repo}/commits/{$commit}/status"),
            'devcontrol_integration' => $project?->integracionGithub?->only([
                'estado', 'rama_principal', 'ultimo_commit_sha', 'ultimo_commit_mensaje', 'ultima_sincronizacion',
            ]),
        ];
    }

    private function correlate(PendingRequest $client, string $owner, string $repo, ?Proyecto $project, int $perPage): array
    {
        $commits = $this->get($client, "/repos/{$owner}/{$repo}/commits", ['per_page' => $perPage]);
        $pullRequests = $this->get($client, "/repos/{$owner}/{$repo}/pulls", ['state' => 'all', 'per_page' => $perPage]);
        $issues = $this->get($client, "/repos/{$owner}/{$repo}/issues", ['state' => 'all', 'per_page' => $perPage]);
        $devcontrol = $project
            ? [
                'project' => $project->only(['id', 'nombre', 'repositorio_url']),
                'tasks' => Tarea::where('proyecto_id', $project->id)->get(['id', 'titulo', 'estado', 'prioridad'])->toArray(),
                'bugs' => Bug::where('proyecto_id', $project->id)->get(['id', 'folio', 'titulo', 'estado', 'prioridad'])->toArray(),
            ]
            : null;

        $references = collect($devcontrol['tasks'] ?? [])->concat($devcontrol['bugs'] ?? []);
        return [
            'repository' => $this->get($client, "/repos/{$owner}/{$repo}"),
            'commits' => collect($commits)->map(fn (array $commit): array => [
                'sha' => $commit['sha'] ?? null,
                'message' => $commit['commit']['message'] ?? null,
                'project_id' => $project?->id,
                'related_tasks' => $this->matches($commit['commit']['message'] ?? '', $references->whereNotNull('titulo')->all()),
                'related_bugs' => $this->matches($commit['commit']['message'] ?? '', $references->whereNotNull('folio')->all(), 'folio'),
            ])->values()->all(),
            'pull_requests' => $pullRequests,
            'issues' => $issues,
            'devcontrol' => $devcontrol,
        ];
    }

    private function matches(string $text, array $records, string $field = 'titulo'): array
    {
        $text = mb_strtolower($text);
        return collect($records)
            ->filter(fn (array $record): bool => isset($record[$field]) && str_contains($text, mb_strtolower((string) $record[$field])))
            ->map(fn (array $record): array => Arr::only($record, ['id', 'titulo', 'folio', 'estado', 'prioridad']))
            ->values()
            ->all();
    }

    /** @return array{owner: string, repo: string}|null */
    private function parseRepositoryUrl(string $url): ?array
    {
        $parts = parse_url($url);
        $segments = array_values(array_filter(explode('/', trim((string) ($parts['path'] ?? ''), '/'))));
        if (($parts['host'] ?? '') !== 'github.com' || count($segments) !== 2) {
            return null;
        }
        return ['owner' => $segments[0], 'repo' => preg_replace('/\.git$/', '', $segments[1])];
    }
}
