<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusInfrastructureService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class InfrastructureInspectTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusInfrastructureService $infrastructure)
    {
    }

    public function name(): string
    {
        return 'nexus.infrastructure.inspect';
    }

    public function description(): string
    {
        return 'Observa infraestructura relacionada con un proyecto: servidores, recursos, servicios, aplicaciones, bases de datos, dominios, SSL, salud y anomalías.';
    }

    public function parameters(): array
    {
        return [
            'operation' => [
                'type' => 'string',
                'required' => true,
                'enum' => ['inventory', 'health', 'resources', 'services', 'anomalies'],
            ],
            'project_id' => ['type' => 'integer', 'required' => false],
            'infrastructure_id' => ['type' => 'integer', 'required' => false],
            'endpoint' => ['type' => 'string', 'required' => false],
            'timeout' => ['type' => 'integer', 'required' => false],
            'thresholds' => ['type' => 'object', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'operation' => ['required', 'in:inventory,health,resources,services,anomalies'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'infrastructure_id' => ['nullable', 'integer', 'min:1'],
            'endpoint' => ['nullable', 'url', 'max:500'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:30'],
            'thresholds' => ['nullable', 'array'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        try {
            if ($parameters['operation'] === 'anomalies') {
                if (! isset($parameters['infrastructure_id'])) {
                    return NexusToolResult::failure('validation_failed', 'La operación anomalies requiere infrastructure_id.');
                }

                return NexusToolResult::success([
                    'events' => $this->infrastructure->events((int) $parameters['infrastructure_id']),
                ]);
            }

            $result = $this->infrastructure->inspect(
                $parameters['project_id'] ?? $context->projectId,
                $parameters['infrastructure_id'] ?? null,
                $parameters['endpoint'] ?? null,
                (int) ($parameters['timeout'] ?? 5),
                $parameters['thresholds'] ?? [],
                $context->user?->id,
            );

            if (($result['available'] ?? false) === false) {
                return NexusToolResult::failure(
                    $result['error_code'] ?? 'infrastructure_unavailable',
                    $result['message'] ?? 'No se pudo observar la infraestructura.'
                );
            }

            $data = match ($parameters['operation']) {
                'health' => [
                    'health' => $result['health'],
                    'events' => $result['events'],
                ],
                'resources' => ['resources' => $result['infrastructure']['resources'] ?? []],
                'services' => ['services' => $result['infrastructure']['services'] ?? []],
                default => $result,
            };

            return NexusToolResult::success($data);
        } catch (ModelNotFoundException) {
            return NexusToolResult::failure('infrastructure_not_found', 'La infraestructura o proyecto solicitado no existe.');
        }
    }
}
