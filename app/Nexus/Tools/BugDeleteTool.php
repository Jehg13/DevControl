<?php

namespace App\Nexus\Tools;

use App\Models\Bug;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

class BugDeleteTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.bugs.delete';
    }

    public function description(): string
    {
        return 'Elimina un bug por su identificador.';
    }

    public function parameters(): array
    {
        return ['bug_id' => ['type' => 'integer', 'required' => true]];
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
        return ['bug_id' => ['required', 'integer', 'exists:bugs,id']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $bug = Bug::findOrFail($parameters['bug_id']);
        $deleted = $bug->delete();

        return NexusToolResult::success([
            'bug_id' => $bug->id,
            'deleted' => $deleted,
        ]);
    }
}
