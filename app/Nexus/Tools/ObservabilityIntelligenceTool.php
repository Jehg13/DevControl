<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusObservabilityService;

final class ObservabilityIntelligenceTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusObservabilityService $observability)
    {
    }

    public function name(): string
    {
        return 'devcontrol.observability.read';
    }

    public function description(): string
    {
        return 'Analiza logs, errores, incidentes, actualizaciones y hallazgos de DevControl con evidencia, sin ejecutar acciones.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => false],
            'hours' => ['type' => 'integer', 'required' => false, 'description' => 'Ventana de observación entre 1 y 168 horas.'],
        ];
    }

    public function permissions(): array
    {
        return ['devcontrol.read'];
    }

    protected function validationRules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'exists:proyectos,id'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        try {
            return NexusToolResult::success($this->observability->observe(
                $parameters['project_id'] ?? null,
                $parameters['hours'] ?? 24,
            ), ['tool' => $this->name(), 'read_only' => true]);
        } catch (\Throwable $exception) {
            return NexusToolResult::failure(
                'observability_read_failed',
                'No se pudo construir la observación de DevControl.',
                ['detail' => $exception->getMessage(), 'read_only' => true]
            );
        }
    }
}
