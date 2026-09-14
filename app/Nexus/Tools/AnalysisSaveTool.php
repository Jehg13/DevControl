<?php

namespace App\Nexus\Tools;

use App\Models\NexusAnalysisResult;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;

class AnalysisSaveTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'nexus.analysis.save';
    }

    public function description(): string
    {
        return 'Guarda un resultado estructurado de análisis asociado opcionalmente a un proyecto y ejecución.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => false],
            'run_id' => ['type' => 'integer', 'required' => false],
            'title' => ['type' => 'string', 'required' => true],
            'analysis_type' => ['type' => 'string', 'required' => false],
            'result' => ['type' => 'object', 'required' => true],
        ];
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
        return [
            'project_id' => ['nullable', 'integer', 'exists:proyectos,id'],
            'run_id' => ['nullable', 'integer', 'exists:nexus_runs,id'],
            'title' => ['required', 'string', 'max:190'],
            'analysis_type' => ['nullable', 'string', 'max:50'],
            'result' => ['required', 'array'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $analysis = NexusAnalysisResult::create([
            'proyecto_id' => $parameters['project_id'] ?? null,
            'nexus_run_id' => $parameters['run_id'] ?? null,
            'usuario_id' => $context->user?->id,
            'title' => $parameters['title'],
            'analysis_type' => $parameters['analysis_type'] ?? 'code',
            'result' => $parameters['result'],
        ]);

        return NexusToolResult::success($analysis->fresh(['proyecto', 'run']));
    }
}
