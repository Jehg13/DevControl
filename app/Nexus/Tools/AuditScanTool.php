<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusAuditService;

class AuditScanTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusAuditService $audit)
    {
    }

    public function name(): string
    {
        return 'nexus.audit.scan';
    }

    public function description(): string
    {
        return 'Analiza proyectos localmente y persiste los hallazgos detectados.';
    }

    public function parameters(): array
    {
        return ['project_id' => ['type' => 'integer', 'required' => false, 'description' => 'ID del proyecto.']];
    }

    public function permissions(): array
    {
        return ['nexus.audit'];
    }

    protected function validationRules(): array
    {
        return ['project_id' => ['nullable', 'integer', 'min:1']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->audit->scan($parameters['project_id'] ?? null));
    }
}
