<?php

namespace App\Nexus\Tools;

use App\Models\Tarea;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Illuminate\Validation\Rule;

class TaskCreateTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.tasks.create';
    }

    public function description(): string
    {
        return 'Crea una tarea asociada a un proyecto.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => true],
            'title' => ['type' => 'string', 'required' => true],
            'description' => ['type' => 'string', 'required' => false],
            'priority' => ['type' => 'string', 'required' => false],
            'status' => ['type' => 'string', 'required' => false],
            'due_date' => ['type' => 'date', 'required' => false],
            'user_id' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['devcontrol.write'];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    protected function validationRules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:proyectos,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['Alta', 'Media', 'Baja'])],
            'status' => ['nullable', Rule::in(['Pendiente', 'En progreso', 'En revisión', 'Completado', 'Cancelado'])],
            'due_date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $task = Tarea::create([
            'proyecto_id' => $parameters['project_id'],
            'usuario_id' => $parameters['user_id'] ?? $context->user?->id,
            'titulo' => $parameters['title'],
            'descripcion' => $parameters['description'] ?? null,
            'prioridad' => $parameters['priority'] ?? 'Media',
            'estado' => $parameters['status'] ?? 'Pendiente',
            'fecha_limite' => $parameters['due_date'] ?? null,
        ]);

        return NexusToolResult::success($task->fresh(['proyecto', 'usuario']));
    }
}
