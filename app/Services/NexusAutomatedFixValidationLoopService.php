<?php

namespace App\Services;

use App\Models\NexusRun;
use App\Models\User;
use Illuminate\Support\Arr;
use Throwable;

final class NexusAutomatedFixValidationLoopService
{
    private const MAX_ITERATIONS = 5;

    public function __construct(
        private readonly NexusControlledCodeModificationService $modifications,
    ) {
    }

    /**
     * Executes explicitly authorized fix attempts. It never creates a new
     * proposal or authorization on behalf of the caller.
     *
     * @param array<string, mixed> $loop
     * @return array<string, mixed>
     */
    public function execute(
        array $loop,
        ?User $user,
        string $sessionId,
        bool $confirmed,
    ): array {
        $errors = $this->validateLoop($loop, $user, $confirmed);
        if ($errors !== []) {
            return [
                'status' => 'rejected',
                'error' => ['code' => 'fix_loop_rejected', 'message' => implode('; ', $errors)],
            ];
        }

        $maxIterations = (int) $loop['max_iterations'];
        $iterations = $loop['iterations'];
        $run = NexusRun::create([
            'usuario_id' => $user->id,
            'source' => 'automated_fix_validation_loop',
            'message' => (string) $loop['objective'],
            'status' => 'running',
            'steps' => 0,
            'context' => [
                'session_id' => $sessionId,
                'max_iterations' => $maxIterations,
                'authorized' => true,
            ],
        ]);

        $evidence = [];
        $previous = null;

        try {
            foreach (array_slice($iterations, 0, $maxIterations) as $index => $iteration) {
                $number = $index + 1;
                $entry = [
                    'iteration' => $number,
                    'started_at' => now()->toIso8601String(),
                    'objective' => $iteration['objective'],
                    'change' => $iteration['change'],
                    'tests' => $iteration['tests'],
                    'diagnosis' => $iteration['diagnosis'],
                    'next_action' => $iteration['next_action'],
                    'authorization' => [
                        'authorized' => $iteration['authorized'],
                        'confirmed' => $iteration['confirmed'],
                    ],
                ];

                if (! $iteration['authorized'] || ! $iteration['confirmed']) {
                    $entry['result'] = ['status' => 'authorization_required'];
                    $entry['comparison'] = $this->compare($previous, $entry['result']);
                    $entry['next_action'] = 'detener y solicitar autorización explícita';
                    $entry['finished_at'] = now()->toIso8601String();
                    $evidence[] = $entry;
                    return $this->finish($run, 'stopped_authorization_required', $evidence);
                }

                $plan = $iteration['plan'];
                $change = $iteration['change'];
                if (isset($iteration['tests']['files'])) {
                    $change['validation_files'] = $iteration['tests']['files'];
                }

                $result = $this->modifications->execute(
                    $plan,
                    $user,
                    $sessionId,
                    true,
                    $change,
                );
                $entry['result'] = $result;
                $entry['comparison'] = $this->compare($previous, $result);
                $entry['finished_at'] = now()->toIso8601String();
                $evidence[] = $entry;
                $previous = $entry;

                if ($result['status'] === 'completed') {
                    return $this->finish($run, 'completed', $evidence);
                }

                if ($number >= $maxIterations) {
                    return $this->finish($run, 'stopped_max_iterations', $evidence);
                }

                if (! $this->allowsContinuation($iteration['next_action'])) {
                    return $this->finish($run, 'stopped_no_recovery', $evidence);
                }
            }

            return $this->finish($run, 'stopped_max_iterations', $evidence);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error' => 'fix_loop_failed: '.$exception->getMessage(),
                'result' => ['status' => 'failed', 'iterations' => $evidence],
            ]);

            return [
                'status' => 'failed',
                'run_id' => $run->id,
                'iterations' => $evidence,
                'error' => [
                    'code' => 'fix_loop_failed',
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }

    /** @param array<string, mixed> $loop */
    private function validateLoop(array $loop, ?User $user, bool $confirmed): array
    {
        $errors = [];
        if (! $user || $user->rol !== 'admin') {
            $errors[] = 'Se requiere un usuario administrador.';
        }
        if (! $confirmed) {
            $errors[] = 'Se requiere confirmación explícita del ciclo.';
        }
        if (! is_string($loop['objective'] ?? null) || trim($loop['objective']) === '') {
            $errors[] = 'El ciclo requiere un objetivo.';
        }
        $max = $loop['max_iterations'] ?? null;
        if (! is_int($max) || $max < 1 || $max > self::MAX_ITERATIONS) {
            $errors[] = 'max_iterations debe estar entre 1 y '.self::MAX_ITERATIONS.'.';
        }
        if (! is_array($loop['iterations'] ?? null) || $loop['iterations'] === []) {
            $errors[] = 'El ciclo requiere al menos una iteración preparada.';
        }
        foreach (($loop['iterations'] ?? []) as $index => $iteration) {
            foreach (['objective', 'change', 'tests', 'diagnosis', 'next_action', 'plan', 'authorized', 'confirmed'] as $field) {
                if (! array_key_exists($field, $iteration)) {
                    $errors[] = "La iteración ".($index + 1)." requiere {$field}.";
                }
            }
            if (isset($iteration['change']) && ! is_array($iteration['change'])) {
                $errors[] = 'El cambio de cada iteración debe ser estructurado.';
            }
            if (isset($iteration['tests']['files']) && ! is_array($iteration['tests']['files'])) {
                $errors[] = 'tests.files debe ser una lista.';
            }
        }
        return $errors;
    }

    private function allowsContinuation(mixed $nextAction): bool
    {
        if (is_string($nextAction)) {
            return in_array(strtolower($nextAction), ['continue', 'continuar'], true);
        }
        return is_array($nextAction)
            && ($nextAction['type'] ?? null) === 'continue'
            && ($nextAction['authorized'] ?? false) === true;
    }

    /** @return array<string, mixed> */
    private function compare(?array $previous, array $current): array
    {
        if ($previous === null) {
            return ['baseline' => true, 'status_changed' => true];
        }
        $previousStatus = Arr::get($previous, 'result.status');
        $currentStatus = $current['status'] ?? null;
        return [
            'baseline' => false,
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
            'status_changed' => $previousStatus !== $currentStatus,
            'previous_error' => Arr::get($previous, 'result.error.code'),
            'current_error' => Arr::get($current, 'error.code'),
        ];
    }

    /** @param array<int, array<string, mixed>> $evidence */
    private function finish(NexusRun $run, string $status, array $evidence): array
    {
        $result = [
            'status' => $status === 'completed' ? 'completed' : 'stopped',
            'run_id' => $run->id,
            'loop_status' => $status,
            'iterations' => $evidence,
            'iteration_count' => count($evidence),
        ];
        $run->update([
            'status' => $result['status'],
            'steps' => count($evidence),
            'result' => $result,
            'error' => $status === 'completed' ? null : $status,
        ]);
        return $result;
    }
}
