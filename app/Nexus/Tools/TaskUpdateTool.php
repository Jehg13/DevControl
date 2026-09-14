<?php

namespace App\Nexus\Tools;

use App\Models\Tarea;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Illuminate\Validation\Rule;
use App\Exceptions\NexusToolException;

class TaskUpdateTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.tasks.update';
    }

    public function description(): string
    {
        return 'Actualiza de forma explícita los campos permitidos de una tarea.';
    }

    public function parameters(): array
    {
        return [
            'task_id' => ['type' => 'integer', 'required' => true],
            'title' => ['type' => 'string', 'required' => false],
            'description' => ['type' => 'string', 'required' => false],
            'priority' => ['type' => 'string', 'required' => false],
            'status' => ['type' => 'string', 'required' => false],
            'due_date' => ['type' => 'date', 'required' => false],
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
            'task_id' => ['required', 'integer', 'exists:tareas,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['Alta', 'Media', 'Baja'])],
            'status' => ['sometimes', Rule::in(['Pendiente', 'En progreso', 'En revisión', 'Completado', 'Cancelado'])],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $task = Tarea::findOrFail($parameters['task_id']);
        $changes = array_filter([
            'titulo' => $parameters['title'] ?? null,
            'descripcion' => $parameters['description'] ?? null,
            'prioridad' => $parameters['priority'] ?? null,
            'estado' => $parameters['status'] ?? null,
            'fecha_limite' => $parameters['due_date'] ?? null,
        ], fn ($value, string $key) => array_key_exists(match ($key) {
            'titulo' => 'title',
            'descripcion' => 'description',
            'prioridad' => 'priority',
            'estado' => 'status',
            'fecha_limite' => 'due_date',
        }, $parameters), ARRAY_FILTER_USE_BOTH);

        if ($changes === []) {
            throw new NexusToolException('Debes indicar al menos un campo para actualizar.', 'no_changes');
        }

        $task->update($changes);

        return NexusToolResult::success($task->fresh(['proyecto', 'usuario']));
    }
}
