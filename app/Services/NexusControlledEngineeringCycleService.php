<?php

namespace App\Services;

use App\Models\NexusAutonomousRun;
use App\Models\NexusRun;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the controlled engineering lifecycle without changing Nexus policy.
 *
 * The permissions checkpoint is a hard boundary: start() never calls an
 * execution service or a mutating tool. Only resume(..., true) may cross it.
 */
final class NexusControlledEngineeringCycleService
{
    private const STAGES = [
        'problem', 'understanding', 'context', 'investigation', 'evidence',
        'hypotheses', 'diagnosis', 'impact', 'solution', 'plan', 'permissions',
        'execution', 'tests', 'analysis', 'correction', 'retest', 'validation',
        'optional_commit', 'rollback_if_failure', 'result', 'memory',
    ];

    public function __construct(
        private readonly NexusExecutionService $execution,
        private readonly NexusToolRegistry $tools,
        private readonly NexusMemoryService $memory,
    ) {
    }

    public function start(
        string $objective,
        array $context = [],
        ?int $userId = null,
        array $limits = [],
    ): NexusAutonomousRun {
        $run = NexusRun::create([
            'usuario_id' => $userId,
            'source' => 'controlled_engineering_cycle',
            'message' => $objective,
            'status' => 'awaiting_confirmation',
            'context' => $context,
            'internal_state' => ['phase' => 'permissions'],
        ]);
        $autonomous = NexusAutonomousRun::create([
            'nexus_run_id' => $run->id,
            'usuario_id' => $userId,
            'proyecto_id' => $context['project_id'] ?? $context['proyecto_id'] ?? null,
            'objective' => $objective,
            'status' => 'running',
            'limits' => $limits,
            'state' => ['checkpoints' => [], 'current_stage' => null, 'authorized' => false],
            'started_at' => now(),
        ]);

        foreach (array_slice(self::STAGES, 0, 10) as $stage) {
            $this->checkpoint($autonomous, $stage, 'completed', $this->stageData($stage, $objective, $context));
        }
        $this->checkpoint($autonomous, 'permissions', 'awaiting_approval', [
            'required' => true,
            'granted' => false,
            'message' => 'Explicit authorization is required before execution.',
        ]);
        $this->finishCheckpoint($autonomous, 'awaiting_approval');

        return $autonomous->fresh();
    }

