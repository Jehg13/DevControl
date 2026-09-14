<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusAuditService;

class AuditHealthTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusAuditService $audit)
    {
    }

    public function name(): string
    {
        return 'nexus.audit.health';
    }

    public function description(): string
    {
        return 'Obtiene el estado general del sistema de auditoría de Nexus.';
    }

    public function parameters(): array
    {
        return [];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->audit->health());
    }
}
