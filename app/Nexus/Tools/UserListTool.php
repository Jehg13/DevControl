<?php

namespace App\Nexus\Tools;

use App\Models\User;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

class UserListTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.users.list';
    }

    public function description(): string
    {
        return 'Consulta usuarios visibles para el permiso de lectura de DevControl.';
    }

    public function parameters(): array
    {
        return [];
    }

    public function permissions(): array
    {
        return ['devcontrol.read'];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success(
            User::query()->latest('id')->get(['id', 'name', 'rol'])->toArray()
        );
    }
}
