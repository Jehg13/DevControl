<?php

namespace App\Nexus;

final class NexusModelRequest
{
    public function __construct(
        public readonly string $identity,
        public readonly string $message,
        public readonly array $context,
        public readonly array $tools,
        public readonly array $history = [],
        public readonly array $toolResults = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'identity' => $this->identity,
            'message' => $this->message,
            'context' => $this->context,
            'tools' => $this->tools,
            'history' => $this->history,
            'tool_results' => $this->toolResults,
        ];
    }
}
