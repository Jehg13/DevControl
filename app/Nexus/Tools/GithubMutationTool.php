<?php

namespace App\Nexus\Tools;

use App\Exceptions\NexusGithubException;
use App\Models\Proyecto;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusGithubService;

class GithubMutationTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusGithubService $github)
    {
    }

    public function name(): string
    {
        return 'nexus.github.write';
    }

    public function description(): string
    {
        return 'Ejecuta una operación GitHub autorizada: crear rama, issue o pull request. Requiere permiso github.write y confirmación explícita.';
    }

    public function parameters(): array
    {
        return [
            'operation' => ['type' => 'string', 'required' => true, 'enum' => ['branch', 'issue', 'pull_request']],
            'project_id' => ['type' => 'integer', 'required' => true],
            'name' => ['type' => 'string', 'required' => false],
            'title' => ['type' => 'string', 'required' => false],
            'body' => ['type' => 'string', 'required' => false],
            'labels' => ['type' => 'array', 'required' => false],
            'head' => ['type' => 'string', 'required' => false],
            'base' => ['type' => 'string', 'required' => false],
            'from_branch' => ['type' => 'string', 'required' => false],
            'plan_id' => ['type' => 'integer', 'required' => false],
            'reason' => ['type' => 'string', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.write', 'github.write'];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    protected function validationRules(): array
    {
        return [
            'operation' => ['required', 'in:branch,issue,pull_request'],
            'project_id' => ['required', 'integer', 'min:1', 'exists:proyectos,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:20000'],
            'labels' => ['nullable', 'array'],
            'head' => ['nullable', 'string', 'max:255'],
            'base' => ['nullable', 'string', 'max:255'],
            'from_branch' => ['nullable', 'string', 'max:255'],
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        try {
            if ($parameters['operation'] === 'branch' && empty($parameters['name'])) {
                return NexusToolResult::failure('validation_failed', 'La operación branch requiere name.');
            }
            if ($parameters['operation'] === 'issue' && empty($parameters['title'])) {
                return NexusToolResult::failure('validation_failed', 'La operación issue requiere title.');
            }
            if ($parameters['operation'] === 'pull_request'
                && (empty($parameters['title']) || empty($parameters['head']) || empty($parameters['base']))) {
                return NexusToolResult::failure('validation_failed', 'La operación pull_request requiere title, head y base.');
            }
            $project = Proyecto::findOrFail($parameters['project_id']);
            $data = match ($parameters['operation']) {
                'branch' => $this->github->createBranch($project, $parameters['name'], $parameters['from_branch'] ?? null),
                'issue' => $this->github->createIssue($project, $parameters['title'], $parameters['body'] ?? '', $parameters['labels'] ?? []),
                'pull_request' => $this->github->createPullRequest($project, $parameters['title'], $parameters['head'], $parameters['base'], $parameters['body'] ?? ''),
            };

            return NexusToolResult::success([
                'operation' => $parameters['operation'],
                'result' => $data,
                'reason' => $parameters['reason'] ?? null,
                'plan_id' => $parameters['plan_id'] ?? null,
            ]);
        } catch (NexusGithubException $exception) {
            return NexusToolResult::failure($exception->errorCode, $exception->getMessage(), $exception->meta);
        }
    }
}
