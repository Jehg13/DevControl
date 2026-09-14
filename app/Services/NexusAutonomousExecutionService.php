<?php

namespace App\Services;

use App\Models\NexusAutonomousRun;
use App\Models\NexusRun;
use App\Nexus\NexusToolRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

class NexusAutonomousExecutionService
{
    public function __construct(
        private readonly NexusPlannerService $planner,
        private readonly NexusPlanService $plans,
        private readonly NexusExecutionService $execution,
        private readonly NexusToolRegistry $tools,
    ) {
    }

    public function start(
        string $objective,
        array $context = [],
        ?int $userId = null,
        string $source = 'autonomous',
        array $limits = [],
    ): NexusAutonomousRun {
        $limits = $this->limits($limits);
        $run = NexusRun::create([
            'usuario_id' => $userId,
            'source' => $source,
            'message' => $objective,
            'status' => 'running',
            'context' => $context,
            'internal_state' => [
                'current_goal' => $objective,
                'phase' => 'planning',
                'limits' => $limits,
                'next_action' => 'create_plan',
            ],
        ]);

        $autonomous = NexusAutonomousRun::create([
            'nexus_run_id' => $run->id,
            'usuario_id' => $userId,
            'proyecto_id' => $context['project_id'] ?? $context['proyecto_id'] ?? null,
            'objective' => $objective,
            'status' => 'planning',
            'limits' => $limits,
            'state' => [
                'current_goal' => $objective,
                'current_task' => null,
                'current_step' => null,
                'available_tools' => $this->toolNames($limits),
                'permissions' => $context['granted_permissions'] ?? $context['permissions'] ?? [],
                'results' => [],
                'errors' => [],
                'decisions' => [],
                'next_action' => 'create_plan',
            ],
            'started_at' => now(),
        ]);

        try {
            $planning = $this->planner->create(
                $objective,
                $context,
                $context['project'] ?? [],
                $limits['restrictions'] ?? [],
                $context['granted_permissions'] ?? $context['permissions'] ?? [],
                $this->filteredDefinitions($limits)
            );

            if (! $planning->successful || $planning->response === null) {
                return $this->finish($autonomous, 'failed', [], $planning->error ?? 'No se pudo crear el plan.');
            }

            $plan = $this->planner->persist(
                $run,
                $objective,
                $planning->response->metadata,
                $context
            );

            if ($plan === null || ($plan->subtasks ?? []) === []) {
                return $this->finish($autonomous, 'failed', [], 'El plan no contiene pasos ejecutables.');
            }

            $autonomous->update([
                'nexus_plan_id' => $plan->id,
                'plan' => $plan->toArray(),
                'status' => 'running',
                'state' => array_merge($autonomous->state ?? [], [
                    'phase' => 'execution',
                    'plan' => $plan->toArray(),
                    'next_action' => 'execute_next_step',
                ]),
            ]);
            $run->update(['status' => 'running']);

            return $this->run($autonomous->fresh(), $userId, $context);
        } catch (Throwable $exception) {
            Log::error('Nexus autonomous planning failed.', [
                'run_id' => $run->id,
                'exception' => $exception,
            ]);

            return $this->finish($autonomous, 'failed', [], 'La planificación autónoma falló.');
        }
    }

    public function resume(
        NexusAutonomousRun $autonomous,
        ?int $userId = null,
        bool $approved = false,
    ): NexusAutonomousRun {
        if (! in_array($autonomous->status, ['paused', 'awaiting_approval'], true)) {
            return $autonomous->fresh();
        }

        if ($autonomous->status === 'awaiting_approval' && ! $approved) {
            return $autonomous->fresh();
        }

        $state = $autonomous->state ?? [];
        if ($approved && ($state['current_step']['id'] ?? null) !== null) {
            $state['approval_granted_for_step'] = $state['current_step']['id'];
        }

        $autonomous->update([
            'status' => 'running',
            'paused_at' => null,
            'state' => array_merge($state, ['next_action' => 'resume_execution']),
        ]);

        return $this->run(
            $autonomous->fresh(),
            $userId ?? $autonomous->usuario_id,
            $autonomous->run?->context ?? []
        );
    }

    public function pause(NexusAutonomousRun $autonomous): NexusAutonomousRun
    {
        if ($autonomous->status !== 'running') {
            return $autonomous->fresh();
        }

        $state = array_merge($autonomous->state ?? [], ['next_action' => 'resume_execution']);
        $autonomous->update([
            'status' => 'paused',
            'paused_at' => now(),
            'state' => $state,
        ]);
        $autonomous->run?->update(['status' => 'paused']);

        return $autonomous->fresh();
    }

