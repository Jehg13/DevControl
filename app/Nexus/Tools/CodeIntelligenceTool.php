<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusCodeIntelligenceService;

final class CodeIntelligenceTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusCodeIntelligenceService $intelligence)
    {
    }

    public function name(): string
    {
        return 'nexus.code.intelligence';
    }

    public function description(): string
    {
        return 'Indexa código sin LLM usando parsers deterministas, símbolos, imports, dependencias, consultas y relaciones. Soporta análisis incremental.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => false],
            'path' => ['type' => 'string', 'required' => false],
            'refresh' => ['type' => 'boolean', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'min:1'],
            'path' => ['nullable', 'string', 'max:300'],
            'refresh' => ['nullable', 'boolean'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->intelligence->analyze(
            $parameters['project_id'] ?? $context->projectId,
            $parameters['path'] ?? null,
            (bool) ($parameters['refresh'] ?? false)
        ));
    }
}
