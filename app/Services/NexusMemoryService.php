<?php

namespace App\Services;

use App\Models\NexusConversation;
use App\Models\NexusMemory;
use App\Models\NexusMessage;
class NexusMemoryService
{
    private const ENGINEERING_TYPES = ['fact', 'experience', 'inference', 'validated_knowledge'];

    public function conversation(string $sessionKey, ?int $userId = null): NexusConversation
    {
        return NexusConversation::query()->firstOrCreate(
            ['session_key' => $sessionKey, 'usuario_id' => $userId],
            ['last_activity_at' => now()]
        );
    }

    public function recordMessage(
        NexusConversation $conversation,
        string $role,
        string $content,
        array $metadata = [],
    ): NexusMessage {
        $message = $conversation->messages()->create([
            'role' => $role,
            'content' => $content,
            'metadata' => $metadata,
        ]);

        $conversation->update(['last_activity_at' => now()]);

        if ($role === 'user') {
            $this->extractExplicitMemory($conversation, $content);
        }

        return $message;
    }

    /**
     * Promotes explicit technical statements from a conversation to candidate
     * engineering memory. Assistant prose is never treated as evidence.
     *
     * @return array<int, NexusMemory>
     */
    public function promoteInteraction(
        NexusConversation $conversation,
        string $userMessage,
        ?string $assistantMessage = null,
        array $context = [],
    ): array {
        if ($conversation->usuario_id === null) {
            return [];
        }

        $projectId = $context['project_id'] ?? $context['proyecto_id'] ?? null;
        $patterns = [
            'problem' => '/\b(?:problema encontrado|el problema es|falló|falla)\s*:?\s*(.{10,400})$/iu',
            'solution' => '/\b(?:solución|se resolvió|resuelto|la solución es)\s*:?\s*(.{10,400})$/iu',
            'decision' => '/\b(?:decidimos|decidí|hemos decidido|la decisión es)\s+(.{10,400})$/iu',
            'experience' => '/\b(?:aprendimos|experiencia|descubrimos que)\s*:?\s*(.{10,400})$/iu',
            'knowledge' => '/\b(?:es importante|ten presente|conocimiento importante)\s*:?\s*(.{10,400})$/iu',
        ];
        $memories = [];

        foreach ($patterns as $category => $pattern) {
            if (! preg_match($pattern, trim($userMessage), $matches)) {
                continue;
            }

            $memories[] = $this->recordEngineeringExperience([
                'key' => $category.':'.$this->memoryKey($matches[1]),
                'content' => trim($matches[1]),
                'type' => 'experience',
                'source' => 'interaction',
                'confidence' => 80,
                'importance' => 70,
                'metadata' => ['interaction_type' => $category],
                'evidence' => [],
            ], $conversation->usuario_id, $projectId, $conversation->session_key);
        }

        return $memories;
    }

    public function relevantContext(
        NexusConversation $conversation,
        string $query,
        array $temporaryContext = [],
    ): array {
        $tokens = $this->tokens($query.' '.json_encode($temporaryContext));
        $projectId = $temporaryContext['project_id'] ?? $temporaryContext['proyecto_id'] ?? null;
        $recentMessages = $conversation->messages()
            ->latest('id')
            ->limit((int) config('nexus.memory.recent_messages', 8))
            ->get()
            ->sortBy('id')
            ->values();

        $candidateMessages = $conversation->messages()
            ->latest('id')
            ->limit((int) config('nexus.memory.message_candidates', 80))
            ->get();

        $relevantMessages = $candidateMessages
            ->map(fn (NexusMessage $message) => [
                'message' => $message,
                'score' => $this->score($this->tokens($message->content), $tokens),
            ])
            ->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc(fn (array $item) => $item['score'])
            ->take((int) config('nexus.memory.relevant_messages', 6))
            ->pluck('message')
            ->sortBy('id')
            ->values();

        $memories = NexusMemory::query()
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($query) use ($conversation): void {
                $query->where('nexus_conversation_id', $conversation->id)
                    ->orWhere(function ($query) use ($conversation): void {
                        $query->whereNull('nexus_conversation_id')
                            ->where('usuario_id', $conversation->usuario_id);
                    });
            })
            ->when($projectId !== null, function ($query) use ($projectId): void {
                $query->where(function ($query) use ($projectId): void {
                    $query->whereNull('proyecto_id')->orWhere('proyecto_id', $projectId);
                });
            })
            ->when($projectId === null, fn ($query) => $query->whereNull('proyecto_id'))
            ->get()
            ->map(fn (NexusMemory $memory) => [
                'memory' => $memory,
                'score' => $this->score($this->tokens($memory->content.' '.$memory->memory_key), $tokens)
                    + ($memory->importance / 100),
            ])
            ->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc(fn (array $item) => $item['score'])
            ->take((int) config('nexus.memory.relevant_memories', 8))
            ->pluck('memory')
            ->values();

