<?php

namespace App\Nexus\Tools;

use App\Models\Proyecto;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

class ProjectListTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.projects.list';
    }

    public function description(): string
    {
        return 'Lista los proyectos registrados y sus datos básicos de seguimiento.';
    }

    public function parameters(): array
    {
        return ['status' => ['type' => 'string', 'required' => false, 'description' => 'Estado del proyecto.']];
    }

    public function permissions(): array
    {
        return ['devcontrol.read'];
    }

    protected function validationRules(): array
    {
        return ['status' => ['nullable', 'string', 'max:30']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $projects = Proyecto::query()
            ->when($parameters['status'] ?? null, fn ($query, string $status) => $query->where('estado', $status))
            ->latest()
            ->get(['id', 'nombre', 'estado', 'progreso', 'repositorio_url', 'fecha_inicio', 'fecha_meta']);

        return NexusToolResult::success($projects->toArray());
    }
}
