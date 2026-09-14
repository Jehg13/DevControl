<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusDatasetService;

final class DatasetGenerateTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusDatasetService $datasets)
    {
    }

    public function name(): string
    {
        return 'nexus.dataset.generate';
    }

    public function description(): string
    {
        return 'Construye ejemplos de dataset de entrenamiento a partir de experiencias, ejecuciones, planes, bugs y tareas, con limpieza, scoring, anonimización, deduplicación y división reproducible.';
    }

    public function parameters(): array
    {
        return [
            'version' => ['type' => 'string', 'required' => false],
            'minimum_quality' => ['type' => 'integer', 'required' => false],
            'training_percent' => ['type' => 'integer', 'required' => false],
            'validation_percent' => ['type' => 'integer', 'required' => false],
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
            'version' => ['nullable', 'string', 'max:40'],
            'minimum_quality' => ['nullable', 'integer', 'min:0', 'max:100'],
            'training_percent' => ['nullable', 'integer', 'min:1', 'max:98'],
            'validation_percent' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->datasets->generate($parameters));
    }
}
