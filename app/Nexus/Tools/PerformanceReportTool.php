<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusOptimizationService;

class PerformanceReportTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusOptimizationService $optimization)
    {
    }

    public function name(): string
    {
        return 'nexus.optimization.report';
    }

    public function description(): string
    {
        return 'Consulta métricas observadas de ejecuciones y patrones de rendimiento sin modificar el sistema.';
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
            'report' => $this->optimization->report($parameters['project_id'] ?? $context->projectId),
        ]);
    }
}
