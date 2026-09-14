<?php

namespace App\Services;

use App\Models\Bug;
use App\Models\NexusDatasetExample;
use App\Models\NexusExperience;
use App\Models\NexusPlan;
use App\Models\NexusRun;
use App\Models\Tarea;
use Illuminate\Support\Collection;

final class NexusDatasetService
{
    private const SPLITS = ['training', 'validation', 'test'];

    public function generate(array $options = []): array
    {
        $examples = collect();
        $this->addExperiences($examples, $options);
        $this->addRuns($examples, $options);
        $this->addWorkItems($examples, $options);
        $this->addPlans($examples, $options);

        $stored = $examples
            ->map(fn (array $candidate): ?NexusDatasetExample => $this->store($candidate, $options))
            ->filter()
            ->values();

        return [
            'created' => $stored->count(),
            'examples' => $stored->map(fn (NexusDatasetExample $example): array => $this->serialize($example))->all(),
            'summary' => $this->summary($stored),
            'version' => (string) ($options['version'] ?? '1.0'),
        ];
    }

    public function ingest(array $record, string $sourceType, ?string $sourceId = null, array $options = []): ?NexusDatasetExample
    {
        return $this->store([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'project_id' => $record['project_id'] ?? $record['proyecto_id'] ?? null,
            'payload' => $record,
            'labels' => $record['labels'] ?? [],
        ], $options);
    }

