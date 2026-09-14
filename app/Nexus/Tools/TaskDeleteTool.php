<?php

namespace App\Nexus\Tools;

use App\Models\Tarea;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

class TaskDeleteTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.tasks.delete';
    }

    public function description(): string
    {
        return 'Elimina una tarea por su identificador.';
    }

    public function parameters(): array
    {
        return ['task_id' => ['type' => 'integer', 'required' => true]];
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
        return ['task_id' => ['required', 'integer', 'exists:tareas,id']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $task = Tarea::findOrFail($parameters['task_id']);
        $deleted = $task->delete();

        return NexusToolResult::success([
            'task_id' => $task->id,
            'deleted' => $deleted,
        ]);
    }
}
