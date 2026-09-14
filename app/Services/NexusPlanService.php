<?php

namespace App\Services;

use App\Models\NexusPlan;
use App\Models\NexusRun;

class NexusPlanService
{
    public function createOrUpdate(NexusRun $run, array $metadata, string $fallbackObjective): ?NexusPlan
    {
        $rawSubtasks = $metadata['subtasks'] ?? $metadata['plan'] ?? [];
        if (! is_array($rawSubtasks) || $rawSubtasks === []) {
            return null;
        }

        $subtasks = [];
        foreach (array_values($rawSubtasks) as $index => $raw) {
            $raw = is_array($raw) ? $raw : ['title' => (string) $raw];
            $subtasks[] = [
                'id' => (string) ($raw['id'] ?? 'subtask-'.($index + 1)),
                'title' => trim((string) ($raw['title'] ?? $raw['task'] ?? 'Subtarea '.($index + 1))),
                'description' => trim((string) ($raw['description'] ?? $raw['title'] ?? $raw['task'] ?? '')),
                'goal' => trim((string) ($raw['goal'] ?? $raw['objective'] ?? '')),
                'dependencies' => array_values((array) ($raw['dependencies'] ?? [])),
                'required_tools' => array_values((array) ($raw['required_tools'] ?? $raw['tools'] ?? [])),
                'priority' => max(1, min(100, (int) ($raw['priority'] ?? 50))),
                'status' => $this->normalizeStatus((string) ($raw['status'] ?? 'pending')),
                'expected_result' => (string) ($raw['expected_result'] ?? 'Resultado verificable de la subtarea.'),
                'completion_criteria' => (string) ($raw['completion_criteria'] ?? 'La subtarea produce el resultado esperado y pasa su validación.'),
                'result' => $raw['result'] ?? null,
                'error' => $raw['error'] ?? null,
                'attempts' => max(0, (int) ($raw['attempts'] ?? 0)),
                'started_at' => $raw['started_at'] ?? null,
                'completed_at' => $raw['completed_at'] ?? null,
                'updated_at' => now()->toISOString(),
            ];
        }

        $plan = NexusPlan::updateOrCreate(
            ['nexus_run_id' => $run->id],
            [
                'objective' => (string) ($metadata['objective'] ?? $fallbackObjective),
                'subtasks' => $subtasks,
                'status' => (string) ($metadata['status'] ?? 'active'),
                'priority' => max(1, min(100, (int) ($metadata['priority'] ?? 50))),
                'expected_result' => (string) ($metadata['expected_result'] ?? 'Completar el objetivo solicitado.'),
                'completion_criteria' => (string) ($metadata['completion_criteria'] ?? 'Todas las subtareas terminan y las validaciones pasan.'),
                'metadata' => ['source' => 'model', 'updated_at' => now()->toISOString()],
            ]
        );

        return $plan;
    }

    public function updateAfterAction(NexusPlan $plan, string $action, array $result): NexusPlan
    {
        $subtasks = $plan->subtasks ?? [];
        $successful = (bool) ($result['ok'] ?? false);
        $currentIndex = collect($subtasks)->search(fn (array $task) => in_array($task['status'], ['in_progress', 'pending'], true));

        if ($currentIndex !== false) {
            $subtasks[$currentIndex]['status'] = $successful ? 'completed' : 'failed';
            $subtasks[$currentIndex]['result'] = [
                'action' => $action,
                'ok' => $successful,
                'error' => $result['error'] ?? null,
            ];
            $subtasks[$currentIndex]['error'] = $successful ? null : ($result['error'] ?? 'La acción falló.');
            $subtasks[$currentIndex]['completed_at'] = $successful ? now()->toISOString() : null;
            $subtasks[$currentIndex]['updated_at'] = now()->toISOString();
        }

        if ($successful) {
            foreach ($subtasks as $index => $subtask) {
                if ($subtask['status'] !== 'pending' || ! $this->dependenciesCompleted($subtasks, $subtask['dependencies'])) {
                    continue;
                }

                $subtasks[$index]['status'] = 'in_progress';
                $subtasks[$index]['attempts'] = max(0, (int) ($subtasks[$index]['attempts'] ?? 0)) + 1;
                $subtasks[$index]['started_at'] = $subtasks[$index]['started_at'] ?? now()->toISOString();
                $subtasks[$index]['updated_at'] = now()->toISOString();
                break;
            }
        }

        $hasFailure = collect($subtasks)->contains(fn (array $task) => in_array($task['status'], ['failed', 'blocked'], true));
        $allFinished = collect($subtasks)->every(fn (array $task) => in_array($task['status'], ['completed', 'skipped'], true));
        $plan->update([
            'subtasks' => $subtasks,
            'status' => $hasFailure ? 'blocked' : ($allFinished ? 'completed' : 'in_progress'),
            'metadata' => array_merge($plan->metadata ?? [], ['last_action' => $action, 'updated_at' => now()->toISOString()]),
        ]);

        return $plan->fresh();
    }

