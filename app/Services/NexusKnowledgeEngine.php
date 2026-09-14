<?php

namespace App\Services;

final class NexusKnowledgeEngine
{
    public function __construct(
        private readonly NexusKnowledgeGraphService $graph,
    ) {
    }

    public function search(array $context, string $query): array
    {
        $items = [];
        foreach ($this->graph->search(
            $query,
            $context['project_id'] ?? $context['proyecto_id'] ?? null
        ) as $claim) {
            $items[] = [
                'type' => 'knowledge',
                'content' => $claim,
                'source' => 'knowledge_graph',
            ];
        }

        foreach ((array) ($context['knowledge'] ?? []) as $item) {
            if (is_string($item)) {
                $items[] = ['type' => 'knowledge', 'content' => $item, 'source' => 'context'];
            } elseif (is_array($item)) {
                $items[] = array_merge(['type' => 'knowledge', 'source' => 'context'], $item);
            }
        }

        $tokens = $this->tokens($query);
        foreach ((array) ($context['known_information'] ?? []) as $item) {
            $content = json_encode($item, JSON_UNESCAPED_UNICODE) ?: '';
            if ($this->overlap($tokens, $this->tokens($content)) > 0) {
                $items[] = ['type' => 'observed', 'content' => $item, 'source' => 'context'];
            }
        }

        return array_values(array_slice($items, 0, 20));
    }

    public function indexUnderstanding(
        array $understanding,
        ?int $projectId,
        string $sourceId,
        ?string $fingerprint = null,
    ): int {
        return $this->graph->indexUnderstanding($understanding, $projectId, $sourceId, $fingerprint);
    }

    public function facts(array $context, string $query): array
    {
        return array_values(array_filter(
            $this->search($context, $query),
            fn (array $item): bool => ($item['type'] ?? '') === 'fact'
        ));
    }

    private function tokens(string $value): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}_]+/u', mb_strtolower($value)) ?: [],
            fn (string $token): bool => mb_strlen($token) > 2
        ));
    }

    private function overlap(array $left, array $right): int
    {
        return count(array_intersect(array_unique($left), array_unique($right)));
    }
}
