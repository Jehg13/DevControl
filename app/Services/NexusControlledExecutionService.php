<?php

namespace App\Services;

use App\Models\NexusRun;
use App\Models\NexusToolCall;
use App\Models\User;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NexusControlledExecutionService
{
    private readonly NexusCognitiveSecurityBoundary $boundary;
    private readonly NexusEngineeringRecoveryService $recovery;

    public function __construct(
        private readonly NexusToolRegistry $tools,
        ?NexusCognitiveSecurityBoundary $boundary = null,
        ?NexusEngineeringRecoveryService $recovery = null,
        private readonly int $defaultTimeoutSeconds = 30,
    ) {
        $this->boundary = $boundary ?? new NexusCognitiveSecurityBoundary($tools);
        $this->recovery = $recovery ?? new NexusEngineeringRecoveryService();
    }

    /**
     * Executes an already validated plan sequentially. Python is not involved.
     *
     * @param array<string, mixed> $plan
     * @param array<string, array<string, mixed>> $stepParameters
     * @return array<string, mixed>
     */
    public function execute(
        array $plan,
        ?User $user,
        string $sessionId,
        bool $confirmed = false,
        array $stepParameters = [],
        ?int $timeoutSeconds = null,
    ): array {
        $validation = array_merge(
            $this->boundary->validateControlledPlan($plan, $user?->id, $confirmed),
            $this->validatePlan($plan)
        );
        if ($validation !== []) {
            return [
                'status' => 'rejected',
                'error' => ['code' => 'invalid_plan', 'message' => implode('; ', $validation)],
                'executed' => [],
            ];
        }
        if ($plan['required_permissions'] !== [] && (! $user || $user->rol !== 'admin')) {
            return [
                'status' => 'rejected',
                'error' => [
                    'code' => 'permission_denied',
                    'message' => 'El usuario no tiene autorización para ejecutar este plan.',
                ],
                'executed' => [],
            ];
        }

        $started = microtime(true);
        $timeout = $timeoutSeconds ?? $this->defaultTimeoutSeconds;
        $run = NexusRun::create([
            'usuario_id' => $user?->id,
            'source' => 'controlled_engineering_execution',
            'message' => (string) $plan['objective'],
            'status' => 'running',
            'steps' => 0,
            'context' => [
                'session_id' => $sessionId,
                'plan_id' => $plan['plan_id'],
                'plan' => $plan,
                'confirmed' => $confirmed,
            ],
        ]);

        $executed = [];
        $completedSteps = [];
        $failed = null;

        try {
            foreach ($plan['steps'] as $stepNumber => $step) {
                $stepId = (string) $step['step_id'];
                $dependencies = $step['depends_on'] ?? [];
                if (array_diff($dependencies, $completedSteps) !== []) {
                    $failed = [
                        'code' => 'step_dependency_failed',
                        'message' => "El paso [{$stepId}] tiene dependencias no completadas.",
                    ];
                    break;
                }

                $run->update(['steps' => $stepNumber + 1]);
                foreach ($step['tools'] as $toolName) {
                    if ($this->timedOut($started, $timeout)) {
                        $failed = [
                            'code' => 'execution_timeout',
                            'message' => 'El plan superó el tiempo máximo permitido.',
                        ];
                        break 2;
                    }

                    $parameters = $stepParameters[$stepId][$toolName] ?? [];
                    $checkpoint = $this->recovery->checkpoint(
                        $run,
                        'step:'.$stepId.':tool:'.$toolName.':'.($run->toolCalls()->count() + 1),
                        'controlled_tool',
                        [
                            'plan_id' => $plan['plan_id'],
                            'step_id' => $stepId,
                            'tool' => $toolName,
                        ],
                    );
                    $callStarted = now();
                    $toolCall = NexusToolCall::create([
                        'nexus_run_id' => $run->id,
                        'sequence' => $run->toolCalls()->count() + 1,
                        'tool_name' => $toolName,
                        'arguments' => [
                            'user_id' => $user?->id,
                            'plan_id' => $plan['plan_id'],
                            'session_id' => $sessionId,
                            'step_id' => $stepId,
                            'parameters' => $parameters,
                        ],
                        'status' => 'running',
                        'started_at' => $callStarted,
                    ]);

                    $result = $this->tools->execute(
                        $toolName,
                        $parameters,
                        new NexusToolContext(
                            user: $user,
                            source: 'controlled_engineering_execution',
                            confirmed: $confirmed,
                        )
                    );
                    $resultData = $result->toArray();
                    if (isset($result->data['rollback']) && is_array($result->data['rollback'])) {
                        $checkpoint = $this->recovery->registerModifiedSnapshot(
                            $checkpoint,
                            $result->data['rollback']
                        );
                    }
                    $timedOut = $this->timedOut($started, $timeout);
                    $callStatus = $timedOut ? 'failed' : ($result->successful ? 'succeeded' : 'failed');
                    $error = $timedOut
                        ? 'El tiempo máximo de ejecución fue excedido.'
                        : ($result->successful ? null : $result->error);
                    $toolCall->update([
                        'status' => $callStatus,
                        'result' => [
                            'user_id' => $user?->id,
                            'tool' => $toolName,
                            'plan_id' => $plan['plan_id'],
                            'session_id' => $sessionId,
                            'step_id' => $stepId,
                            'parameters' => $parameters,
                            'timestamp' => $callStarted->toIso8601String(),
                            'result' => $resultData,
                            'evidence' => $result->successful ? [
                                'source' => 'laravel_tool',
                                'tool' => $toolName,
                                'step_id' => $stepId,
                                'data' => $result->data,
                            ] : null,
                        ],
                        'error' => $error,
                        'finished_at' => now(),
                    ]);
                    $executed[] = [
                        'user_id' => $user?->id,
                        'plan_id' => $plan['plan_id'],
                        'session_id' => $sessionId,
                        'step_id' => $stepId,
                        'tool' => $toolName,
                        'parameters' => $parameters,
                        'timestamp' => $callStarted->toIso8601String(),
                        'result' => $resultData,
                        'error' => $error,
                        'evidence' => $result->successful ? [
                            'source' => 'laravel_tool',
                            'tool' => $toolName,
                            'data' => $result->data,
                        ] : null,
                    ];

                    if ($timedOut || ! $result->successful) {
                        $failed = [
                            'code' => $timedOut ? 'execution_timeout' : ($result->errorCode ?? 'tool_failed'),
                            'message' => $error ?? 'La herramienta falló.',
                        ];
                        break 2;
                    }
                    $this->recovery->markCompleted($checkpoint);
                }
                $completedSteps[] = $stepId;
            }

            if ($failed !== null) {
                $failed['recovery'] = $this->recovery->rollbackRun($run);
                $run->update([
                    'status' => 'failed',
                    'error' => "{$failed['code']}: {$failed['message']}",
                    'result' => [
                        'plan_id' => $plan['plan_id'],
                        'executed' => $executed,
                        'failure' => $failed,
                        'recovery' => $failed['recovery'] ?? [],
                    ],
                ]);
                return [
                    'status' => 'failed',
                    'run_id' => $run->id,
                    'executed' => $executed,
                    'failure' => $failed,
                ];
            }

            $run->update([
                'status' => 'completed',
                'result' => ['plan_id' => $plan['plan_id'], 'executed' => $executed],
            ]);
            return [
                'status' => 'completed',
                'run_id' => $run->id,
                'executed' => $executed,
            ];
        } catch (Throwable $exception) {
            Log::error('Controlled Nexus execution failed.', [
                'run_id' => $run->id,
                'plan_id' => $plan['plan_id'],
                'session_id' => $sessionId,
                'exception' => $exception,
            ]);
            $recovery = $this->recovery->rollbackRun($run);
            $run->update([
                'status' => 'failed',
                'error' => 'execution_failed: '.$exception->getMessage(),
                'result' => [
                    'plan_id' => $plan['plan_id'],
                    'executed' => $executed,
                    'recovery' => $recovery,
                ],
            ]);
            return [
                'status' => 'failed',
                'run_id' => $run->id,
                'executed' => $executed,
                'failure' => [
                    'code' => 'execution_failed',
                    'message' => $exception->getMessage(),
                    'recovery' => $recovery,
                ],
            ];
        }
    }

    /** @return list<string> */
    private function validatePlan(array $plan): array
    {
        $errors = [];
        foreach (['plan_id', 'objective', 'steps', 'required_tools', 'required_permissions'] as $field) {
            if (! array_key_exists($field, $plan)) {
                $errors[] = "Falta el campo {$field}.";
            }
        }
        if (($plan['executed'] ?? false) === true) {
            $errors[] = 'El plan ya está marcado como ejecutado.';
        }
        if (($plan['status'] ?? null) !== 'ready_for_authorization') {
            $errors[] = 'El plan no está listo para autorización.';
        }
        if (! is_array($plan['steps'] ?? null) || $plan['steps'] === []) {
            $errors[] = 'El plan debe contener pasos.';
        }
        foreach (($plan['steps'] ?? []) as $step) {
            if (! is_array($step) || ! is_string($step['step_id'] ?? null) || ! is_array($step['tools'] ?? null)) {
                $errors[] = 'Cada paso debe tener step_id y tools.';
                continue;
            }
            foreach ($step['tools'] as $toolName) {
                if (! is_string($toolName)) {
                    $errors[] = "El paso [{$step['step_id']}] contiene una herramienta inválida.";
                    continue;
                }
                if (! in_array($toolName, $plan['required_tools'], true)) {
                    $errors[] = "La herramienta [{$toolName}] no está autorizada por required_tools.";
                    continue;
                }
                try {
                    $this->tools->get($toolName);
                } catch (Throwable) {
                    $errors[] = "La herramienta [{$toolName}] no está registrada.";
                }
            }
        }
        return $errors;
    }

    private function timedOut(float $started, int $timeout): bool
    {
        return $timeout <= 0 || microtime(true) - $started > $timeout;
    }
}
