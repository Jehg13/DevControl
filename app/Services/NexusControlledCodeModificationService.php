<?php

namespace App\Services;

use App\Models\NexusRun;
use App\Models\NexusToolCall;
use App\Models\User;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NexusControlledCodeModificationService
{
    private readonly NexusCognitiveSecurityBoundary $boundary;

    public function __construct(
        private readonly NexusToolRegistry $tools,
        ?NexusCognitiveSecurityBoundary $boundary = null,
    )
    {
        $this->boundary = $boundary ?? new NexusCognitiveSecurityBoundary($tools);
    }

    /** @param array<string, mixed> $plan */
    public function execute(
        array $plan,
        ?User $user,
        string $sessionId,
        bool $confirmed,
        array $parameters,
    ): array {
        $errors = array_merge(
            $this->boundary->validateControlledPlan($plan, $user?->id, $confirmed),
            $this->validatePlan($plan, $user, $confirmed, $parameters)
        );
        if ($errors !== []) {
            return ['status' => 'rejected', 'error' => ['code' => 'modification_rejected', 'message' => implode('; ', $errors)]];
        }

        $run = NexusRun::create([
            'usuario_id' => $user->id,
            'source' => 'controlled_code_modification',
            'message' => (string) $plan['objective'],
            'status' => 'running',
            'steps' => 0,
            'context' => ['session_id' => $sessionId, 'plan_id' => $plan['plan_id'], 'confirmed' => true],
        ]);
        $snapshot = null;

        try {
            $modify = $this->call($run, 'nexus.code.modify', $parameters, $user, $sessionId, 'apply');
            if (! $modify['result']->successful) {
                return $this->fail($run, $modify['result']->errorCode ?? 'modification_failed', $modify['result']->error ?? 'La modificación falló.');
            }
            $snapshot = $modify['result']->data['rollback'] ?? null;
            $path = (string) ($modify['result']->data['path'] ?? $parameters['path']);

            $validation = $this->call(
                $run,
                'nexus.code.validate',
                ['files' => $parameters['validation_files'] ?? [$path]],
                $user,
                $sessionId,
                'test',
            );
            if (! $validation['result']->successful) {
                $this->rollback($snapshot);
                return $this->fail(
                    $run,
                    'validation_failed_rolled_back',
                    'Las pruebas fallaron; el archivo fue restaurado.',
                    [
                        'validation' => $validation['result']->toArray(),
                        'rollback' => ['performed' => true, 'path' => $path],
                    ],
                );
            }

            $result = [
                'status' => 'completed',
                'run_id' => $run->id,
                'plan_id' => $plan['plan_id'],
                'diff' => $modify['result']->data['diff'],
                'validation' => $validation['result']->toArray(),
                'rollback' => ['available' => true, 'path' => $path],
            ];
            $run->update(['status' => 'completed', 'result' => $result]);
            return $result;
        } catch (Throwable $exception) {
            Log::error('Controlled code modification failed.', ['run_id' => $run->id, 'exception' => $exception]);
            if (is_array($snapshot)) {
                $this->rollback($snapshot);
            }
            return $this->fail($run, 'modification_failed', $exception->getMessage());
        }
    }

    private function call(NexusRun $run, string $tool, array $parameters, User $user, string $sessionId, string $step): array
    {
        $started = now();
        $call = NexusToolCall::create([
            'nexus_run_id' => $run->id,
            'sequence' => $run->toolCalls()->count() + 1,
            'tool_name' => $tool,
            'arguments' => ['user_id' => $user->id, 'session_id' => $sessionId, 'plan_id' => $run->context['plan_id'], 'step_id' => $step, 'parameters' => $parameters],
            'status' => 'running',
            'started_at' => $started,
        ]);
        $result = $this->tools->execute($tool, $parameters, new NexusToolContext(user: $user, source: 'controlled_code_modification', confirmed: true));
        $call->update([
            'status' => $result->successful ? 'succeeded' : 'failed',
            'result' => ['evidence' => $result->successful ? ['source' => 'laravel_tool', 'tool' => $tool, 'data' => $result->data] : null, 'result' => $result->toArray()],
            'error' => $result->successful ? null : $result->error,
            'finished_at' => now(),
        ]);
        return ['result' => $result];
    }

    private function rollback(?array $snapshot): void
    {
        if (! is_array($snapshot) || ! isset($snapshot['path'], $snapshot['original_content'])) {
            return;
        }
        $path = realpath(base_path((string) $snapshot['path']));
        if ($path !== false
            && is_file($path)
            && isset($snapshot['modified_sha256'])
            && hash_file('sha256', $path) === $snapshot['modified_sha256']) {
            File::put($path, (string) $snapshot['original_content']);
        }
    }

    private function validatePlan(array $plan, ?User $user, bool $confirmed, array $parameters): array
    {
        $errors = [];
        if (! $user || $user->rol !== 'admin') {
            $errors[] = 'Se requiere un usuario administrador.';
        }
        if (! $confirmed) {
            $errors[] = 'Se requiere confirmación explícita.';
        }
        if (($plan['status'] ?? null) !== 'ready_for_authorization' || ($plan['executed'] ?? false)) {
            $errors[] = 'El plan no está listo para ejecución.';
        }
        if (! in_array('nexus.code.modify', $plan['required_tools'] ?? [], true)
            || ! in_array('nexus.code.modify', $plan['required_permissions'] ?? [], true)) {
            $errors[] = 'El plan no declara explícitamente la herramienta y el permiso de modificación.';
        }
        if (! isset($parameters['path'], $parameters['expected_sha256'], $parameters['new_content'])) {
            $errors[] = 'La operación requiere path, expected_sha256 y new_content.';
        }
        return $errors;
    }

    private function fail(NexusRun $run, string $code, string $message, array $evidence = []): array
    {
        $result = [
            'status' => 'failed',
            'run_id' => $run->id,
            'error' => ['code' => $code, 'message' => $message],
            'evidence' => $evidence,
        ];
        $run->update(['status' => 'failed', 'result' => $result, 'error' => "{$code}: {$message}"]);
        return $result;
    }
}
