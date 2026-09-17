<?php

namespace App\Services;

use App\Models\NexusRecoveryCheckpoint;
use App\Models\NexusRun;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Throwable;

final class NexusEngineeringRecoveryService
{
    public function checkpoint(
        NexusRun $run,
        string $operationKey,
        string $operationType,
        array $scope,
        ?array $snapshot = null,
    ): NexusRecoveryCheckpoint {
        return NexusRecoveryCheckpoint::create([
            'nexus_run_id' => $run->id,
            'operation_key' => $operationKey,
            'operation_type' => $operationType,
            'scope' => $scope,
            'snapshot' => $snapshot,
            'status' => 'created',
            'rollback_status' => 'not_requested',
        ]);
    }

    public function snapshotFile(string $relativePath): array
    {
        $path = $this->safePath($relativePath);
        if ($path === null) {
            throw new \InvalidArgumentException('El archivo del checkpoint está fuera del workspace.');
        }

        $exists = File::exists($path) && File::isFile($path);

        return [
            'path' => $relativePath,
            'exists' => $exists,
            'original_content' => $exists ? File::get($path) : null,
            'original_sha256' => $exists ? hash_file('sha256', $path) : null,
        ];
    }

    public function registerModifiedSnapshot(
        NexusRecoveryCheckpoint $checkpoint,
        array $rollbackSnapshot,
    ): NexusRecoveryCheckpoint {
        $declaredPath = $checkpoint->scope['path'] ?? null;
        $snapshotPath = $rollbackSnapshot['path'] ?? $declaredPath;
        if ($declaredPath !== null && $snapshotPath !== $declaredPath) {
            throw new \RuntimeException('El rollback no coincide con el alcance del checkpoint.');
        }
        $snapshot = $checkpoint->snapshot ?? [];
        $checkpoint->update([
            'snapshot' => array_merge($snapshot, [
                'path' => $snapshotPath ?? ($snapshot['path'] ?? null),
                'exists' => $rollbackSnapshot['exists'] ?? true,
                'original_content' => $rollbackSnapshot['original_content'] ?? null,
                'original_sha256' => $rollbackSnapshot['original_sha256'] ?? null,
                'modified_sha256' => $rollbackSnapshot['modified_sha256'] ?? null,
            ]),
            'status' => 'state_changed',
        ]);

        return $checkpoint->fresh();
    }

    public function markCompleted(NexusRecoveryCheckpoint $checkpoint): void
    {
        $checkpoint->update(['status' => 'completed']);
    }

    public function rollback(NexusRecoveryCheckpoint $checkpoint): array
    {
        $checkpoint->update(['rollback_status' => 'requested']);
        $snapshot = $checkpoint->snapshot ?? [];

        try {
            if (! isset($snapshot['path'], $snapshot['exists'])) {
                $result = ['status' => 'not_reversible', 'reason' => 'checkpoint_without_snapshot'];
                $checkpoint->update(['rollback_status' => 'not_reversible', 'rollback_result' => $result]);
                return $result;
            }

            $path = $this->safePath((string) $snapshot['path']);
            if ($path === null) {
                throw new \RuntimeException('El checkpoint apunta fuera del workspace.');
            }

            $currentExists = File::exists($path) && File::isFile($path);
            $currentHash = $currentExists ? hash_file('sha256', $path) : null;
            $expectedModifiedHash = $snapshot['modified_sha256'] ?? null;
            if ($expectedModifiedHash !== null && $currentHash !== $expectedModifiedHash) {
                throw new \RuntimeException('El archivo cambió después del checkpoint; rollback detenido.');
            }

            if (($snapshot['exists'] ?? false) === true) {
                File::put($path, (string) ($snapshot['original_content'] ?? ''));
            } elseif ($currentExists) {
                File::delete($path);
            }

            $verifiedExists = File::exists($path) && File::isFile($path);
            $verifiedHash = $verifiedExists ? hash_file('sha256', $path) : null;
            if ($verifiedExists !== (($snapshot['exists'] ?? false) === true)
                || (($snapshot['original_sha256'] ?? null) !== $verifiedHash)) {
                throw new \RuntimeException('El rollback no pudo verificarse.');
            }

            $result = [
                'status' => 'rolled_back',
                'path' => $snapshot['path'],
                'verified' => true,
                'timestamp' => now()->toIso8601String(),
            ];
            $checkpoint->update(['rollback_status' => 'rolled_back', 'rollback_result' => $result]);
            return $result;
        } catch (Throwable $exception) {
            $result = ['status' => 'rollback_failed', 'verified' => false, 'error' => $exception->getMessage()];
            $checkpoint->update([
                'rollback_status' => 'failed',
                'rollback_result' => $result,
                'error' => $exception->getMessage(),
            ]);
            return $result;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function rollbackRun(NexusRun $run): array
    {
        return $run->recoveryCheckpoints()
            ->whereIn('rollback_status', ['not_requested', 'requested'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (NexusRecoveryCheckpoint $checkpoint): array => $this->rollback($checkpoint))
            ->values()
            ->all();
    }

    public function rollbackExplicitly(
        NexusRun $run,
        ?User $user,
        string $sessionId,
        bool $confirmed,
    ): array {
        if (! $user || $user->rol !== 'admin') {
            return ['status' => 'rejected', 'error' => 'Se requiere un usuario administrador.'];
        }
        if (! $confirmed) {
            return ['status' => 'rejected', 'error' => 'El rollback requiere confirmación explícita.'];
        }
        if (($run->context['session_id'] ?? null) !== $sessionId) {
            return ['status' => 'rejected', 'error' => 'La sesión no coincide con la operación.'];
        }
        if (! in_array($run->status, ['failed', 'stopped', 'completed'], true)) {
            return ['status' => 'rejected', 'error' => 'La operación todavía está en ejecución.'];
        }

        $results = $this->rollbackRun($run);
        $successful = collect($results)->every(
            fn (array $result): bool => in_array($result['status'], ['rolled_back', 'not_reversible'], true)
        );
        $result = [
            'status' => $successful ? 'rolled_back' : 'rollback_failed',
            'run_id' => $run->id,
            'session_id' => $sessionId,
            'results' => $results,
            'verified' => $successful,
            'timestamp' => now()->toIso8601String(),
        ];
        $run->update([
            'status' => $successful ? 'rolled_back' : 'recovery_failed',
            'result' => array_merge($run->result ?? [], ['explicit_recovery' => $result]),
        ]);

        return $result;
    }

    private function safePath(string $relativePath): ?string
    {
        $root = realpath(base_path());
        $candidate = realpath(base_path($relativePath));
        if ($root === false || $candidate === false) {
            return null;
        }
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/').'/';
        $normalizedCandidate = str_replace('\\', '/', $candidate);

        return str_starts_with($normalizedCandidate.'/', $normalizedRoot)
            ? $candidate
            : null;
    }
}
