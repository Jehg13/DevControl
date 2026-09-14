<?php

namespace App\Nexus\Tools;

use App\Models\Tarea;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

class TaskListTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.tasks.list';
    }

    public function description(): string
    {
        return 'Consulta tareas por proyecto, estado o prioridad.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => false],
            'status' => ['type' => 'string', 'required' => false],
            'priority' => ['type' => 'string', 'required' => false],
            'open_only' => ['type' => 'boolean', 'required' => false],
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
            'status' => ['nullable', 'string', 'max:30'],
            'priority' => ['nullable', 'string', 'max:20'],
            'open_only' => ['nullable', 'boolean'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $tasks = Tarea::query()
            ->with(['proyecto:id,nombre', 'usuario:id,name', 'seccion:id,nombre', 'funcionalidad:id,nombre'])
            ->when($parameters['project_id'] ?? null, fn ($query, int $id) => $query->where('proyecto_id', $id))
            ->when($parameters['status'] ?? null, fn ($query, string $status) => $query->where('estado', $status))
            ->when($parameters['priority'] ?? null, fn ($query, string $priority) => $query->where('prioridad', $priority))
            ->when($parameters['open_only'] ?? false, fn ($query) => $query->whereIn('estado', ['Pendiente', 'En progreso', 'En revisión']))
            ->latest()
            ->get();

        return NexusToolResult::success($tasks->toArray());
    }
}
