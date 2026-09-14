<?php

namespace App\Services;

use App\Models\NexusConversation;
use App\Models\NexusMemory;
use App\Models\NexusMessage;
class NexusMemoryService
{
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
                'content' => $memory->content,
                'importance' => $memory->importance,
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
            ]
        );
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
