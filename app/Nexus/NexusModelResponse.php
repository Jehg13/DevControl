<?php

namespace App\Nexus;

final class NexusModelResponse
{
    public function __construct(
        public readonly string $intent,
        public readonly string $message,
        public readonly array $toolCalls = [],
        public readonly bool $requiresConfirmation = false,
        public readonly array $metadata = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'intent' => $this->intent,
            'message' => $this->message,
            'tool_calls' => $this->toolCalls,
            'requires_confirmation' => $this->requiresConfirmation,
            'metadata' => $this->metadata,
        ];
    }
}
