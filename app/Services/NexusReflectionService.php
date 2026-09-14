<?php

namespace App\Services;

use App\Models\NexusConversation;
use App\Models\NexusRun;

class NexusReflectionService
{
    public function __construct(
        private readonly NexusMemoryService $memory,
        private readonly NexusPlanService $plans,
        private readonly NexusProjectUnderstandingService $understanding,
        private readonly ?NexusExperienceService $experiences = null,
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
            'attempted_action' => $state['last_action'] ?? null,
            'rationale' => (string) ($state['current_task'] ?? $state['next_action'] ?? 'Evaluar la ejecución.'),
            'expected_result' => $this->expectedResult($state),
            'actual_result' => $finalResult,
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
            'evaluation' => $this->evaluation($fulfilled, $errors, $state, $finalResult),
            'evidence_classification' => $this->classifyEvidence($known, $state),
            'learning' => $this->learning($fulfilled, $errors, $state),
            'alternative_strategy' => $this->alternativeStrategy($errors, $state),
            'error_patterns' => $this->errorPatterns($errors, $actions),
            'planner_feedback' => $this->plannerFeedback($run, $errors),
            'understanding_feedback' => $this->understandingFeedback($run, $errors),
            'should_remember' => $this->memoryCandidates($state, $fulfilled, $errors),
            'experience_candidate' => $this->experienceCandidate($run, $state, $fulfilled, $errors, $actions, $known),
            'next_steps' => $this->nextSteps($state, $fulfilled, $errors),
            'confidence' => max(0, min(100, (int) ($state['confidence'] ?? 0))),
            'confidence_indicator' => round(max(0, min(100, (int) ($state['confidence'] ?? 0))) / 100, 2),
            'reflection_budget' => [
                'tool_calls_considered' => $toolCalls->count(),
                'known_items_considered' => count($known),
                'bounded' => true,
            ],
            'generated_at' => now()->toISOString(),
        ];

        $run->update(['reflection' => $reflection]);
        $this->persistMemoryCandidates($conversation, $reflection['should_remember'], $run);
        $this->experiences?->fromReflection($run, $reflection);

