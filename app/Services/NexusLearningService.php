<?php

namespace App\Services;

use App\Models\NexusExperience;
use App\Models\NexusLearningRecord;
use App\Models\NexusPerformanceMetric;
use Illuminate\Support\Collection;

final class NexusLearningService
{
    public function __construct(
        private readonly NexusKnowledgeGraphService $knowledge,
        private readonly NexusDatasetService $dataset,
    ) {
    }

    public function analyze(?int $projectId = null): array
    {
        $candidates = collect();
        $this->recurringErrors($candidates, $projectId);
        $this->effectiveStrategies($candidates, $projectId);
        $this->obsoleteKnowledge($candidates, $projectId);
        $records = $candidates->map(fn (array $candidate) => $this->record($candidate))->values();

        return [
            'created' => $records->count(),
            'records' => $records->map(fn (NexusLearningRecord $record) => $this->serialize($record))->all(),
            'summary' => [
                'by_status' => $records->countBy('status')->all(),
                'by_pattern' => $records->countBy('pattern')->all(),
            ],
            'protected' => [
                'permission_manager' => true,
                'security' => true,
                'critical_code' => true,
                'automatic_structural_changes' => false,
            ],
        ];
    }

    public function validate(NexusLearningRecord $record, int $confidence = 80): NexusLearningRecord
    {
        $record->update(['status' => 'validated', 'confidence' => max(0, min(100, $confidence)), 'validated_at' => now(), 'rejected_at' => null]);
        return $record->fresh();
    }

    public function reject(NexusLearningRecord $record, string $reason): NexusLearningRecord
    {
        $record->update([
            'status' => 'rejected',
            'observation' => array_merge($record->observation ?? [], ['rejection_reason' => trim($reason)]),
            'rejected_at' => now(),
            'validated_at' => null,
        ]);
        return $record->fresh();
    }

    public function apply(NexusLearningRecord $record): NexusLearningRecord
    {
        if ($record->status !== 'validated') {
            throw new \InvalidArgumentException('Solo se puede aplicar aprendizaje validado.');
        }
        $record->update([
            'applied_update' => $record->proposed_update,
            'rollback_snapshot' => ['applied_update' => $record->applied_update],
        ]);
        if ($record->pattern === 'effective_strategy') {
            $this->knowledge->relate(
                ['type' => 'learning', 'key' => 'learning:'.$record->id, 'label' => 'Learning '.$record->id],
                'supports_strategy',
                ['type' => 'strategy', 'key' => 'strategy:'.hash('sha256', (string) ($record->proposed_update['strategy'] ?? $record->learning_key)), 'label' => (string) ($record->proposed_update['strategy'] ?? $record->learning_key)],
                'FACT',
                'learning',
                (string) $record->id,
                $record->proyecto_id,
                $record->confidence
            );
        }
        $this->dataset->ingest([
            'PROBLEMA' => $record->observation,
            'CONTEXTO' => $record->evidence,
            'EVIDENCIA' => $record->evidence,
            'ANÁLISIS' => $record->pattern,
            'HIPÓTESIS' => $record->status,
            'DECISIÓN' => $record->proposed_update,
            'ACCIÓN' => $record->applied_update,
            'RESULTADO' => 'validated_learning',
            'SOLUCIÓN' => $record->proposed_update,
            'VALIDACIÓN' => $record->confidence,
        ], 'learning', (string) $record->id, ['minimum_quality' => 0, 'version' => 'learning-1.0']);
        return $record->fresh();
    }

    public function rollback(NexusLearningRecord $record): NexusLearningRecord
    {
        if (! is_array($record->rollback_snapshot)) {
            throw new \InvalidArgumentException('El aprendizaje no tiene snapshot para rollback.');
        }
        $record->update([
            'applied_update' => $record->rollback_snapshot['applied_update'] ?? null,
            'rolled_back_at' => now(),
        ]);
        return $record->fresh();
    }

    public function updateExperience(NexusLearningRecord $record, NexusExperience $experience): NexusExperience
    {
        if ($record->status !== 'validated' || $record->pattern !== 'effective_strategy') {
            throw new \InvalidArgumentException('Solo una estrategia validada puede actualizar Experience Memory.');
        }
        $experience->update([
            'metadata' => array_merge($experience->metadata ?? [], [
                'learning_record_id' => $record->id,
                'validated_strategy' => $record->proposed_update['strategy'] ?? null,
            ]),
            'confidence' => max($experience->confidence, $record->confidence),
        ]);
        return $experience->fresh();
    }

