<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusAuditService;

class AuditFindingsTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusAuditService $audit)
    {
    }

    public function name(): string
    {
        return 'nexus.audit.findings';
    }

    public function description(): string
    {
        return 'Consulta los hallazgos activos de Nexus, opcionalmente filtrados por proyecto y tipo.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => false],
            'type' => ['type' => 'string', 'required' => false, 'max' => 40],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'string', 'max:40'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->audit->findings(
            $parameters['project_id'] ?? null,
            $parameters['type'] ?? null
        )->values()->toArray());
    }
}