        return $reflection;
    }

    private function expectedResult(array $state): ?string
    {
        $current = collect($state['plan']['subtasks'] ?? [])->firstWhere('status', 'in_progress');

        return $state['plan']['expected_result'] ?? ($current['expected_result'] ?? null);
    }

    private function evaluation(bool $fulfilled, array $errors, array $state, array $result): array
    {
        return [
            'outcome' => $fulfilled ? 'success' : ($errors !== [] ? 'failure' : 'uncertain'),
            'quality' => $fulfilled ? 1.0 : ($errors !== [] ? 0.0 : 0.5),
            'confidence_indicator' => round(max(0, min(100, (int) ($state['confidence'] ?? 0))) / 100, 2),
            'basis' => $errors !== [] ? 'tool_errors' : ($fulfilled ? 'completed_without_errors' : 'insufficient_evidence'),
            'result_status' => $result['status'] ?? null,
        ];
    }

    private function classifyEvidence(array $known, array $state): array
    {
        return [
            'observed_facts' => array_values(array_filter($known, fn (array $item): bool => in_array($item['source'] ?? '', $state['available_tools'] ?? [], true))),
            'inferences' => array_values(array_filter($known, fn (array $item): bool => ($item['source'] ?? '') === 'reasoning')),
            'hypotheses' => $state['hypotheses'] ?? [],
            'decisions' => array_values(array_filter($known, fn (array $item): bool => ($item['source'] ?? '') === 'decision')),
        ];
    }

    private function learning(bool $fulfilled, array $errors, array $state): array
    {
        if ($errors !== []) {
            return [
                'conclusion' => 'La estrategia actual no produjo el resultado esperado.',
                'new_information' => array_slice($state['known_information'] ?? [], -5),
            ];
        }

        return $fulfilled
            ? ['conclusion' => 'La estrategia produjo un resultado sin errores registrados.', 'new_information' => array_slice($state['known_information'] ?? [], -5)]
            : ['conclusion' => 'No hay evidencia suficiente para afirmar que el objetivo fue cumplido.'];
    }

    private function alternativeStrategy(array $errors, array $state): ?array
    {
        if ($errors === []) {
            return null;
        }

        return [
            'hypothesis' => 'La causa puede estar fuera de la herramienta que falló.',
            'next_action' => $state['next_action'] ?? 'analizar_error',
            'requires_human_intervention' => in_array($state['next_action'] ?? null, ['await_confirmation', 'human_intervention'], true),
        ];
    }

    private function errorPatterns(array $errors, array $actions): array
    {
        $patterns = [];
        foreach ($errors as $error) {
            $message = strtolower((string) ($error['error'] ?? ''));
            $sameTool = collect($actions)->where('tool', $error['tool'] ?? null)->where('status', 'failed')->count() > 1;
            if ($sameTool || str_contains($message, 'conflict') || str_contains($message, 'not found')) {
                $patterns[] = [
                    'type' => $sameTool ? 'repeated_tool_failure' : 'known_failure_signal',
                    'tool' => $error['tool'] ?? null,
                    'message' => $error['error'] ?? null,
                    'reusable' => true,
                ];
            }
        }

        return $patterns;
    }

    private function plannerFeedback(NexusRun $run, array $errors): array
    {
        $plan = $run->plan;
        if (! $plan || $errors === []) {
            return ['updated' => false];
        }

        $updated = $this->plans->modify($plan, [
            'reason' => 'reflection_detected_failure',
            'metadata' => ['reflection_errors' => $errors],
        ]);

        return ['updated' => true, 'status' => $updated->status, 'plan_id' => $updated->id];
    }

    private function understandingFeedback(NexusRun $run, array $errors): array
    {
        $projectId = $run->context['project_id'] ?? $run->context['proyecto_id'] ?? null;
        $stale = collect($errors)->contains(fn (array $error): bool => preg_match('/stale|outdated|cambi|conflict|not found/i', (string) ($error['error'] ?? '')) === 1);
        if (! $projectId || ! $stale) {
            return ['invalidated' => false];
        }

        return [
            'invalidated' => $this->understanding->invalidateForProject((int) $projectId, 'La reflexión detectó evidencia posiblemente obsoleta.') > 0,
            'project_id' => (int) $projectId,
        ];
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

    private function experienceCandidate(
        NexusRun $run,
        array $state,
        bool $fulfilled,
        array $errors,
        array $actions,
        array $known,
    ): ?array {
        $important = count($errors) > 0
            || count($actions) >= 2
            || ($fulfilled
                && ($state['current_task'] ?? null)
                && ($state['phase'] ?? null) !== 'reporting');
        if (! $important) {
            return null;
        }

        return [
            'problem' => $run->message,
            'context' => $state['current_context'] ?? $run->context ?? [],
            'hypothesis' => $state['hypotheses'][0] ?? null,
            'action' => $state['last_action'] ?? null,
            'result' => $run->result ?? [],
            'solution' => $fulfilled ? 'La estrategia ejecutada alcanzó el resultado esperado.' : null,
            'lesson' => $errors !== []
                ? 'Revisar la causa del error antes de repetir la misma estrategia.'
                : 'La estrategia utilizada produjo un resultado verificable.',
            'errors' => $errors,
            'strategy' => $state['current_task'] ?? null,
            'tools_used' => collect($actions)->pluck('tool')->unique()->values()->all(),
            'category' => $fulfilled ? 'successful_strategy' : 'failure_pattern',
            'relevance' => $fulfilled ? 70 : 75,
            'confidence' => $fulfilled ? 80 : 70,
            'metadata' => ['known_information' => array_slice($known, -5)],
        ];
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
