<?php

namespace App\Nexus;

final class NexusModelCapabilities
{
    public function __construct(
        public readonly bool $textGeneration = false,
        public readonly bool $structuredOutput = false,
        public readonly bool $toolCalling = false,
        public readonly bool $embeddings = false,
        public readonly bool $local = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'text_generation' => $this->textGeneration,
            'structured_output' => $this->structuredOutput,
            'tool_calling' => $this->toolCalling,
            'embeddings' => $this->embeddings,
            'local' => $this->local,
        ];
    }
}
