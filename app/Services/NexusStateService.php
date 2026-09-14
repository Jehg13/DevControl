<?php

namespace App\Services;

use App\Models\NexusRun;
use App\Nexus\NexusToolRegistry;

class NexusStateService
{
    public function __construct(
        private readonly NexusToolRegistry $tools,
    ) {
    }

    public function initialize(string $message, array $context = []): array
    {
        return [
            'current_goal' => $message,
            'current_task' => null,
            'current_context' => $context,
            'last_action' => null,
            'last_action_result' => null,
            'available_tools' => array_values(array_map(
                fn (array $definition) => $definition['name'],
                $this->tools->definitions()
            )),
            'capabilities' => [
                'interpret natural language',
                'execute registered tools',
                'chain bounded tool calls',
                'retrieve relevant conversation memory',
            ],
            'limitations' => [
                'bounded execution steps',
                'confirmation required for sensitive tools',
                'no access to unregistered tools',
                'uncertain information is not treated as fact',
            ],
            'known_information' => $this->knownInformation($context),
            'unknown_information' => [],
            'confidence' => 0,
            'next_action' => 'interpret_request',
            'updated_at' => now()->toISOString(),
        ];
    }

    public function persist(NexusRun $run, array $state): void
    {
        $state['updated_at'] = now()->toISOString();
        $run->update(['internal_state' => $state]);
    }

    public function includeRetrievedContext(array $state, array $context): array
    {
        $known = $state['known_information'] ?? [];
        foreach (['persistent_memories', 'relevant_messages'] as $section) {
            foreach ($context[$section] ?? [] as $item) {
                $known[] = ['source' => $section, 'data' => $item];
            }
        }

        return array_merge($state, [
            'current_context' => $context['temporary'] ?? $state['current_context'],
            'known_information' => array_slice($known, -20),
        ]);
    }

    public function afterReasoning(array $state, array $response, int $step): array
    {
        $calls = $response['tool_calls'] ?? [];

        return array_merge($state, [
            'current_task' => $response['intent'] ?? null,
            'last_action' => 'reasoning_step_'.$step,
            'last_action_result' => [
                'intent' => $response['intent'] ?? null,
                'message' => $response['message'] ?? null,
            ],
            'confidence' => $this->confidence($response),
            'next_action' => $calls === []
                ? 'respond_to_user'
                : 'execute_tools',
        ]);
    }

    public function afterTool(
        array $state,
        string $toolName,
        array $result,
        int $step,
        ?string $nextAction = null,
    ): array {
        $known = $state['known_information'] ?? [];
        if (($result['successful'] ?? false) && isset($result['data'])) {
            $known[] = [
                'source' => $toolName,
                'data' => $result['data'],
            ];
        }

        return array_merge($state, [
            'current_task' => $toolName,
            'last_action' => 'tool:'.$toolName,
            'last_action_result' => $result,
            'known_information' => array_slice($known, -20),
            'unknown_information' => $result['successful'] ?? false
                ? ($state['unknown_information'] ?? [])
                : array_values(array_unique(array_merge(
                    $state['unknown_information'] ?? [],
                    [$result['error'] ?? 'La herramienta no devolvió un resultado válido.']
                ))),
            'confidence' => ($result['successful'] ?? false) ? 80 : 20,
            'next_action' => $nextAction ?? 'reason_over_tool_result',
        ]);
    }

    public function markFailure(array $state, string $error): array
    {
        return array_merge($state, [
            'last_action_result' => ['error' => $error],
            'unknown_information' => array_values(array_unique(array_merge(
                $state['unknown_information'] ?? [],
                [$error]
            ))),
            'confidence' => 0,
            'next_action' => 'stop_and_report',
        ]);
    }

    private function knownInformation(array $context): array
    {
        return array_map(
            fn ($key, $value) => ['source' => 'request_context', 'key' => $key, 'value' => $value],
            array_keys($context),
            array_values($context)
        );
    }

    private function confidence(array $response): int
    {
        $metadata = $response['metadata'] ?? [];
        return max(0, min(100, (int) ($metadata['confidence'] ?? 60)));
    }
}
