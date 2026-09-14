<?php

namespace App\Services;

use App\Models\NexusConversation;
use App\Models\NexusMemory;
use App\Models\NexusMessage;
class NexusMemoryService
{
    private const TECHNICAL_TYPES = [
        'architecture', 'relation', 'flow', 'decision',
        'diagnosis', 'solution', 'feature',
    ];

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
            $this->extractExplicitMemory($conversation, $content, $metadata['project_id'] ?? null);
        }

        return $message;
    }

    /**
     * Stores only a classified, sufficiently reliable long-term memory.
     */
    public function remember(
        NexusConversation $conversation,
        string $type,
        string $content,
        int $confidence,
        array $metadata = [],
    ): ?NexusMemory {
        $allowedTypes = [
            'project', 'decision', 'experience', 'problem',
            'solution', 'preference', 'knowledge',
        ];
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);
        $confidence = max(0, min(100, $confidence));

        if (! in_array($type, $allowedTypes, true)
            || $content === ''
            || $confidence < (int) config('nexus.memory.automatic_min_confidence', 75)
            || $this->containsSensitiveData($content)) {
            return null;
        }

        $projectId = $metadata['project_id'] ?? null;
        $key = $this->memoryKey($type, $content);
        $existing = NexusMemory::query()
            ->where('usuario_id', $conversation->usuario_id)
            ->where('memory_type', $type)
            ->where(function ($query): void {
                $query->where('status', 'active')
                    ->orWhere(function ($technical) {
                        $technical->where('status', 'validated')
                            ->whereIn('memory_type', self::TECHNICAL_TYPES);
                    });
            })
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->get()
            ->first(fn (NexusMemory $memory) => $this->similarity($memory->content, $content)
                >= (float) config('nexus.memory.deduplication_threshold', 0.75));

        if ($existing) {
            $existing->update([
                'confidence' => max($existing->confidence, $confidence),
                'importance' => max($existing->importance, (int) ($metadata['importance'] ?? 60)),
                'last_confirmed_at' => now(),
                'metadata' => array_merge($existing->metadata ?? [], $metadata),
            ]);

            return $existing->fresh();
        }

        return NexusMemory::create([
            'usuario_id' => $conversation->usuario_id,
            'nexus_conversation_id' => $conversation->id,
            'proyecto_id' => $projectId,
            'memory_key' => $key,
            'memory_type' => $type,
            'content' => $content,
            'source' => $metadata['source'] ?? 'conversation',
            'importance' => max(1, min(100, (int) ($metadata['importance'] ?? 60))),
            'confidence' => $confidence,
            'status' => 'active',
            'metadata' => $metadata,
            'last_confirmed_at' => now(),
        ]);
    }

    /**
     * Creates technical knowledge only when its origin and evidence are explicit.
     * Candidates are retained for review but are never treated as validated knowledge.
     */
    public function createTechnicalMemory(
        ?NexusConversation $conversation,
        string $type,
        string $content,
        ?int $projectId,
        string $origin,
        array $evidence,
        int $confidence = 0,
        string $status = 'candidate',
        array $metadata = [],
    ): ?NexusMemory {
        if (! in_array($type, self::TECHNICAL_TYPES, true)
            || ! in_array($status, ['candidate', 'validated', 'obsolete', 'invalidated'], true)
            || trim($content) === ''
            || trim($origin) === ''
            || $evidence === []
            || $this->containsSensitiveData($content)) {
            return null;
        }

        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);
        $confidence = max(0, min(100, $confidence));
        $key = $this->memoryKey($type, $content);

        return NexusMemory::create([
            'usuario_id' => $conversation?->usuario_id,
            'nexus_conversation_id' => $conversation?->id,
            'proyecto_id' => $projectId,
            'memory_type' => $type,
            'memory_key' => $key,
            'content' => $content,
            'source' => $origin,
            'importance' => max(1, min(100, (int) ($metadata['importance'] ?? 70))),
            'confidence' => $confidence,
            'status' => $status,
            'metadata' => array_merge($metadata, [
                'origin' => $origin,
                'evidence' => array_values($evidence),
                'validation_state' => $status,
            ]),
            'last_confirmed_at' => $status === 'validated' ? now() : null,
            'validated_at' => $status === 'validated' ? now() : null,
        ]);
    }

    public function validateTechnicalMemory(NexusMemory $memory, string $reason = ''): NexusMemory
    {
        $this->assertTechnicalMemory($memory);
        $memory->update([
            'status' => 'validated',
            'confidence' => max($memory->confidence, 75),
            'metadata' => array_merge($memory->metadata ?? [], [
                'validation_reason' => trim($reason),
                'validation_state' => 'validated',
            ]),
            'last_confirmed_at' => now(),
            'validated_at' => now(),
        ]);

        return $memory->fresh();
    }

    public function retrieveTechnicalMemory(?int $projectId = null, ?string $type = null)
    {
        return NexusMemory::query()
            ->where('status', 'validated')
            ->whereIn('memory_type', self::TECHNICAL_TYPES)
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->when($type !== null, fn ($query) => $query->where('memory_type', $type))
            ->latest('validated_at')
            ->get();
    }

    public function invalidateTechnicalMemory(NexusMemory $memory, string $reason): NexusMemory
    {
        $this->assertTechnicalMemory($memory);
        $memory->update([
            'status' => 'invalidated',
            'metadata' => array_merge($memory->metadata ?? [], [
                'invalidated_reason' => trim($reason),
                'validation_state' => 'invalidated',
            ]),
        ]);

        return $memory->fresh();
    }

    private function assertTechnicalMemory(NexusMemory $memory): void
    {
        if (! in_array($memory->memory_type, self::TECHNICAL_TYPES, true)) {
            throw new \InvalidArgumentException('La memoria indicada no es memoria técnica.');
        }
    }

    public function promoteInteraction(
        NexusConversation $conversation,
        string $userMessage,
        ?string $assistantMessage = null,
        array $context = [],
    ): array {
        $candidates = [];
        $projectId = $context['project_id'] ?? $context['proyecto_id'] ?? null;
        // Assistant prose is not treated as authoritative technical knowledge.
        $text = trim($userMessage);

        foreach ([
            'project' => '/\b(el proyecto|este proyecto|proyecto actual|proyecto se llama)\s*:?\s*(.{10,400})$/iu',
            'decision' => '/\b(decidimos|decidí|hemos decidido|la decisión es)\s+(.{10,400})$/iu',
            'problem' => '/\b(problema encontrado|el problema es|falló|falla)\s*:?\s*(.{10,400})$/iu',
            'solution' => '/\b(solución|se resolvió|resuelto|la solución es)\s*:?\s*(.{10,400})$/iu',
            'preference' => '/\b(prefiero|preferencia|trabajo mejor|no quiero)\s+(.{10,300})$/iu',
            'knowledge' => '/\b(es importante|ten presente|conocimiento importante)\s*:?\s*(.{10,400})$/iu',
            'experience' => '/\b(aprendimos|experiencia|descubrimos que)\s*:?\s*(.{10,400})$/iu',
        ] as $type => $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $memory = $this->remember($conversation, $type, trim($matches[2]), 80, [
                    'project_id' => $projectId,
                    'source' => 'interaction',
                    'importance' => 70,
                ]);
                if ($memory) {
                    $candidates[] = $memory;
                }
            }
        }

        return $candidates;
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
            ->where(function ($query) use ($conversation): void {
                $query->where('nexus_conversation_id', $conversation->id)
                    ->orWhere(function ($query) use ($conversation): void {
                        $query->whereNull('nexus_conversation_id')
                            ->where('usuario_id', $conversation->usuario_id);
                    });
            })
            ->where(function ($query): void {
                $query->where('status', 'active')
                    ->orWhere(function ($technical) {
                        $technical->where('status', 'validated')
                            ->whereIn('memory_type', self::TECHNICAL_TYPES);
                    });
            })
            ->when($projectId !== null, function ($query) use ($projectId): void {
                $query->where(function ($query) use ($projectId): void {
                    $query->whereNull('proyecto_id')->orWhere('proyecto_id', $projectId);
                });
            })
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
                'type' => $memory->memory_type,
                'content' => $memory->content,
                'importance' => $memory->importance,
                'confidence' => $memory->confidence,
            ])->all(),
        ];
    }

    public function forget(NexusMemory $memory, ?int $userId): void
    {
        if ($memory->usuario_id !== $userId) {
            abort(403, 'No tienes permiso para eliminar este recuerdo.');
        }

        $memory->delete();
    }

    public function clearConversation(NexusConversation $conversation): void
    {
        $conversation->messages()->delete();
        $conversation->update(['last_activity_at' => now()]);
    }

    private function extractExplicitMemory(NexusConversation $conversation, string $content, ?int $projectId = null): void
    {
        if (! preg_match('/^\s*(?:recuerda|recuerdame|ten presente)\s+(?:que\s+)?(.{10,500})$/iu', trim($content), $matches)) {
            return;
        }

        $memory = trim($matches[1]);
        $this->remember($conversation, 'knowledge', $memory, 100, [
            'project_id' => $projectId,
            'source' => 'explicit_user',
            'importance' => 90,
        ]);
    }

    private function memoryKey(string $type, string $content): string
    {
        return mb_substr($type.'-'.preg_replace('/\s+/u', '-', mb_strtolower($content, 'UTF-8')), 0, 120);
    }

    private function similarity(string $left, string $right): float
    {
        $leftTokens = $this->tokens($left);
        $rightTokens = $this->tokens($right);
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));

        return $union === 0 ? 0.0 : count(array_intersect($leftTokens, $rightTokens)) / $union;
    }

    private function containsSensitiveData(string $content): bool
    {
        return preg_match('/\b(password|contraseña|token|api[_ -]?key|secret|credencial)\b\s*[:=]/iu', $content) === 1;
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
