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
                'dependencies' => array_values((array) ($raw['dependencies'] ?? [])),
                'priority' => max(1, min(100, (int) ($raw['priority'] ?? 50))),
                'status' => (string) ($raw['status'] ?? 'pending'),
                'expected_result' => (string) ($raw['expected_result'] ?? 'Resultado verificable de la subtarea.'),
                'completion_criteria' => (string) ($raw['completion_criteria'] ?? 'La subtarea produce el resultado esperado y pasa su validación.'),
                'result' => $raw['result'] ?? null,
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
            $subtasks[$currentIndex]['status'] = $successful ? 'completed' : 'blocked';
            $subtasks[$currentIndex]['result'] = [
                'action' => $action,
                'ok' => $successful,
                'error' => $result['error'] ?? null,
            ];
        }

        if ($successful) {
            foreach ($subtasks as $index => $subtask) {
                if ($subtask['status'] !== 'pending' || ! $this->dependenciesCompleted($subtasks, $subtask['dependencies'])) {
                    continue;
                }

                $subtasks[$index]['status'] = 'in_progress';
                break;
            }
        }

        $plan->update([
            'subtasks' => $subtasks,
            'status' => collect($subtasks)->contains(fn (array $task) => $task['status'] === 'blocked')
                ? 'blocked'
                : (collect($subtasks)->every(fn (array $task) => $task['status'] === 'completed')
                    ? 'completed'
                    : 'in_progress'),
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
}
