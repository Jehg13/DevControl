<?php

namespace App\Nexus\Tools;

use App\Models\NexusLearningRecord;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusLearningService;

final class LearningAnalyzeTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusLearningService $learning)
    {
    }

    public function name(): string { return 'nexus.learning.analyze'; }

    public function description(): string
    {
        return 'Detecta patrones, estrategias y errores repetidos, y permite validar, rechazar, aplicar o revertir aprendizajes controlados.';
    }

    public function parameters(): array
    {
        return [
            'operation' => ['type' => 'string', 'required' => false],
            'project_id' => ['type' => 'integer', 'required' => false],
            'learning_id' => ['type' => 'integer', 'required' => false],
            'confidence' => ['type' => 'integer', 'required' => false],
            'reason' => ['type' => 'string', 'required' => false],
        ];
    }

    public function permissions(): array { return ['nexus.write']; }
    public function requiresConfirmation(): bool { return true; }

    protected function validationRules(): array
    {
        return [
            'operation' => ['nullable', 'in:analyze,validate,reject,apply,rollback'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'learning_id' => ['required_unless:operation,analyze', 'integer', 'min:1'],
            'confidence' => ['nullable', 'integer', 'min:0', 'max:100'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $operation = $parameters['operation'] ?? 'analyze';
        if ($operation === 'analyze') {
            return NexusToolResult::success($this->learning->analyze($parameters['project_id'] ?? $context->projectId));
        }

        $record = NexusLearningRecord::findOrFail($parameters['learning_id']);
        $result = match ($operation) {
            'validate' => $this->learning->validate($record, (int) ($parameters['confidence'] ?? 80)),
            'reject' => $this->learning->reject($record, (string) ($parameters['reason'] ?? 'Rechazado por revisión humana.')),
            'apply' => $this->learning->apply($record),
            'rollback' => $this->learning->rollback($record),
        };

        return NexusToolResult::success(['learning' => $result->toArray()]);
    }
}
