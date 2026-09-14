<?php

namespace App\Nexus\Tools;

use App\Http\Controllers\ProyectoController;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

final class GitStatusTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'nexus.git.status';
    }

    public function description(): string
    {
        return 'Consulta los cambios locales publicables reutilizando la detección segura existente.';
    }

    public function parameters(): array
    {
        return ['project_id' => ['type' => 'integer', 'required' => false]];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return ['project_id' => ['nullable', 'integer', 'min:1']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success(app(ProyectoController::class)->cambiosLocalesPublicables());
    }
}
