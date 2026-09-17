<?php

namespace App\Nexus\Tools;

use App\Models\Actualizacion;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

class UpdateListTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.updates.list';
    }

    public function description(): string
    {
        return 'Consulta actualizaciones registradas por proyecto.';
    }

    public function parameters(): array
    {
        return ['project_id' => ['type' => 'integer', 'required' => false]];
    }

    public function permissions(): array
    {
        return ['devcontrol.read'];
    }

    protected function validationRules(): array
    {
        return ['project_id' => ['nullable', 'integer', 'min:1']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success(
            Actualizacion::query()
                ->with('proyecto:id,nombre')
                ->when($parameters['project_id'] ?? null, fn ($query, int $id) => $query->where('proyecto_id', $id))
                ->latest('id')
                ->get()
                ->toArray()
        );
    }
}