    public function export(array $filters = []): string
    {
        $query = NexusDatasetExample::query()
            ->where('status', 'active')
            ->when($filters['split'] ?? null, fn ($q, $split) => $q->where('split', $split))
            ->when($filters['version'] ?? null, fn ($q, $version) => $q->where('version', $version))
            ->when($filters['minimum_quality'] ?? null, fn ($q, $score) => $q->where('quality_score', '>=', $score));

        return $query->get()
            ->map(fn (NexusDatasetExample $example): string => json_encode($this->serialize($example), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
            ->implode("\n");
    }

    private function addExperiences(Collection $examples, array $options): void
    {
        NexusExperience::query()->where('status', 'active')->get()->each(function (NexusExperience $experience) use ($examples): void {
            $examples->push($this->candidate(
                'experience',
                (string) $experience->id,
                $experience->proyecto_id,
                [
                    'PROBLEMA' => $experience->problem,
                    'CONTEXTO' => $experience->context,
                    'EVIDENCIA' => $experience->result,
                    'ANÁLISIS' => $experience->reflection,
                    'HIPÓTESIS' => $experience->hypothesis,
                    'DECISIÓN' => $experience->strategy,
                    'ACCIÓN' => $experience->action,
                    'RESULTADO' => $experience->result,
                    'SOLUCIÓN' => $experience->solution,
                    'VALIDACIÓN' => $experience->lesson,
                ],
                [
                    'technologies' => $experience->technologies,
                    'problem_type' => $experience->category,
                    'reasoning_type' => 'diagnostic',
                    'difficulty' => $this->difficulty($experience->errors),
                    'severity' => 'normal',
                ]
            ));
        });
    }

    private function addRuns(Collection $examples, array $options): void
    {
        NexusRun::query()->whereIn('status', ['completed', 'failed'])->get()->each(function (NexusRun $run) use ($examples): void {
            $examples->push($this->candidate(
                'run',
                (string) $run->id,
                $run->context['project_id'] ?? null,
                [
                    'PROBLEMA' => $run->message,
                    'CONTEXTO' => $run->context,
                    'EVIDENCIA' => $run->result,
                    'ANÁLISIS' => $run->reflection,
                    'HIPÓTESIS' => $run->internal_state['hypotheses'] ?? [],
                    'DECISIÓN' => $run->internal_state['next_action'] ?? null,
                    'ACCIÓN' => $run->internal_state['actions'] ?? [],
                    'RESULTADO' => $run->result,
                    'SOLUCIÓN' => $run->status === 'completed' ? $run->result : null,
                    'VALIDACIÓN' => $run->status,
                ],
                [
                    'problem_type' => 'execution',
                    'reasoning_type' => 'planning',
                    'difficulty' => $this->difficulty($run->error),
                    'severity' => $run->status === 'failed' ? 'high' : 'normal',
                ]
            ));
        });
    }

    private function addWorkItems(Collection $examples, array $options): void
    {
        Bug::query()->get()->each(function (Bug $bug) use ($examples): void {
            $examples->push($this->candidate('bug', (string) $bug->id, $bug->proyecto_id, [
                'PROBLEMA' => $bug->titulo,
                'CONTEXTO' => ['descripcion' => $bug->descripcion, 'estado' => $bug->estado],
                'EVIDENCIA' => ['folio' => $bug->folio],
                'ANÁLISIS' => null, 'HIPÓTESIS' => null, 'DECISIÓN' => null,
                'ACCIÓN' => null, 'RESULTADO' => null, 'SOLUCIÓN' => null,
                'VALIDACIÓN' => $bug->estado,
            ], ['problem_type' => 'bug', 'severity' => $bug->prioridad ?: 'normal', 'reasoning_type' => 'classification']));
        });

        Tarea::query()->get()->each(function (Tarea $task) use ($examples): void {
            $examples->push($this->candidate('task', (string) $task->id, $task->proyecto_id, [
                'PROBLEMA' => $task->titulo,
                'CONTEXTO' => ['descripcion' => $task->descripcion, 'estado' => $task->estado],
                'EVIDENCIA' => [], 'ANÁLISIS' => null, 'HIPÓTESIS' => null,
                'DECISIÓN' => null, 'ACCIÓN' => null, 'RESULTADO' => null,
                'SOLUCIÓN' => null, 'VALIDACIÓN' => $task->estado,
            ], ['problem_type' => 'task', 'severity' => $task->prioridad ?: 'normal', 'reasoning_type' => 'planning']));
        });
    }

    private function addPlans(Collection $examples, array $options): void
    {
        NexusPlan::query()->with('run')->get()->each(function (NexusPlan $plan) use ($examples): void {
            $examples->push($this->candidate('plan', (string) $plan->id, $plan->run?->context['project_id'] ?? null, [
                'PROBLEMA' => $plan->objective,
                'CONTEXTO' => $plan->metadata,
                'EVIDENCIA' => $plan->subtasks,
                'ANÁLISIS' => $plan->completion_criteria,
                'HIPÓTESIS' => null,
                'DECISIÓN' => $plan->priority,
                'ACCIÓN' => $plan->subtasks,
                'RESULTADO' => $plan->expected_result,
                'SOLUCIÓN' => null,
                'VALIDACIÓN' => $plan->status,
            ], ['problem_type' => 'planning', 'reasoning_type' => 'planning', 'difficulty' => 'normal', 'severity' => 'normal']));
        });
    }

    private function candidate(string $sourceType, string $sourceId, ?int $projectId, array $payload, array $labels): array
    {
        return [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'project_id' => $projectId,
            'payload' => $payload,
            'labels' => $labels,
        ];
    }

    private function store(array $candidate, array $options): ?NexusDatasetExample
    {
        $payload = $this->sanitize($candidate['payload'] ?? []);
        $payload = $this->normalize($payload);
        $quality = $this->quality($payload);
        $minimum = (int) ($options['minimum_quality'] ?? 60);
        if ($quality < $minimum || ! $this->valid($payload)) {
            return null;
        }

        $labels = $this->classify(array_merge($candidate['labels'] ?? [], $payload));
        $version = (string) ($options['version'] ?? '1.0');
        $canonical = json_encode(['payload' => $payload, 'labels' => $labels], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $canonical);
        $existing = NexusDatasetExample::query()->where('example_hash', $hash)->first();
        if ($existing) {
            return $existing;
        }

        $split = $this->split($hash, $options);
        return NexusDatasetExample::create([
            'example_hash' => $hash,
            'version' => $version,
            'split' => $split,
            'payload' => $payload,
            'labels' => $labels,
            'quality_score' => $quality,
            'status' => 'active',
            'source_type' => $candidate['source_type'],
            'source_id' => $candidate['source_id'] ?? null,
            'proyecto_id' => $candidate['project_id'] ?? null,
        ]);
    }

    private function valid(array $payload): bool
    {
        return $this->hasContent($payload['PROBLEMA'] ?? null)
            && collect(['CONTEXTO', 'EVIDENCIA', 'ANÁLISIS', 'HIPÓTESIS', 'DECISIÓN', 'ACCIÓN', 'RESULTADO', 'SOLUCIÓN', 'VALIDACIÓN'])
                ->contains(fn (string $key): bool => $this->hasContent($payload[$key] ?? null));
    }

    private function quality(array $payload): int
    {
        $keys = ['PROBLEMA', 'CONTEXTO', 'EVIDENCIA', 'ANÁLISIS', 'HIPÓTESIS', 'DECISIÓN', 'ACCIÓN', 'RESULTADO', 'SOLUCIÓN', 'VALIDACIÓN'];
        return (int) round(100 * collect($keys)->filter(fn (string $key): bool => $this->hasContent($payload[$key] ?? null))->count() / count($keys));
    }

    private function hasContent(mixed $value): bool
    {
        return $value !== null && $value !== [] && $value !== '' && (! is_string($value) || trim($value) !== '');
    }

    private function classify(array $labels): array
    {
        return [
            'language' => $labels['language'] ?? $labels['languages'] ?? null,
            'framework' => $labels['framework'] ?? null,
            'problem_type' => $labels['problem_type'] ?? 'general',
            'difficulty' => $labels['difficulty'] ?? 'normal',
            'severity' => $labels['severity'] ?? 'normal',
            'domain' => $labels['domain'] ?? 'software_engineering',
            'reasoning_type' => $labels['reasoning_type'] ?? 'analysis',
            'technologies' => array_values(array_filter((array) ($labels['technologies'] ?? []), 'is_string')),
        ];
    }

    private function split(string $hash, array $options): string
    {
        $number = hexdec(substr($hash, 0, 6)) % 100;
        $training = (int) ($options['training_percent'] ?? 80);
        $validation = $training + (int) ($options['validation_percent'] ?? 10);
        return $number < $training ? 'training' : ($number < $validation ? 'validation' : 'test');
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->normalize($item), $value);
        }
        if (is_string($value)) {
            return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        }
        return $value;
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $safe = [];
            foreach ($value as $key => $item) {
                $safe[$key] = preg_match('/(?:password|api[_-]?key|token|secret|credential|private[_-]?key|authorization)/i', (string) $key)
                    ? '[redacted]' : $this->sanitize($item);
            }
            return $safe;
        }
        if (is_string($value) && preg_match('/(?:-----BEGIN|(?:api[_-]?key|token|secret|password)\s*[:=])/i', $value)) {
            return '[redacted]';
        }
        return $value;
    }

    private function difficulty(mixed $value): string
    {
        return $value ? 'advanced' : 'normal';
    }

    private function serialize(NexusDatasetExample $example): array
    {
        return [
            'id' => $example->id,
            'version' => $example->version,
            'split' => $example->split,
            'quality_score' => $example->quality_score,
            'labels' => $example->labels,
            'payload' => $example->payload,
            'source' => ['type' => $example->source_type, 'id' => $example->source_id],
        ];
    }

    private function summary(Collection $examples): array
    {
        return [
            'by_split' => $examples->countBy('split')->all(),
            'by_source' => $examples->countBy('source_type')->all(),
            'average_quality' => round((float) $examples->avg('quality_score'), 2),
        ];
    }
}
