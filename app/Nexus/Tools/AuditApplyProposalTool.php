<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusAuditService;

class AuditApplyProposalTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusAuditService $audit)
    {
    }

    public function name(): string
    {
        return 'nexus.audit.apply_proposal';
    }

    public function description(): string
    {
        return 'Aplica una propuesta de cambio determinista y valida los archivos después de escribirlos.';
    }

    public function parameters(): array
    {
        return ['finding_id' => ['type' => 'integer', 'required' => true, 'description' => 'ID del hallazgo.']];
    }

    public function permissions(): array
    {
        return ['nexus.write'];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    protected function validationRules(): array
    {
        return ['finding_id' => ['required', 'integer', 'min:1']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->audit->applyProposal($parameters['finding_id']));
    }
}
