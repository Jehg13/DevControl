<?php

namespace App\Nexus\Tools;

use App\Models\NexusRun;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusPlannerService;
use App\Nexus\NexusToolRegistry;
use App\Services\NexusPermissionManager;

class PlanCreateTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusPlannerService $planner)
    {
    }

    public function name(): string
    {
        return 'nexus.plan.create';
    }

    public function description(): string
    {
        return 'Convierte un objetivo en un plan mínimo y estructurado sin ejecutar acciones ni modificar el proyecto.';
    }

    public function parameters(): array
    {
        return [
            'objective' => ['type' => 'string', 'required' => true],
            'context' => ['type' => 'object', 'required' => false],
            'project' => ['type' => 'object', 'required' => false],
            'restrictions' => ['type' => 'array', 'required' => false],
            'permissions' => ['type' => 'array', 'required' => false],
            'run_id' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'objective' => ['required', 'string', 'max:20000'],
            'context' => ['nullable', 'array'],
            'project' => ['nullable', 'array'],
            'restrictions' => ['nullable', 'array'],
            'permissions' => ['nullable', 'array'],
            'run_id' => ['nullable', 'integer', 'exists:nexus_runs,id'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $result = $this->planner->create(
            $parameters['objective'],
            $parameters['context'] ?? [],
            $parameters['project'] ?? [],
            $parameters['restrictions'] ?? [],
            $parameters['permissions'] ?? [],
            app(NexusToolRegistry::class)->definitions(),
        );

        if (! $result->successful) {
            return NexusToolResult::failure($result->errorCode ?? 'planning_failed', $result->error ?? 'No se pudo crear el plan.');
        }

        $plan = null;
        if (isset($parameters['run_id'])) {
            $plan = $this->planner->persist(
                NexusRun::findOrFail($parameters['run_id']),
                $parameters['objective'],
                $result->response?->metadata ?? [],
                $parameters['context'] ?? [],
            );
        }

        return NexusToolResult::success([
            'plan' => $result->response?->metadata ?? [],
            'message' => $result->response?->message,
            'persisted_plan_id' => $plan?->id,
            'read_only_execution' => true,
        ]);
    }
}
