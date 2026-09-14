<?php

namespace App\Nexus;

final class NexusRuntimeResponse
{
    public function __construct(
        public readonly string $finalMessage,
        public readonly string $intent = 'general',
        public readonly array $evidence = [],
        public readonly array $toolsUsed = [],
        public readonly string $certainty = 'INSUFICIENTE',
        public readonly array $missingInformation = [],
        public readonly array $errors = [],
        public readonly array $actions = [],
        public readonly array $toolResults = [],
        public readonly array $investigation = [],
        public readonly array $hypotheses = [],
        public readonly array $impact = [],
        public readonly array $behavior = [],
        public readonly array $diagnosis = [],
        public readonly array $solution = [],
        public readonly array $verification = [],
        public readonly string $status = 'completed',
        public readonly string $source = 'runtime',
        public readonly ?string $navigation = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'final_message' => $this->finalMessage,
            'intent' => $this->intent,
            'evidence' => $this->evidence,
            'tools_used' => $this->toolsUsed,
            'certainty' => $this->certainty,
            'missing_information' => $this->missingInformation,
            'errors' => $this->errors,
            'actions' => $this->actions,
            'tool_results' => $this->toolResults,
            'investigation' => $this->investigation,
            'hypotheses' => $this->hypotheses,
            'impact' => $this->impact,
            'behavior' => $this->behavior,
            'diagnosis' => $this->diagnosis,
            'solution' => $this->solution,
            'verification' => $this->verification,
            'status' => $this->status,
            'source' => $this->source,
            'navigation' => $this->navigation,
        ];
    }
}
