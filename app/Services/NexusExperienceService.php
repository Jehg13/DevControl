<?php

namespace App\Services;

use App\Models\NexusExperience;
use App\Models\NexusRun;
use Illuminate\Support\Collection;

class NexusExperienceService
{
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
