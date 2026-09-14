<?php

namespace App\Services;

use App\Models\NexusExperience;
use App\Models\NexusLearningRecord;
use App\Models\NexusRun;
use Illuminate\Support\Collection;

class NexusExperienceService
{
    public function __construct(
        private readonly NexusKnowledgeGraphService $knowledge,
    ) {
    }

    public function evaluateOutcome(
        NexusExperience $experience,
        bool $success,
        string $result,
        array $errors = [],
        array $contradictions = [],
    ): NexusExperience {
        $status = $contradictions !== []
            ? 'contradictory'
            : ($success ? 'evaluated_success' : 'evaluated_failure');

        $experience->update([
            'status' => $status,
            'result' => array_merge($experience->result ?? [], [
                'outcome' => $result,
                'success' => $success,
            ]),
            'errors' => array_values(array_filter(array_merge($experience->errors ?? [], $errors))),
            'metadata' => array_merge($experience->metadata ?? [], [
                'evaluation' => [
                    'status' => $status,
                    'success' => $success,
                    'contradictions' => $contradictions,
                    'evaluated_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        return $experience->fresh();
    }

    public function createLearningCandidate(NexusExperience $experience): ?NexusLearningRecord
    {
        $evaluation = data_get($experience->metadata, 'evaluation', []);
        if (! in_array($evaluation['status'] ?? null, ['evaluated_success', 'evaluated_failure'], true)) {
            return null;
        }

        return NexusLearningRecord::create([
            'learning_key' => 'experience:'.$experience->id,
            'learning_type' => 'experience',
            'status' => 'observed',
            'pattern' => $evaluation['status'],
            'observation' => [
                'problem' => $experience->problem,
                'solution' => $experience->solution,
                'lesson' => $experience->lesson,
            ],
            'evidence' => [
                'experience_id' => $experience->id,
                'context' => $experience->context,
                'tools_used' => $experience->tools_used,
                'result' => $experience->result,
                'errors' => $experience->errors,
            ],
            'proposed_update' => [
                'lesson' => $experience->lesson,
                'strategy' => $experience->strategy,
            ],
            'confidence' => $experience->confidence,
            'proyecto_id' => $experience->proyecto_id,
            'source_type' => 'experience',
            'source_id' => (string) $experience->id,
        ]);
    }

    public function validateLearningCandidate(NexusLearningRecord $candidate, int $confidence = 80): NexusLearningRecord
    {
        if ($candidate->learning_type !== 'experience' || $candidate->status !== 'observed') {
            throw new \InvalidArgumentException('Solo se pueden validar candidatos de experiencia observados.');
        }

        $candidate->update([
            'status' => 'validated',
            'confidence' => max(0, min(100, $confidence)),
            'validated_at' => now(),
        ]);

        return $candidate->fresh();
    }

    public function promoteValidatedLearning(NexusLearningRecord $candidate): ?array
    {
        if ($candidate->learning_type !== 'experience' || $candidate->status !== 'validated') {
            return null;
        }

        $lesson = (string) data_get($candidate->proposed_update, 'lesson');
        $claim = $this->knowledge->fact(
            ['type' => 'experience', 'key' => 'experience:'.$candidate->source_id, 'label' => 'Experience '.$candidate->source_id],
            'validated_lesson',
            $lesson,
            'FACT',
            'experience',
            (string) $candidate->source_id,
            $candidate->proyecto_id,
            $candidate->confidence,
            null,
            ['learning_record_id' => $candidate->id],
        );

        return [
            'status' => 'knowledge_candidate',
            'validated' => true,
            'project_id' => $candidate->proyecto_id,
            'source' => [
                'type' => $candidate->source_type,
                'id' => $candidate->source_id,
            ],
            'evidence' => $candidate->evidence,
            'lesson' => data_get($candidate->proposed_update, 'lesson'),
            'confidence' => $candidate->confidence,
            'knowledge_claim_id' => $claim->id,
        ];
    }
    public function remember(array $experience, ?int $userId = null, ?int $runId = null): ?NexusExperience
    {
        $problem = $this->clean((string) ($experience['problem'] ?? ''));
        $confidence = $this->bounded($experience['confidence'] ?? 0);
        $relevance = $this->bounded($experience['relevance'] ?? 0);

        if ($problem === ''
            || $confidence < (int) config('nexus.experiences.minimum_confidence', 70)
            || $relevance < (int) config('nexus.experiences.minimum_relevance', 60)
            || $this->containsSensitiveData(json_encode($experience, JSON_UNESCAPED_UNICODE) ?: '')) {
            return null;
        }

        $projectId = $experience['project_id'] ?? $experience['proyecto_id'] ?? null;
        $existing = NexusExperience::query()
            ->where('status', 'active')
            ->where('problem', $problem)
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->first();

        $data = [
            'usuario_id' => $userId,
            'proyecto_id' => $projectId,
            'nexus_run_id' => $runId,
            'problem' => $problem,
            'context' => $this->sanitize($experience['context'] ?? []),
            'hypothesis' => $this->clean($experience['hypothesis'] ?? ''),
            'action' => $this->clean($experience['action'] ?? ''),
            'result' => $this->sanitize($experience['result'] ?? []),
            'solution' => $this->clean($experience['solution'] ?? ''),
            'lesson' => $this->clean($experience['lesson'] ?? ''),
            'errors' => $this->sanitize($experience['errors'] ?? []),
            'strategy' => $this->clean($experience['strategy'] ?? ''),
            'tools_used' => array_values(array_filter((array) ($experience['tools_used'] ?? []), 'is_string')),
            'reflection' => $this->sanitize($experience['reflection'] ?? []),
            'technologies' => array_values(array_filter((array) ($experience['technologies'] ?? []), 'is_string')),
            'category' => $this->clean($experience['category'] ?? 'general') ?: 'general',
            'relevance' => $relevance,
            'confidence' => $confidence,
            'status' => 'active',
            'metadata' => $this->sanitize($experience['metadata'] ?? []),
        ];

        if ($existing) {
            $existing->update([
                'relevance' => max($existing->relevance, $relevance),
                'confidence' => min(100, max($existing->confidence, $confidence)),
                'metadata' => array_merge($existing->metadata ?? [], $data['metadata']),
            ]);

            return $existing->fresh();
        }

        return NexusExperience::create($data);
    }

    public function similar(
        string $query,
        ?int $projectId = null,
        array $technologies = [],
        int $limit = 5,
    ): array {
        $tokens = $this->tokens($query.' '.implode(' ', $technologies));

        return NexusExperience::query()
            ->where('status', 'active')
            ->when($projectId !== null, fn ($queryBuilder) => $queryBuilder->where(function ($builder) use ($projectId) {
                $builder->whereNull('proyecto_id')->orWhere('proyecto_id', $projectId);
            }))
            ->get()
            ->map(fn (NexusExperience $experience) => [
                'experience' => $experience,
                'score' => $this->score($this->tokens($experience->problem.' '.$experience->solution.' '.$experience->lesson.' '.implode(' ', $experience->technologies ?? [])), $tokens)
                    + ($experience->relevance / 100),
            ])
            ->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(max(1, $limit))
            ->map(fn (array $item) => [
                'id' => $item['experience']->id,
                'problem' => $item['experience']->problem,
                'solution' => $item['experience']->solution,
                'lesson' => $item['experience']->lesson,
                'strategy' => $item['experience']->strategy,
                'result' => $item['experience']->result,
                'confidence' => $item['experience']->confidence,
                'relevance' => $item['experience']->relevance,
                'status' => $item['experience']->status,
                'score' => round($item['score'], 3),
            ])
            ->values()
            ->all();
    }

    public function correct(NexusExperience $experience, string $correction, int $confidence = 80): NexusExperience
    {
        $experience->update([
            'status' => 'corrected',
            'correction' => $this->clean($correction),
            'confidence' => $this->bounded($confidence),
        ]);

        return $experience->fresh();
    }

    public function invalidate(NexusExperience $experience, string $reason): NexusExperience
    {
        $experience->update([
            'status' => 'invalidated',
            'correction' => $this->clean($reason),
        ]);

        return $experience->fresh();
    }

    public function fromReflection(NexusRun $run, array $reflection): ?NexusExperience
    {
        $candidate = $reflection['experience_candidate'] ?? null;
        if (! is_array($candidate)) {
            return null;
        }

        return $this->remember(array_merge($candidate, [
            'project_id' => $run->context['project_id'] ?? $run->context['proyecto_id'] ?? null,
            'nexus_run_id' => $run->id,
            'tools_used' => $reflection['tools_used'] ?? [],
            'reflection' => $reflection,
        ]), $run->usuario_id, $run->id);
    }

    private function tokens(string $value): array
    {
        return array_values(array_filter(preg_split('/[^\p{L}\p{N}_]+/u', mb_strtolower($value)) ?: [], fn ($token) => mb_strlen($token) > 2));
    }

    private function score(array $left, array $right): float
    {
        if ($left === [] || $right === []) {
            return 0.0;
        }

        return count(array_intersect(array_unique($left), array_unique($right))) / max(1, count(array_unique($right)));
    }

    private function bounded(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    private function clean(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = preg_match('/(?:api[_-]?key|secret|password|token|authorization|private[_-]?key)/i', (string) $key) === 1
                    ? '[redacted]'
                    : $this->sanitize($item);
            }

            return $sanitized;
        }
        if (is_string($value) && $this->containsSensitiveData($value)) {
            return '[redacted]';
        }

        return $value;
    }

    private function containsSensitiveData(string $value): bool
    {
        return preg_match('/(?:api[_-]?key|secret|password|token|authorization|private[_-]?key)\s*[:=]/i', $value) === 1;
    }
}
