<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusProjectUnderstandingService;

class ProjectUnderstandTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusProjectUnderstandingService $understanding)
    {
    }

    public function name(): string
    {
        return 'nexus.project.understand';
    }

    public function description(): string
    {
        return 'Construye o recupera una representación estructurada y auditable de un proyecto: tecnologías, módulos, entradas, rutas, capas, datos, relaciones y funcionalidades detectadas.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => false],
            'path' => ['type' => 'string', 'required' => false],
            'include_documentation' => ['type' => 'boolean', 'required' => false],
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
            'include_documentation' => ['nullable', 'boolean'],
            'refresh' => ['nullable', 'boolean'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->understanding->understand(
            $parameters['project_id'] ?? null,
            $parameters['path'] ?? null,
            (bool) ($parameters['include_documentation'] ?? true),
            (bool) ($parameters['refresh'] ?? false),
            $context->user?->id,
        ));
    }
}
