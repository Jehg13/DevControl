<?php

namespace App\Nexus\Tools;

use App\Exceptions\NexusGithubException;
use App\Models\Proyecto;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusGithubService;

class GithubInspectTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusGithubService $github)
    {
    }

    public function name(): string
    {
        return 'nexus.github.inspect';
    }

    public function description(): string
    {
        return 'Consulta de forma estructurada un repositorio GitHub: repositorio, ramas, commits, cambios recientes, archivos, contenido de archivos o issues abiertos.';
    }

    public function parameters(): array
    {
        return [
            'operation' => ['type' => 'string', 'required' => true, 'enum' => ['repository', 'branches', 'commits', 'changes', 'files', 'file', 'issues', 'pull_requests', 'releases']],
            'project_id' => ['type' => 'integer', 'required' => false],
            'owner' => ['type' => 'string', 'required' => false],
            'repo' => ['type' => 'string', 'required' => false],
            'branch' => ['type' => 'string', 'required' => false],
            'path' => ['type' => 'string', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'operation' => ['required', 'in:repository,branches,commits,changes,files,file,issues,pull_requests,releases'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'owner' => ['nullable', 'string', 'max:100'],
            'repo' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:500', 'regex:/^(?!\/)(?!.*\.\.).+$/'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        try {
            if ($parameters['operation'] === 'file' && empty($parameters['path'])) {
                return NexusToolResult::failure('validation_failed', 'La operación file requiere path.');
            }
            $project = null;
            if (isset($parameters['project_id'])) {
                $project = Proyecto::find($parameters['project_id']);
            }
            $operation = $parameters['operation'];
            $args = [$project, $parameters['owner'] ?? null, $parameters['repo'] ?? null];
            $data = match ($operation) {
                'repository' => $this->github->repository(...$args),
                'branches' => $this->github->branches(...$args),
                'commits' => $this->github->commits($project, $parameters['owner'] ?? null, $parameters['repo'] ?? null, $parameters['branch'] ?? null),
                'changes' => $this->github->changes($project, $parameters['owner'] ?? null, $parameters['repo'] ?? null, $parameters['branch'] ?? null),
                'files' => $this->github->files($project, $parameters['owner'] ?? null, $parameters['repo'] ?? null, $parameters['branch'] ?? null),
                'file' => $this->github->file($project, $parameters['path'], $parameters['owner'] ?? null, $parameters['repo'] ?? null, $parameters['branch'] ?? null),
                'issues' => $this->github->issues(...$args),
                'pull_requests' => $this->github->pullRequests(...$args),
                'releases' => $this->github->releases(...$args),
            };

            return NexusToolResult::success($data);
        } catch (NexusGithubException $exception) {
            return NexusToolResult::failure($exception->errorCode, $exception->getMessage(), $exception->meta);
        }
    }
}
