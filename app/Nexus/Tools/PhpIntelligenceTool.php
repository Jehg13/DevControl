<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusPhpIntelligenceService;

final class PhpIntelligenceTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusPhpIntelligenceService $intelligence)
    {
    }

    public function name(): string
    {
        return 'nexus.php.diagnose';
    }

    public function description(): string
    {
        return 'Diagnostica riesgos avanzados de PHP: memoria, Composer, autoloading, PSR, runtime, streams, procesos, OPcache y errores.';
    }

    public function parameters(): array
    {
        return [
            'path' => ['type' => 'string', 'required' => false, 'description' => 'Ruta relativa al proyecto.'],
            'max_files' => ['type' => 'integer', 'required' => false, 'description' => 'Máximo de archivos PHP analizados.'],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return ['path' => ['nullable', 'string', 'max:255'], 'max_files' => ['nullable', 'integer', 'min:1', 'max:500']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->intelligence->diagnose($parameters['path'] ?? null, (int) ($parameters['max_files'] ?? 200)));
    }
}
