<?php

namespace App\Services;

use App\Models\NexusConversation;
use App\Models\NexusRun;

class NexusReflectionService
{
    public function __construct(
        private readonly NexusMemoryService $memory,
    ) {
    }

    public function reflect(
        NexusRun $run,
        array $state,
        array $finalResult,
        NexusConversation $conversation,
    ): array {
        $toolCalls = $run->toolCalls()->orderBy('sequence')->get();
        $actions = $toolCalls->map(fn ($call) => [
            'sequence' => $call->sequence,
            'tool' => $call->tool_name,
            'arguments' => $call->arguments,
            'status' => $call->status,
            'result' => $call->result,
            'error' => $call->error,
        ])->values()->all();
        $errors = $toolCalls->filter(fn ($call) => $call->status === 'failed' || $call->error !== null)
            ->map(fn ($call) => [
                'tool' => $call->tool_name,
                'error' => $call->error,
                'result' => $call->result,
            ])->values()->all();
        $known = array_slice($state['known_information'] ?? [], -10);
        $fulfilled = $run->status === 'completed'
            && ! collect($errors)->isNotEmpty()
            && ($state['next_action'] ?? null) === 'completed';

        $reflection = [
            'objective_original' => $run->message,
            'actions_performed' => $actions,
            'tools_used' => $toolCalls->pluck('tool_name')->unique()->values()->all(),
            'result' => $finalResult,
            'errors' => $errors,
            'decisions' => [
                'intent' => $state['current_task'] ?? null,
                'phase' => $state['phase'] ?? null,
                'next_action' => $state['next_action'] ?? null,
                'plan_status' => $state['plan']['status'] ?? null,
                'plan' => $state['plan']['subtasks'] ?? $state['plan'] ?? [],
                'target_files' => $state['target_files'] ?? [],
            ],
            'new_information' => $known,
            'goal_fulfilled' => $fulfilled,
            'should_remember' => $this->memoryCandidates($state, $fulfilled, $errors),
            'next_steps' => $this->nextSteps($state, $fulfilled, $errors),
            'confidence' => max(0, min(100, (int) ($state['confidence'] ?? 0))),
            'generated_at' => now()->toISOString(),
        ];

        $run->update(['reflection' => $reflection]);
        $this->persistMemoryCandidates($conversation, $reflection['should_remember'], $run);

        return $reflection;
    }

    private function memoryCandidates(array $state, bool $fulfilled, array $errors): array
    {
        $candidates = [];
        if ($fulfilled && ($state['current_task'] ?? null)) {
            $candidates[] = [
                'type' => 'experience',
                'content' => 'La tarea "'.($state['current_task']).'" se completó correctamente.',
                'confidence' => 80,
                'importance' => 60,
            ];
        }
        foreach ($errors as $error) {
            if (! empty($error['error'])) {
                $candidates[] = [
                    'type' => 'problem',
                    'content' => 'La herramienta '.($error['tool'] ?? 'desconocida').' produjo: '.$error['error'],
                    'confidence' => 80,
                    'importance' => 65,
                ];
            }
        }

        return $candidates;
    }

    private function nextSteps(array $state, bool $fulfilled, array $errors): array
    {
        if ($fulfilled) {
            return ['Revisar el resultado si el usuario solicita cambios adicionales.'];
        }

        if ($errors !== []) {
            return ['Analizar los errores registrados.', 'Corregir el bloqueo y volver a validar.'];
        }

        return [$state['next_action'] ?? 'Solicitar información adicional.'];
    }

    private function persistMemoryCandidates(
        NexusConversation $conversation,
        array $candidates,
        NexusRun $run,
    ): void {
        foreach ($candidates as $candidate) {
            $this->memory->remember(
                $conversation,
                $candidate['type'],
                $candidate['content'],
                $candidate['confidence'],
                [
                    'source' => 'reflection',
                    'run_id' => $run->id,
                    'importance' => $candidate['importance'],
                ]
            );
        }
    }
}