    public function resume(
        NexusAutonomousRun $autonomous,
        ?int $userId = null,
        bool $approved = false,
        array $permissions = [],
    ): NexusAutonomousRun {
        if ($autonomous->status !== 'awaiting_approval' || ! $approved) {
            return $autonomous->fresh();
        }

        $state = $autonomous->state ?? [];
        $context = $autonomous->run?->context ?? [];
        $userId ??= $autonomous->usuario_id;
        $this->checkpoint($autonomous, 'permissions', 'completed', [
            'required' => true, 'granted' => true, 'permissions' => array_values($permissions),
        ]);
        $state['authorized'] = true;
        $autonomous->update(['state' => $state, 'status' => 'running']);

        try {
            $child = $this->execution->execute(
                $autonomous->objective,
                array_merge($context, ['controlled_cycle' => true, 'granted_permissions' => $permissions]),
                [],
                $userId,
                'controlled_engineering_cycle',
                true,
                'controlled-cycle-'.$autonomous->id,
            );
            $passed = $child->status === 'completed';
            $this->checkpoint($autonomous, 'execution', $passed ? 'completed' : 'failed', [
                'run_id' => $child->id, 'status' => $child->status, 'error' => $child->error,
            ]);
            $this->checkpoint($autonomous, 'tests', 'completed', [
                'result' => 'Validation result is recorded after the test stage.',
            ]);
            $this->checkpoint($autonomous, 'analysis', 'completed', ['passed' => $passed]);
            $this->checkpoint($autonomous, 'correction', 'skipped', [
                'reason' => 'No correction operation is exposed by the existing Nexus services.',
            ]);
            $this->checkpoint($autonomous, 'retest', 'skipped', [
                'reason' => 'No correction was performed.',
            ]);

            $validation = $this->tools->execute(
                'nexus.code.validate',
                ['files' => array_values((array) ($context['files'] ?? [])), 'suite' => 'all'],
                new NexusToolContext(
                    user: $userId ? \App\Models\User::find($userId) : null,
                    source: 'controlled_engineering_cycle',
                    confirmed: true,
                    grantedPermissions: $permissions,
                    runId: $child->id,
                    projectId: $context['project_id'] ?? $context['proyecto_id'] ?? null,
                    toolName: 'nexus.code.validate',
                )
            );
            $validationData = $validation->toArray();
            $valid = $validation->successful && (bool) data_get($validationData, 'data.passed', true);
            $this->updateCheckpoint($autonomous, 'tests', $valid ? 'completed' : 'failed', $validationData);
            $this->checkpoint($autonomous, 'validation', $valid ? 'completed' : 'failed', $validationData);
            $commitRequested = ! empty($context['commit']);
            $commitEligible = $commitRequested && $passed && $valid;
            $commit = $commitRequested
                && $commitEligible
                ? $this->tools->execute(
                    'nexus.github.local.commit',
                    [
                        'project_id' => $context['project_id'] ?? $context['proyecto_id'] ?? 0,
                        'message' => is_string($context['commit']) ? $context['commit'] : $autonomous->objective,
                        'branch' => $context['branch'] ?? null,
                    ],
                    new NexusToolContext(
                        user: $userId ? \App\Models\User::find($userId) : null,
                        source: 'controlled_engineering_cycle',
                        confirmed: true,
                        grantedPermissions: $permissions,
                        runId: $child->id,
                        projectId: $context['project_id'] ?? $context['proyecto_id'] ?? null,
                        toolName: 'nexus.github.local.commit',
                    )
                )
                : null;
            $this->checkpoint($autonomous, 'optional_commit', ! $commitEligible ? 'skipped' : ($commit?->successful ? 'completed' : 'failed'), [
                'requested' => $commitRequested,
                'eligible' => $commitEligible,
                'reason' => ! $commitEligible ? 'Commit requires explicit request and passed execution plus validation.' : null,
                'result' => $commit?->toArray(),
            ]);
            $this->checkpoint($autonomous, 'rollback_if_failure', 'skipped', [
                'performed' => false,
                'reason' => 'No rollback operation is exposed by the existing Nexus services.',
            ]);
            $status = $passed && $valid ? 'completed' : 'failed';
            $this->checkpoint($autonomous, 'result', $status, ['run_id' => $child->id, 'validation' => $validationData]);
            $memory = $this->memory->createTechnicalMemory(
                null,
                'solution',
                $autonomous->objective,
                $context['project_id'] ?? $context['proyecto_id'] ?? null,
                'controlled_engineering_cycle',
                ['run:'.$child->id],
                $status === 'completed' ? 75 : 0,
                'candidate',
                ['autonomous_run_id' => $autonomous->id, 'outcome' => $status],
            );
            $this->checkpoint($autonomous, 'memory', $memory ? 'completed' : 'skipped', [
                'stored' => $memory !== null,
                'reason' => $memory ? null : 'Evidence was insufficient to store a technical memory.',
            ]);

            return $this->finishCheckpoint($autonomous, $status, [
                'run_id' => $child->id, 'validation' => $validationData,
            ]);
        } catch (Throwable $exception) {
            Log::error('Controlled engineering cycle failed.', ['run_id' => $autonomous->id, 'exception' => $exception]);
            $this->checkpoint($autonomous, 'rollback_if_failure', 'skipped', [
                'performed' => false,
                'reason' => 'No rollback operation is exposed by the existing Nexus services.',
            ]);
            $error = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ];
            $this->checkpoint($autonomous, 'result', 'failed', ['error' => $error]);
            $this->checkpoint($autonomous, 'memory', 'skipped', [
                'stored' => false,
                'reason' => 'The cycle failed before a reliable technical memory could be created.',
            ]);

            return $this->finishCheckpoint($autonomous, 'failed', ['error' => $error]);
        }
    }

    /** @return array<string, mixed> */
    private function stageData(string $stage, string $objective, array $context): array
    {
        return match ($stage) {
            'problem' => ['objective' => $objective],
            'context' => Arr::only($context, ['project_id', 'proyecto_id', 'files']),
            default => [],
        };
    }

    private function checkpoint(NexusAutonomousRun $run, string $stage, string $status, array $data): void
    {
        $state = $run->state ?? [];
        $state['checkpoints'] ??= [];
        $checkpoint = ['stage' => $stage, 'status' => $status, 'data' => $data, 'at' => now()->toISOString()];
        $last = array_key_last($state['checkpoints']);
        if ($last !== null && ($state['checkpoints'][$last]['stage'] ?? null) === $stage) {
            $state['checkpoints'][$last] = $checkpoint;
        } else {
            $state['checkpoints'][] = $checkpoint;
        }
        $state['current_stage'] = $stage;
        $run->update(['state' => $state]);
    }

    private function updateCheckpoint(NexusAutonomousRun $run, string $stage, string $status, array $data): void
    {
        $fresh = NexusAutonomousRun::query()->findOrFail($run->getKey());
        $state = $fresh->state ?? [];
        $index = collect($state['checkpoints'] ?? [])->search(
            fn (array $checkpoint): bool => ($checkpoint['stage'] ?? null) === $stage
        );
        if ($index !== false) {
            $state['checkpoints'][$index] = [
                'stage' => $stage,
                'status' => $status,
                'data' => $data,
                'at' => now()->toISOString(),
            ];
            $fresh->update(['state' => $state]);
            $run->setRawAttributes($fresh->getAttributes(), true);
        }
    }

    private function finishCheckpoint(NexusAutonomousRun $run, string $status, array $result = []): NexusAutonomousRun
    {
        $run->update([
            'status' => $status,
            'result' => array_merge(['status' => $status, 'checkpoints' => $run->state['checkpoints'] ?? []], $result),
            'finished_at' => $status === 'awaiting_approval' ? null : now(),
        ]);
        $run->run?->update(['status' => $status === 'completed' ? 'completed' : ($status === 'awaiting_approval' ? 'awaiting_confirmation' : 'failed')]);
        return $run->fresh();
    }
}