    private function run(NexusAutonomousRun $autonomous, ?int $userId, array $context): NexusAutonomousRun
    {
        $plan = $autonomous->planModel()->first();
        if ($plan === null) {
            return $this->finish($autonomous, 'failed', [], 'La ejecución no tiene un plan persistido.');
        }

        $limits = $autonomous->limits ?? $this->limits([]);
        $state = $autonomous->state ?? [];

        while ($autonomous->steps < $limits['max_steps']) {
            $autonomous->refresh();
            if ($autonomous->status === 'paused') {
                return $autonomous;
            }
            if ($this->timedOut($autonomous, $limits)) {
                return $this->finish($autonomous, 'limit_exceeded', $state, 'Se agotó el tiempo máximo de ejecución.');
            }
            if ((int) ($state['budget_used'] ?? 0) >= $limits['budget']) {
                return $this->finish($autonomous, 'limit_exceeded', $state, 'Se agotó el presupuesto de ejecución.');
            }

            $step = $this->plans->nextStep($plan->fresh());
            if ($step === null) {
                if (collect($plan->subtasks ?? [])->contains(fn (array $task) => in_array($task['status'], ['failed', 'blocked'], true))) {
                    return $this->finish($autonomous, 'blocked', $state, 'El plan contiene pasos bloqueados.');
                }
                if ($plan->status !== 'completed') {
                    $plan = $this->plans->complete($plan);
                    $autonomous->update(['plan' => $plan->toArray()]);
                }
                return $this->finish($autonomous, 'completed', $state, null);
            }

            $state['current_step'] = $step;
            $state['current_task'] = $step['title'] ?? $step['description'] ?? $step['id'];
            $state['next_action'] = 'execute_step';
            $autonomous->update(['state' => $state]);

            $plan = $this->setStepStatus($plan, $step['id'], 'in_progress');
            $confirmed = ($state['approval_granted_for_step'] ?? null) === $step['id'];
            $child = $this->execution->execute(
                $step['description'] ?: $step['title'],
                array_merge($context, [
                    'autonomous_run_id' => $autonomous->id,
                    'autonomous_step_id' => $step['id'],
                ]),
                [],
                $userId,
                'autonomous_step',
                $confirmed,
                'autonomous-'.$autonomous->id,
                $limits['allowed_tools'],
                $limits['prohibited_tools'],
            );
            $autonomous->increment('steps');
            $autonomous->refresh();

            $state['results'][] = [
                'step_id' => $step['id'],
                'run_id' => $child->id,
                'status' => $child->status,
                'result' => $child->result,
                'error' => $child->error,
            ];
            $state['budget_used'] = (int) ($state['budget_used'] ?? 0) + $child->toolCalls()->count();
            $state['approval_granted_for_step'] = null;

            if ($child->status === 'awaiting_confirmation') {
                $state['next_action'] = 'await_human_approval';
                $autonomous->update(['state' => $state, 'status' => 'awaiting_approval']);
                $autonomous->run?->update(['status' => 'awaiting_confirmation']);

                return $autonomous;
            }

            if ($child->status !== 'completed') {
                $message = $child->error ?? 'El paso autónomo falló.';
                $state['errors'][] = ['step_id' => $step['id'], 'message' => $message];
                if ($this->canRetry($child->error, (int) ($step['attempts'] ?? 0), $limits)) {
                    $autonomous->increment('retries');
                    $plan = $this->setStepStatus($plan, $step['id'], 'pending', $message);
                    $autonomous->update(['state' => $state]);
                    continue;
                }

                $plan = $this->setStepStatus($plan, $step['id'], 'failed', $message);
                $autonomous->update(['state' => $state]);

                return $this->finish($autonomous, $this->failureStatus($child->error), $state, $message);
            }

            $plan = $this->setStepStatus($plan, $step['id'], 'completed', null, $child->result);
            $state['next_action'] = 'evaluate_result';
            $autonomous->update([
                'plan' => $plan->toArray(),
                'state' => $state,
            ]);
            if ($this->plans->nextStep($plan) === null) {
                $plan = $this->plans->complete($plan);
                $autonomous->update(['plan' => $plan->toArray()]);

                return $this->finish($autonomous, 'completed', $state, null);
            }
        }

        return $this->finish($autonomous, 'limit_exceeded', $state, 'Nexus alcanzó el límite máximo de pasos.');
    }