    private function recurringErrors(Collection $candidates, ?int $projectId): void
    {
        $errors = NexusPerformanceMetric::query()
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->get()
            ->flatMap(fn (NexusPerformanceMetric $metric) => (array) data_get($metric->metrics, 'error_patterns', []))
            ->filter(fn ($error): bool => is_string($error) && trim($error) !== '')
            ->countBy();
        foreach ($errors->filter(fn (int $count): bool => $count >= 2) as $error => $count) {
            $candidates->push($this->candidate('recurring_error', 'error:'.hash('sha256', $error), 'recurring_error', ['error' => $error, 'occurrences' => $count], ['metric_count' => $count], ['investigate' => $error], $projectId));
        }
    }

    private function effectiveStrategies(Collection $candidates, ?int $projectId): void
    {
        $strategies = NexusExperience::query()
            ->where('status', 'active')
            ->when($projectId !== null, fn ($q) => $q->where('proyecto_id', $projectId))
            ->get()
            ->filter(fn (NexusExperience $experience): bool => trim($experience->strategy) !== '')
            ->groupBy(fn (NexusExperience $experience): string => mb_strtolower($experience->strategy));
        foreach ($strategies as $strategy => $items) {
            $successful = $items->filter(fn (NexusExperience $experience): bool => $experience->confidence >= 70 && trim($experience->solution) !== '');
            if ($successful->count() < 2) {
                continue;
            }
            $candidates->push($this->candidate('strategy', 'strategy:'.hash('sha256', $strategy), 'effective_strategy', ['strategy' => $strategy, 'successful_experiences' => $successful->pluck('id')->values()->all()], ['experience_count' => $successful->count()], ['strategy' => $strategy, 'source_experiences' => $successful->pluck('id')->values()->all()], $projectId, 'experience', 'inferred'));
        }
    }

    private function obsoleteKnowledge(Collection $candidates, ?int $projectId): void
    {
        $stale = NexusLearningRecord::query()
            ->whereIn('status', ['observed', 'inferred', 'validated'])
            ->whereNotNull('validated_at')
            ->where('validated_at', '<', now()->subMonths(6))
            ->when($projectId !== null, fn ($q) => $q->where('proyecto_id', $projectId))
            ->count();
        if ($stale > 0) {
            $candidates->push($this->candidate('obsolescence', 'obsolete:'.($projectId ?? 'global'), 'obsolete_knowledge', ['records_without_recent_validation' => $stale], ['stale_records' => $stale], ['review_required' => true], $projectId, 'learning'));
        }
    }

    private function candidate(string $type, string $key, string $pattern, array $observation, array $evidence, array $update, ?int $projectId, string $sourceType = 'performance', string $status = 'observed'): array
    {
        return compact('type', 'key', 'pattern', 'observation', 'evidence', 'update', 'projectId', 'sourceType', 'status');
    }

    private function record(array $candidate): NexusLearningRecord
    {
        return NexusLearningRecord::updateOrCreate(
            ['learning_key' => $candidate['key'], 'status' => $candidate['status']],
            [
                'learning_type' => $candidate['type'],
                'pattern' => $candidate['pattern'],
                'observation' => $candidate['observation'],
                'evidence' => $candidate['evidence'],
                'proposed_update' => $candidate['update'],
                'confidence' => $this->confidence($candidate['evidence']),
                'proyecto_id' => $candidate['projectId'],
                'source_type' => $candidate['sourceType'],
                'source_id' => $candidate['key'],
            ]
        );
    }

    private function confidence(array $evidence): int
    {
        $count = max(1, (int) ($evidence['metric_count'] ?? $evidence['experience_count'] ?? $evidence['stale_records'] ?? 1));
        return min(95, 50 + ($count * 10));
    }

    private function serialize(NexusLearningRecord $record): array
    {
        return [
            'id' => $record->id,
            'type' => $record->learning_type,
            'status' => $record->status,
            'pattern' => $record->pattern,
            'observation' => $record->observation,
            'evidence' => $record->evidence,
            'proposed_update' => $record->proposed_update,
            'confidence' => $record->confidence,
            'source' => ['type' => $record->source_type, 'id' => $record->source_id],
        ];
    }
}
