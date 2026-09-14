<?php

namespace App\Nexus\Tools;

use App\Exceptions\NexusGithubException;
use App\Models\Proyecto;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusGithubService;

class GithubFileWriteTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusGithubService $github)
    {
    }

    public function name(): string
    {
        return 'nexus.github.file.write';
    }

    public function description(): string
    {
        return 'Crea o actualiza un archivo en GitHub después de comprobar el SHA actual para evitar sobrescribir cambios humanos.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => true],
            'path' => ['type' => 'string', 'required' => true],
            'content' => ['type' => 'string', 'required' => true],
            'message' => ['type' => 'string', 'required' => true],
            'branch' => ['type' => 'string', 'required' => false],
            'expected_sha' => ['type' => 'string', 'required' => false],
            'reason' => ['type' => 'string', 'required' => false],
            'plan_id' => ['type' => 'integer', 'required' => false],
            'validation_result' => ['type' => 'object', 'required' => false],
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
            'project_id' => ['required', 'integer', 'min:1', 'exists:proyectos,id'],
            'path' => ['required', 'string', 'max:500', 'regex:/^(?!\/)(?!.*\.\.).+$/'],
            'content' => ['required', 'string', 'max:1000000'],
            'message' => ['required', 'string', 'max:500'],
            'branch' => ['nullable', 'string', 'max:255'],
            'expected_sha' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'validation_result' => ['nullable', 'array'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        try {
            $data = $this->github->createOrUpdateFile(
                Proyecto::findOrFail($parameters['project_id']),
                $parameters['path'],
                $parameters['content'],
                $parameters['message'],
                $parameters['branch'] ?? null,
                $parameters['expected_sha'] ?? null,
            );

            return NexusToolResult::success(array_merge($data, [
                'reason' => $parameters['reason'] ?? null,
                'plan_id' => $parameters['plan_id'] ?? null,
                'validation_result' => $parameters['validation_result'] ?? null,
            ]));
        } catch (NexusGithubException $exception) {
            return NexusToolResult::failure($exception->errorCode, $exception->getMessage(), $exception->meta);
        }
    }
}
