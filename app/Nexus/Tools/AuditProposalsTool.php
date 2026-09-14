<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusAuditService;

class AuditProposalsTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusAuditService $audit)
    {
    }

    public function name(): string
    {
        return 'nexus.audit.proposals';
    }

    public function description(): string
    {
        return 'Genera propuestas deterministas para los hallazgos activos de Nexus.';
    }

    public function parameters(): array
    {
        return ['project_id' => ['type' => 'integer', 'required' => false]];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return ['project_id' => ['nullable', 'integer', 'min:1']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->audit->proposals(
            $parameters['project_id'] ?? null
        )->values()->toArray());
    }
}
