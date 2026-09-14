<?php

namespace App\Nexus\Tools;

use App\Models\Bug;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Exceptions\NexusToolException;
use Illuminate\Validation\Rule;

class BugUpdateTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.bugs.update';
    }

    public function description(): string
    {
        return 'Actualiza los campos permitidos de un bug identificado por su ID.';
    }

    public function parameters(): array
    {
        return [
            'bug_id' => ['type' => 'integer', 'required' => true],
            'title' => ['type' => 'string', 'required' => false],
            'description' => ['type' => 'string', 'required' => false],
            'status' => ['type' => 'string', 'required' => false],
            'priority' => ['type' => 'string', 'required' => false],
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
            'bug_id' => ['required', 'integer', 'exists:bugs,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['Reportado', 'Investigando', 'En desarrollo', 'En pruebas', 'Solucionado', 'Cerrado'])],
            'priority' => ['sometimes', Rule::in(['Baja', 'Media', 'Alta'])],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $bug = Bug::findOrFail($parameters['bug_id']);
        $changes = [];

        foreach ([
            'title' => 'titulo',
            'description' => 'descripcion',
            'status' => 'estado',
            'priority' => 'prioridad',
        ] as $input => $column) {
            if (array_key_exists($input, $parameters)) {
                $changes[$column] = $parameters[$input];
            }
        }

        if ($changes === []) {
            throw new NexusToolException('Debes indicar al menos un campo para actualizar.', 'no_changes');
        }

        $bug->update($changes);

        return NexusToolResult::success($bug->fresh('proyecto'));
    }
}
