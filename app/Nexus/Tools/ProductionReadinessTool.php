<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use App\Nexus\NexusToolResult;
use App\Services\NexusProductionReadinessService;

final class ProductionReadinessTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusProductionReadinessService $audit)
    {
    }

    public function name(): string
    {
        return 'nexus.audit.production_readiness';
    }

    public function description(): string
    {
        return 'Audita contratos, capas y límites de producción de Nexus sin ejecutar mutaciones.';
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
        return NexusToolResult::success(
            $this->audit->audit(app(NexusToolRegistry::class))
        );
    }
}