    private function dependenciesCompleted(array $subtasks, array $dependencies): bool
    {
        if ($dependencies === []) {
            return true;
        }

        $completed = collect($subtasks)
            ->filter(fn (array $task) => $task['status'] === 'completed')
            ->pluck('id');

        return collect($dependencies)->every(fn ($dependency) => $completed->contains($dependency));
    }

    public function complete(NexusPlan $plan): NexusPlan
    {
        $subtasks = collect($plan->subtasks ?? [])
            ->map(fn (array $task) => array_merge($task, ['status' => 'completed']))
            ->all();

        $plan->update(['subtasks' => $subtasks, 'status' => 'completed']);

        return $plan->fresh();
    }

    public function modify(NexusPlan $plan, array $changes): NexusPlan
    {
        $subtasks = array_values((array) ($changes['subtasks'] ?? $plan->subtasks ?? []));
        $normalized = $this->normalizeSubtasks($subtasks);

        $plan->update([
            'objective' => array_key_exists('objective', $changes) ? (string) $changes['objective'] : $plan->objective,
            'subtasks' => $normalized,
            'status' => array_key_exists('status', $changes) ? (string) $changes['status'] : $plan->status,
            'priority' => array_key_exists('priority', $changes) ? max(1, min(100, (int) $changes['priority'])) : $plan->priority,
            'expected_result' => array_key_exists('expected_result', $changes) ? $changes['expected_result'] : $plan->expected_result,
            'completion_criteria' => array_key_exists('completion_criteria', $changes) ? $changes['completion_criteria'] : $plan->completion_criteria,
            'metadata' => array_merge($plan->metadata ?? [], [
                'modified_at' => now()->toISOString(),
                'modified_reason' => $changes['reason'] ?? 'plan_updated',
            ], (array) ($changes['metadata'] ?? [])),
        ]);

        return $plan->fresh();
    }

    public function nextStep(NexusPlan $plan): ?array
    {
        foreach ($plan->subtasks ?? [] as $task) {
            if ($task['status'] === 'pending' && $this->dependenciesCompleted($plan->subtasks ?? [], $task['dependencies'] ?? [])) {
                return $task;
            }
        }

        return collect($plan->subtasks ?? [])->first(fn (array $task) => $task['status'] === 'in_progress');
    }

    private function normalizeSubtasks(array $subtasks): array
    {
        $normalized = [];
        foreach (array_values($subtasks) as $index => $task) {
            $task = is_array($task) ? $task : ['title' => (string) $task];
            $normalized[] = array_merge([
                'id' => 'subtask-'.($index + 1),
                'title' => 'Subtarea '.($index + 1),
                'description' => '',
                'goal' => '',
                'dependencies' => [],
                'required_tools' => [],
                'priority' => 50,
                'status' => 'pending',
                'expected_result' => '',
                'completion_criteria' => '',
                'result' => null,
                'error' => null,
                'attempts' => 0,
                'started_at' => null,
                'completed_at' => null,
                'updated_at' => now()->toISOString(),
            ], $task, [
                'status' => $this->normalizeStatus((string) ($task['status'] ?? 'pending')),
                'dependencies' => array_values((array) ($task['dependencies'] ?? [])),
                'required_tools' => array_values((array) ($task['required_tools'] ?? $task['tools'] ?? [])),
            ]);
        }

        return $normalized;
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['pending', 'in_progress', 'completed', 'failed', 'blocked', 'skipped'], true)
            ? $status
            : 'pending';
    }
}