    private function setStepStatus($plan, string $stepId, string $status, ?string $error = null, mixed $result = null)
    {
        $subtasks = $plan->subtasks ?? [];
        foreach ($subtasks as &$task) {
            if (($task['id'] ?? null) !== $stepId) {
                continue;
            }
            $task['status'] = $status;
            $task['attempts'] = max(0, (int) ($task['attempts'] ?? 0)) + ($status === 'in_progress' ? 1 : 0);
            $task['error'] = $error;
            $task['result'] = $result ?? ($task['result'] ?? null);
            $task['updated_at'] = now()->toISOString();
            if (in_array($status, ['completed', 'failed', 'blocked'], true)) {
                $task['completed_at'] = now()->toISOString();
            }
        }

        return $this->plans->modify($plan, ['subtasks' => $subtasks, 'reason' => 'autonomous_step_'.$status]);
    }

    private function finish(NexusAutonomousRun $autonomous, string $status, array $state, ?string $error): NexusAutonomousRun
    {
        $state['next_action'] = $status === 'completed' ? 'completed' : 'stop_and_report';
        $result = [
            'status' => $status,
            'steps' => $autonomous->steps,
            'retries' => $autonomous->retries,
            'plan' => $autonomous->plan,
            'state' => $state,
        ];
        $autonomous->update([
            'status' => $status,
            'state' => $state,
            'result' => $result,
            'error' => $error,
            'duration_ms' => $autonomous->started_at
                ? max(0, now()->diffInMilliseconds($autonomous->started_at))
                : null,
            'finished_at' => in_array($status, ['completed', 'failed', 'blocked', 'limit_exceeded'], true) ? now() : null,
        ]);
        $autonomous->run?->update([
            'status' => $status === 'completed' ? 'completed' : ($status === 'awaiting_approval' ? 'awaiting_confirmation' : 'failed'),
            'result' => $result,
            'error' => $error,
        ]);

        return $autonomous->fresh();
    }

    private function canRetry(?string $error, int $attempts, array $limits): bool
    {
        if ($attempts >= $limits['max_retries']) {
            return false;
        }
        foreach (['permission_denied', 'permission_not_declared', 'tool_not_allowed', 'confirmation_required', 'max_steps_exceeded', 'duplicate_tool_call'] as $nonRetryable) {
            if ($error !== null && str_contains($error, $nonRetryable)) {
                return false;
            }
        }

        return true;
    }

    private function failureStatus(?string $error): string
    {
        return $error !== null && (str_contains($error, 'permission_') || str_contains($error, 'tool_not_allowed'))
            ? 'blocked'
            : 'failed';
    }

    private function timedOut(NexusAutonomousRun $autonomous, array $limits): bool
    {
        return $limits['timeout_seconds'] > 0
            && $autonomous->started_at !== null
            && now()->diffInSeconds($autonomous->started_at) >= $limits['timeout_seconds'];
    }

    private function limits(array $overrides): array
    {
        $defaults = config('nexus.autonomy', []);
        $limits = array_merge([
            'max_steps' => 10,
            'max_retries' => 2,
            'timeout_seconds' => 300,
            'budget' => 20,
            'allowed_tools' => null,
            'prohibited_tools' => [],
            'restrictions' => [],
        ], $defaults, $overrides);

        $limits['max_steps'] = max(1, (int) $limits['max_steps']);
        $limits['max_retries'] = max(0, (int) $limits['max_retries']);
        $limits['timeout_seconds'] = max(0, (int) $limits['timeout_seconds']);
        $limits['budget'] = max(1, (int) $limits['budget']);
        $limits['allowed_tools'] = $limits['allowed_tools'] === null
            ? null
            : array_values(array_filter((array) $limits['allowed_tools'], 'is_string'));
        $limits['prohibited_tools'] = array_values(array_filter((array) $limits['prohibited_tools'], 'is_string'));

        return $limits;
    }

    private function filteredDefinitions(array $limits): array
    {
        return array_values(array_filter(
            $this->tools->definitions(),
            fn (array $definition): bool => ($limits['allowed_tools'] === null || in_array($definition['name'], $limits['allowed_tools'], true))
                && ! in_array($definition['name'], $limits['prohibited_tools'], true)
        ));
    }

    private function toolNames(array $limits): array
    {
        return array_values(array_map(
            fn (array $definition): string => $definition['name'],
            $this->filteredDefinitions($this->limits($limits))
        ));
    }
}
