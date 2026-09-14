<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusOptimizationService;

class OptimizationProposalsTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusOptimizationService $optimization)
    {
    }

    public function name(): string
    {
        return 'nexus.optimization.proposals';
    }

    public function description(): string
    {
        return 'Detecta patrones y crea propuestas revisables; nunca aplica cambios automáticamente.';
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
        return NexusToolResult::success([
            'proposals' => $this->optimization->propose($parameters['project_id'] ?? $context->projectId),
        ]);
    }
}
