<?php

namespace App\Contracts;

use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

interface NexusTool
{
    public function name(): string;

    public function description(): string;

    public function parameters(): array;

    public function permissions(): array;

    public function requiresConfirmation(): bool;

    public function execute(array $parameters, NexusToolContext $context): NexusToolResult;
}
