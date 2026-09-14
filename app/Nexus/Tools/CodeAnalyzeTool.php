<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusCodeAnalysisService;

class CodeAnalyzeTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusCodeAnalysisService $analysis)
    {
    }

    public function name(): string
    {
        return 'nexus.code.analyze';
    }

    public function description(): string
    {
        return 'Analiza un proyecto antes de modificarlo: tecnologías, estructura, archivos relevantes, símbolos, relaciones, dependencias y documentación. Es de solo lectura.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => false],
            'path' => ['type' => 'string', 'required' => false, 'description' => 'Ruta relativa dentro del proyecto.'],
            'include_documentation' => ['type' => 'boolean', 'required' => false],
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
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->analysis->analyze(
            $parameters['project_id'] ?? null,
            $parameters['path'] ?? null,
            (bool) ($parameters['include_documentation'] ?? true)
        ));
    }
}