        if ($memories->isNotEmpty()) {
            NexusMemory::whereKey($memories->pluck('id'))->update(['last_used_at' => now()]);
        }

        return [
            'temporary' => $temporaryContext,
            'recent_messages' => $recentMessages->map(fn (NexusMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])->all(),
            'relevant_messages' => $relevantMessages->map(fn (NexusMessage $message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])->all(),
            'persistent_memories' => $memories->map(fn (NexusMemory $memory) => [
                'key' => $memory->memory_key,
                'content' => $memory->content,
                'type' => $memory->memory_type ?? 'experience',
                'importance' => $memory->importance,
                'confidence' => $memory->confidence ?? 50,
                'evidence' => $memory->evidence ?? [],
                'relationships' => $memory->relationships ?? [],
            ])->all(),
        ];
    }

    /**
     * Stores a validated technical experience without making it authoritative.
     *
     * @param array<string, mixed> $experience
     */
    public function recordEngineeringExperience(
        array $experience,
        ?int $userId,
        ?int $projectId = null,
        ?string $sessionKey = null,
    ): NexusMemory {
        if ($userId === null) {
            throw new \InvalidArgumentException('La memoria técnica requiere un usuario autenticado.');
        }
        $type = (string) ($experience['type'] ?? 'experience');
        if (! in_array($type, self::ENGINEERING_TYPES, true)) {
            throw new \InvalidArgumentException('El tipo de memoria técnica no es válido.');
        }
        if ($type === 'fact' && empty($experience['evidence'])) {
            throw new \InvalidArgumentException('Un hecho técnico requiere evidencia.');
        }
        if ($type === 'validated_knowledge' && ((int) ($experience['confidence'] ?? 0)) < 70) {
            throw new \InvalidArgumentException('El conocimiento validado requiere confianza mínima de 70.');
        }
        if (! is_string($experience['content'] ?? null) || trim($experience['content']) === '') {
            throw new \InvalidArgumentException('La memoria técnica requiere contenido.');
        }

        $conversation = $sessionKey !== null ? $this->conversation($sessionKey, $userId) : null;
        $content = trim(mb_substr($experience['content'], 0, 10000));
        $key = trim(mb_substr(
            (string) ($experience['key'] ?? $this->memoryKey($content)),
            0,
            120
        ));
        $attributes = [
            'nexus_conversation_id' => $conversation?->id,
            'content' => $content,
            'source' => (string) ($experience['source'] ?? 'engineering'),
            'memory_type' => $type,
            'importance' => max(0, min(100, (int) ($experience['importance'] ?? 60))),
            'confidence' => max(0, min(100, (int) ($experience['confidence'] ?? 60))),
            'metadata' => $experience['metadata'] ?? [],
            'evidence' => array_values($experience['evidence'] ?? []),
            'relationships' => array_values($experience['relationships'] ?? []),
            'expires_at' => $experience['expires_at']
                ?? (in_array($type, ['experience', 'inference'], true)
                    ? now()->addDays((int) config('nexus.memory.engineering_memory_ttl_days', 180))
                    : null),
            'access_scope' => $projectId !== null ? 'project' : 'user',
            'last_used_at' => null,
        ];

        $memory = NexusMemory::query()
            ->where('usuario_id', $userId)
            ->where('proyecto_id', $projectId)
            ->where('memory_type', $type)
            ->where('memory_key', $key)
            ->first();

        if ($memory === null) {
            return NexusMemory::create($attributes + [
                'usuario_id' => $userId,
                'proyecto_id' => $projectId,
                'memory_key' => $key,
            ]);
        }

        if ($this->isStale($memory, $attributes)) {
            return $memory;
        }

        $memory->update($attributes);
        return $memory->refresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function retrieveEngineeringMemory(
        string $query,
        ?int $userId,
        ?int $projectId = null,
        int $limit = 8,
    ): array {
        $tokens = $this->tokens($query);
        if ($tokens === []) {
            return [];
        }

        $memories = NexusMemory::query()
            ->where('usuario_id', $userId)
            ->whereIn('memory_type', self::ENGINEERING_TYPES)
            ->where(function ($builder) use ($projectId): void {
                if ($projectId === null) {
                    $builder->whereNull('proyecto_id');
                } else {
                    $builder->where('proyecto_id', $projectId);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get()
            ->map(fn (NexusMemory $memory): array => [
                'memory' => $memory,
                'score' => $this->semanticScore($memory, $tokens),
            ])
            ->filter(fn (array $item): bool => $item['score'] > 0)
            ->sortByDesc('score')
            ->take(max(1, min($limit, (int) config('nexus.memory.engineering_memory_limit', 50))));

        $selected = $memories->pluck('memory')->values();
        if ($selected->isNotEmpty()) {
            NexusMemory::whereKey($selected->pluck('id'))->update(['last_used_at' => now()]);
        }

        return $memories->map(fn (array $item): array => [
            'id' => $item['memory']->id,
            'key' => $item['memory']->memory_key,
            'content' => $item['memory']->content,
            'type' => $item['memory']->memory_type,
            'score' => round($item['score'], 4),
            'confidence' => $item['memory']->confidence,
            'evidence' => $item['memory']->evidence ?? [],
            'relationships' => $item['memory']->relationships ?? [],
            'project_id' => $item['memory']->proyecto_id,
        ])->values()->all();
    }

    public function purgeExpiredEngineeringMemory(?int $userId = null, ?int $projectId = null): int
    {
        return NexusMemory::query()
            ->whereIn('memory_type', self::ENGINEERING_TYPES)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->when($userId !== null, fn ($query) => $query->where('usuario_id', $userId))
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->delete();
    }

    public function forget(NexusMemory $memory, ?int $userId): void
    {
        if ($userId === null || $memory->usuario_id !== $userId) {
            abort(403, 'No tienes permiso para eliminar este recuerdo.');
        }

        $memory->delete();
    }

    public function clearConversation(NexusConversation $conversation): void
    {
        $conversation->messages()->delete();
        $conversation->update(['last_activity_at' => now()]);
    }

    private function extractExplicitMemory(NexusConversation $conversation, string $content): void
    {
        if (! preg_match('/^\s*(?:recuerda|recuerdame|ten presente)\s+(?:que\s+)?(.{10,500})$/iu', trim($content), $matches)) {
            return;
        }

        $memory = trim($matches[1]);
        $key = mb_substr(preg_replace('/\s+/u', ' ', mb_strtolower($memory, 'UTF-8')), 0, 120);

        NexusMemory::updateOrCreate(
            [
                'usuario_id' => $conversation->usuario_id,
                'memory_key' => $key,
            ],
            [
                'nexus_conversation_id' => $conversation->id,
                'content' => $memory,
                'source' => 'explicit_user',
                'importance' => 90,
                'memory_type' => 'experience',
                'confidence' => 70,
                'access_scope' => 'user',
            ]
        );
    }

    private function memoryKey(string $content): string
    {
        return mb_substr(preg_replace('/\s+/u', ' ', mb_strtolower($content, 'UTF-8')) ?: $content, 0, 120);
    }

    private function isStale(NexusMemory $memory, array $attributes): bool
    {
        $incomingConfidence = (int) ($attributes['confidence'] ?? 0);
        return $memory->memory_type === 'fact'
            && (int) $memory->confidence > $incomingConfidence
            && count($attributes['evidence'] ?? []) < count($memory->evidence ?? []);
    }

    private function semanticScore(NexusMemory $memory, array $tokens): float
    {
        $candidate = $this->tokens($memory->content.' '.$memory->memory_key);
        $overlap = count(array_intersect($candidate, $tokens));
        $importance = ((int) $memory->importance) / 100;
        $confidence = ((int) ($memory->confidence ?? 50)) / 100;
        $recency = $memory->last_used_at?->diffInDays(now()) ?? 30;
        return $overlap + $importance * 0.25 + $confidence * 0.25 + (1 / (1 + $recency)) * 0.1;
    }

    private function tokens(string $value): array
    {
        $normalized = mb_strtolower($value, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalized) ?? $normalized;
        $tokens = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = ['para', 'como', 'esta', 'este', 'eso', 'desde', 'sobre', 'tiene', 'quiero', 'puede', 'con'];

        return array_values(array_unique(array_filter($tokens, fn (string $token) => mb_strlen($token, 'UTF-8') > 2 && ! in_array($token, $stopWords, true))));
    }

    private function score(array $candidate, array $query): int
    {
        return count(array_intersect($candidate, $query));
    }
}
