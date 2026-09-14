<?php

namespace App\Nexus\Tools;

use App\Http\Controllers\ProyectoController;
use App\Models\Proyecto;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Throwable;

final class GithubLocalCommitTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'nexus.github.local.commit';
    }

    public function description(): string
    {
        return 'Publica los cambios locales permitidos como un commit de GitHub usando la implementación existente.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => true],
            'message' => ['type' => 'string', 'required' => true],
            'branch' => ['type' => 'string', 'required' => false],
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
            'project_id' => ['required', 'integer', 'exists:proyectos,id'],
            'message' => ['required', 'string', 'max:500'],
            'branch' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        try {
            $result = app(ProyectoController::class)->crearCommitGithubDesdeCambiosLocales(
                Proyecto::findOrFail($parameters['project_id']),
                ['mensaje' => $parameters['message'], 'rama' => $parameters['branch'] ?? null],
            );

            return NexusToolResult::success($result);
        } catch (Throwable $exception) {
            return NexusToolResult::failure('github_commit_failed', $exception->getMessage());
        }
    }
}
