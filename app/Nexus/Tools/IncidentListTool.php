<?php

namespace App\Nexus\Tools;

use App\Models\Incidente;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

class IncidentListTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.incidents.list';
    }

    public function description(): string
    {
        return 'Consulta incidentes por proyecto, estado o prioridad.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => false],
            'status' => ['type' => 'string', 'required' => false],
            'priority' => ['type' => 'string', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['devcontrol.read'];
    }

    protected function validationRules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:20'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $incidents = Incidente::query()
            ->with('proyecto:id,nombre')
            ->when($parameters['project_id'] ?? null, fn ($query, int $id) => $query->where('proyecto_id', $id))
            ->when($parameters['status'] ?? null, fn ($query, string $status) => $query->where('estado', $status))
            ->when($parameters['priority'] ?? null, fn ($query, string $priority) => $query->where('prioridad', $priority))
            ->latest('id')
            ->get();

        return NexusToolResult::success($incidents->toArray());
    }
}
