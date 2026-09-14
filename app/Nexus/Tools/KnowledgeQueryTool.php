<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusKnowledgeGraphService;

final class KnowledgeQueryTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusKnowledgeGraphService $knowledge)
    {
    }

    public function name(): string
    {
        return 'nexus.knowledge.query';
    }

    public function description(): string
    {
        return 'Busca hechos y relaciones técnicas trazables o analiza el impacto de una entidad conocida.';
    }

    public function parameters(): array
    {
        return [
            'query' => ['type' => 'string', 'required' => false],
            'operation' => ['type' => 'string', 'required' => false],
            'entity_type' => ['type' => 'string', 'required' => false],
            'entity_key' => ['type' => 'string', 'required' => false],
            'project_id' => ['type' => 'integer', 'required' => false],
            'limit' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'query' => ['nullable', 'string', 'max:4000'],
            'operation' => ['nullable', 'in:search,impact'],
            'entity_type' => ['required_if:operation,impact', 'string', 'max:80'],
            'entity_key' => ['required_if:operation,impact', 'string', 'max:500'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $operation = $parameters['operation'] ?? 'search';
        if ($operation === 'impact') {
            return NexusToolResult::success([
                'operation' => 'impact',
                'claims' => $this->knowledge->impact(
                    $parameters['entity_type'],
                    $parameters['entity_key'],
                    $parameters['project_id'] ?? $context->projectId
                ),
            ]);
        }

        if (trim((string) ($parameters['query'] ?? '')) === '') {
            return NexusToolResult::failure('invalid_query', 'La búsqueda de conocimiento requiere una consulta.');
        }

        return NexusToolResult::success([
            'operation' => 'search',
            'claims' => $this->knowledge->search(
                $parameters['query'],
                $parameters['project_id'] ?? $context->projectId,
                (int) ($parameters['limit'] ?? 20)
            ),
        ]);
    }
}
