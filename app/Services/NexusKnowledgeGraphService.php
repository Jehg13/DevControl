<?php

namespace App\Services;

use App\Models\NexusKnowledgeClaim;
use App\Models\NexusKnowledgeEntity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class NexusKnowledgeGraphService
{
    private const TYPES = ['FACT', 'INFERENCE', 'HYPOTHESIS', 'UNKNOWN'];

    public function entity(
        string $type,
        string $key,
        string $label,
        ?int $projectId = null,
        array $metadata = [],
    ): NexusKnowledgeEntity {
        $type = $this->cleanPart($type);
        $key = trim($key);
        if ($type === '' || $key === '' || trim($label) === '') {
            throw new InvalidArgumentException('Una entidad requiere tipo, clave y etiqueta.');
        }

        $entity = NexusKnowledgeEntity::query()->firstOrNew([
            'entity_type' => $type,
            'entity_key' => $key,
        ]);
        $entity->fill([
            'proyecto_id' => $projectId ?? $entity->proyecto_id,
            'label' => trim($label),
            'metadata' => array_merge($entity->metadata ?? [], $metadata),
            'status' => 'active',
            'invalidated_at' => null,
        ]);
        $entity->save();

        return $entity;
    }

    public function fact(
        array $subject,
        string $predicate,
        mixed $value,
        string $epistemicType,
        string $sourceType,
        ?string $sourceId = null,
        ?int $projectId = null,
        int $confidence = 0,
        ?string $fingerprint = null,
        array $metadata = [],
    ): NexusKnowledgeClaim {
        $this->assertType($epistemicType);
        if (trim($predicate) === '') {
            throw new InvalidArgumentException('Una afirmación requiere una relación.');
        }

        return DB::transaction(function () use (
            $subject, $predicate, $value, $epistemicType, $sourceType,
            $sourceId, $projectId, $confidence, $fingerprint, $metadata
        ): NexusKnowledgeClaim {
            $subjectEntity = $this->entityFrom($subject, $projectId);
            $encodedValue = $this->encodeValue($value);
            $active = NexusKnowledgeClaim::query()
                ->where('subject_entity_id', $subjectEntity->id)
                ->where('predicate', trim($predicate))
                ->where('status', 'active')
                ->latest('version')
                ->first();

            if ($active && $active->object_value === $encodedValue
                && $active->epistemic_type === $epistemicType) {
                return $active;
            }

            $version = ($active?->version ?? 0) + 1;
            if ($active) {
                $active->update(['status' => 'superseded']);
            }

            return NexusKnowledgeClaim::create([
                'proyecto_id' => $projectId,
                'subject_entity_id' => $subjectEntity->id,
                'predicate' => trim($predicate),
                'object_value' => $encodedValue,
                'epistemic_type' => $epistemicType,
                'confidence' => max(0, min(100, $confidence)),
                'source_type' => trim($sourceType),
                'source_id' => $sourceId,
                'source_fingerprint' => $fingerprint,
                'version' => $version,
                'supersedes_id' => $active?->id,
                'metadata' => $this->sanitize($metadata),
            ]);
        });
    }

    public function relate(
        array $subject,
        string $predicate,
        array $object,
        string $epistemicType = 'FACT',
        string $sourceType = 'manual',
        ?string $sourceId = null,
        ?int $projectId = null,
        int $confidence = 0,
        ?string $fingerprint = null,
        array $metadata = [],
    ): NexusKnowledgeClaim {
        $this->assertType($epistemicType);

        return DB::transaction(function () use (
            $subject, $predicate, $object, $epistemicType, $sourceType,
            $sourceId, $projectId, $confidence, $fingerprint, $metadata
        ): NexusKnowledgeClaim {
            $subjectEntity = $this->entityFrom($subject, $projectId);
            $objectEntity = $this->entityFrom($object, $projectId);
            $active = NexusKnowledgeClaim::query()
                ->where('subject_entity_id', $subjectEntity->id)
                ->where('object_entity_id', $objectEntity->id)
                ->where('predicate', trim($predicate))
                ->where('status', 'active')
                ->latest('version')
                ->first();

            if ($active && $active->epistemic_type === $epistemicType) {
                return $active;
            }
            if ($active) {
                $active->update(['status' => 'superseded']);
            }

            return NexusKnowledgeClaim::create([
                'proyecto_id' => $projectId,
                'subject_entity_id' => $subjectEntity->id,
                'object_entity_id' => $objectEntity->id,
                'predicate' => trim($predicate),
                'epistemic_type' => $epistemicType,
                'confidence' => max(0, min(100, $confidence)),
                'source_type' => trim($sourceType),
                'source_id' => $sourceId,
                'source_fingerprint' => $fingerprint,
                'version' => ($active?->version ?? 0) + 1,
                'supersedes_id' => $active?->id,
                'metadata' => $this->sanitize($metadata),
            ]);
        });
    }

    public function search(string $query, ?int $projectId = null, int $limit = 20): array
    {
        $tokens = $this->tokens($query);
        if ($tokens === []) {
            return [];
        }

        $claims = NexusKnowledgeClaim::query()
            ->with(['subject', 'object'])
            ->where('status', 'active')
            ->when($projectId !== null, fn ($q) => $q->where(function ($inner) use ($projectId) {
                $inner->whereNull('proyecto_id')->orWhere('proyecto_id', $projectId);
            }))
            ->get()
            ->map(fn (NexusKnowledgeClaim $claim) => [
                'claim' => $claim,
                'score' => $this->overlap($tokens, $this->tokens($this->claimText($claim))),
            ])
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(max(1, $limit));

        return $claims->map(fn (array $item): array => $this->serializeClaim($item['claim'], $item['score']))
            ->values()
            ->all();
    }

    public function impact(string $entityType, string $entityKey, ?int $projectId = null): array
    {
        $entity = NexusKnowledgeEntity::query()
            ->where('entity_type', $entityType)
            ->where('entity_key', $entityKey)
            ->where('status', 'active')
            ->first();
        if (! $entity) {
            return [];
        }

        return NexusKnowledgeClaim::query()
            ->with(['subject', 'object'])
            ->where('status', 'active')
            ->where(function ($query) use ($entity) {
                $query->where('subject_entity_id', $entity->id)
                    ->orWhere('object_entity_id', $entity->id);
            })
            ->when($projectId !== null, fn ($q) => $q->where('proyecto_id', $projectId))
            ->get()
            ->map(fn (NexusKnowledgeClaim $claim): array => $this->serializeClaim($claim, 1))
            ->all();
    }

    public function invalidate(NexusKnowledgeClaim $claim, string $reason): NexusKnowledgeClaim
    {
        $claim->update([
            'status' => 'invalidated',
            'invalidated_at' => now(),
            'metadata' => array_merge($claim->metadata ?? [], ['invalidated_reason' => trim($reason)]),
        ]);

        return $claim->fresh();
    }

    public function invalidateSource(string $sourceType, string $fingerprint, string $reason): int
    {
        $claims = NexusKnowledgeClaim::query()
            ->where('source_type', $sourceType)
            ->where('source_fingerprint', $fingerprint)
            ->where('status', 'active')
            ->get();
        foreach ($claims as $claim) {
            $claim->update([
                'status' => 'invalidated',
                'invalidated_at' => now(),
                'metadata' => array_merge($claim->metadata ?? [], ['invalidated_reason' => trim($reason)]),
            ]);
        }
        return $claims->count();
    }

    public function indexUnderstanding(array $understanding, ?int $projectId, string $sourceId, ?string $fingerprint = null): int
    {
        $project = $this->entity(
            'project',
            'project:'.($projectId ?? ($understanding['name'] ?? 'unknown')),
            (string) ($understanding['name'] ?? 'Proyecto sin nombre'),
            $projectId
        );
        $count = 0;
        $count += $this->indexList($project, 'uses_technology', $understanding['evidence']['technologies']['detected'] ?? [], 'technology', 'name', $projectId, $sourceId, $fingerprint);
        $count += $this->indexList($project, 'contains_file', $understanding['evidence']['files'] ?? [], 'file', 'path', $projectId, $sourceId, $fingerprint);
        $count += $this->indexList($project, 'exposes_route', $understanding['routes'] ?? [], 'route', 'path', $projectId, $sourceId, $fingerprint);
        $count += $this->indexDependencies($project, $understanding['dependencies'] ?? [], $projectId, $sourceId, $fingerprint);
        foreach ($understanding['evidence']['symbols'] ?? [] as $symbol) {
            $file = $symbol['file'] ?? null;
            if (! is_string($file) || $file === '') {
                continue;
            }
            foreach (array_merge((array) ($symbol['types'] ?? []), (array) ($symbol['functions'] ?? [])) as $name) {
                if (! is_string($name) || $name === '') {
                    continue;
                }
                $this->relate(
                    ['type' => 'file', 'key' => 'file:'.$file, 'label' => $file],
                    'defines',
                    ['type' => in_array($name, (array) ($symbol['types'] ?? []), true) ? 'class' : 'function', 'key' => 'symbol:'.$name, 'label' => $name],
                    'FACT', 'project_understanding', $sourceId, $projectId, 90, $fingerprint
                );
                $count++;
            }
        }
        foreach ($understanding['relationships'] ?? [] as $relation) {
            if (! is_string($relation['from'] ?? null) || ! is_string($relation['to'] ?? null)) {
                continue;
            }
            $this->relate(
                ['type' => 'file', 'key' => 'file:'.$relation['from'], 'label' => $relation['from']],
                (string) ($relation['type'] ?? 'references'),
                ['type' => 'symbol', 'key' => 'symbol:'.$relation['to'], 'label' => $relation['to']],
                'FACT', 'project_understanding', $sourceId, $projectId, 85, $fingerprint
            );
            $count++;
        }
        foreach ($understanding['evidence']['route_bindings'] ?? [] as $binding) {
            if (! is_string($binding['route'] ?? null) || ! is_string($binding['controller'] ?? null)) {
                continue;
            }
            $this->relate(
                ['type' => 'route', 'key' => 'route:'.$binding['route'], 'label' => $binding['route']],
                'handled_by',
                ['type' => 'controller', 'key' => 'controller:'.$binding['controller'], 'label' => $binding['controller']],
                'FACT', 'project_understanding', $sourceId, $projectId, 90, $fingerprint
            );
            $count++;
        }
        return $count;
    }

    private function indexDependencies(
        NexusKnowledgeEntity $project,
        array $dependencies,
        ?int $projectId,
        string $sourceId,
        ?string $fingerprint,
    ): int {
        $count = 0;
        foreach ($dependencies as $manifest => $data) {
            foreach (array_merge((array) ($data['require'] ?? []), (array) ($data['require_dev'] ?? []), (array) ($data['dependencies'] ?? [])) as $name => $version) {
                $label = is_string($name) ? $name : (string) $version;
                if ($label === '') {
                    continue;
                }
                $this->relate(
                    ['type' => $project->entity_type, 'key' => $project->entity_key, 'label' => $project->label],
                    'has_dependency',
                    ['type' => 'dependency', 'key' => 'dependency:'.$label, 'label' => $label],
                    'FACT', 'project_understanding', $sourceId, $projectId, 90, $fingerprint,
                    ['manifest' => $manifest, 'constraint' => is_string($name) ? $version : null]
                );
                $count++;
            }
        }
        return $count;
    }

    private function indexList(
        NexusKnowledgeEntity $subject,
        string $predicate,
        array $items,
        string $type,
        string $key,
        ?int $projectId,
        string $sourceId,
        ?string $fingerprint,
    ): int {
        $count = 0;
        foreach ($items as $item) {
            $value = is_array($item) ? ($item[$key] ?? $item['name'] ?? $item['path'] ?? null) : $item;
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $this->relate(
                ['type' => $subject->entity_type, 'key' => $subject->entity_key, 'label' => $subject->label],
                $predicate,
                ['type' => $type, 'key' => $type.':'.$value, 'label' => $value],
                'FACT',
                'project_understanding',
                $sourceId,
                $projectId,
                90,
                $fingerprint
            );
            $count++;
        }
        return $count;
    }

    private function entityFrom(array $data, ?int $projectId): NexusKnowledgeEntity
    {
        return $this->entity(
            (string) ($data['type'] ?? ''),
            (string) ($data['key'] ?? ''),
            (string) ($data['label'] ?? $data['key'] ?? ''),
            $projectId,
            (array) ($data['metadata'] ?? [])
        );
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Tipo epistemológico no soportado: '.$type);
        }
    }

    private function encodeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return is_scalar($value) ? (string) $value : json_encode($this->sanitize($value), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function claimText(NexusKnowledgeClaim $claim): string
    {
        return implode(' ', array_filter([
            $claim->subject?->label,
            $claim->predicate,
            $claim->object?->label,
            $claim->object_value,
        ]));
    }

    private function serializeClaim(NexusKnowledgeClaim $claim, int|float $score): array
    {
        return [
            'id' => $claim->id,
            'subject' => ['type' => $claim->subject?->entity_type, 'key' => $claim->subject?->entity_key, 'label' => $claim->subject?->label],
            'predicate' => $claim->predicate,
            'object' => $claim->object
                ? ['type' => $claim->object->entity_type, 'key' => $claim->object->entity_key, 'label' => $claim->object->label]
                : $claim->object_value,
            'epistemic_type' => $claim->epistemic_type,
            'confidence' => $claim->confidence,
            'source' => ['type' => $claim->source_type, 'id' => $claim->source_id, 'fingerprint' => $claim->source_fingerprint],
            'version' => $claim->version,
            'score' => $score,
        ];
    }

    private function cleanPart(string $value): string
    {
        return strtolower(trim($value));
    }

    private function tokens(string $value): array
    {
        return array_values(array_filter(preg_split('/[^\p{L}\p{N}_]+/u', mb_strtolower($value)) ?: [], fn (string $token): bool => mb_strlen($token) > 2));
    }

    private function overlap(array $left, array $right): int
    {
        return count(array_intersect(array_unique($left), array_unique($right)));
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = preg_match('/(?:api[_-]?key|secret|password|token|authorization|private[_-]?key)/i', (string) $key)
                    ? '[redacted]' : $this->sanitize($item);
            }
            return $result;
        }
        return is_string($value) && preg_match('/(?:api[_-]?key|secret|password|token|authorization|private[_-]?key)\s*[:=]/i', $value)
            ? '[redacted]' : $value;
    }
}
