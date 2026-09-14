<?php

namespace App\Services;

use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;

final class NexusResolutionService
{
    public function __construct(
        private readonly NexusExecutionService $execution,
        private readonly NexusToolRegistry $tools,
    ) {
    }

    public function resolve(
        string $problem,
        array $diagnosis,
        array $proposal,
        array $context = [],
        bool $authorized = false,
        ?int $userId = null,
    ): array {
        $base = [
            'problem' => $problem,
            'diagnosis' => $diagnosis,
            'proposal' => $proposal,
            'read_only' => ! $authorized,
            'phases' => [],
        ];

        if (($proposal['status'] ?? null) !== 'propuesta' || ($proposal['not_confirmed'] ?? true) !== true) {
            return array_merge($base, [
                'status' => 'rejected',
                'reason' => 'La propuesta no tiene el estado requerido para autorización.',
                'phases' => $this->phases('rejected'),
            ]);
        }

        if (! $authorized) {
            return array_merge($base, [
                'status' => 'authorization_required',
                'reason' => 'La ejecución requiere autorización explícita del usuario.',
                'phases' => $this->phases('authorization_required'),
            ]);
        }

        $run = $this->execution->execute(
            $proposal['objective'] ?? $problem,
            array_merge($context, [
                'resolution' => [
                    'diagnosis' => $diagnosis,
                    'proposal' => $proposal,
                ],
                'granted_permissions' => $context['granted_permissions'] ?? $context['permissions'] ?? [],
            ]),
            [],
            $userId,
            'controlled_resolution',
            true,
            'resolution-'.hash('sha256', $problem),
        );

        $executionPassed = $run->status === 'completed';
        $files = array_values(array_filter((array) ($proposal['files'] ?? $proposal['affected_components'] ?? [])));
        $validation = $this->tools->execute(
            'nexus.code.validate',
            ['files' => $files, 'suite' => 'all'],
            new NexusToolContext(
                user: $userId ? \App\Models\User::find($userId) : null,
                source: 'controlled_resolution',
                confirmed: true,
                grantedPermissions: $context['granted_permissions'] ?? $context['permissions'] ?? ['nexus.read'],
                runId: $run->id,
                projectId: $context['project_id'] ?? $context['proyecto_id'] ?? null,
                toolName: 'nexus.code.validate',
            )
        );
        $validationData = $validation->toArray();
        $validationPassed = $validation->successful && (bool) data_get($validationData, 'data.passed', false);

        return array_merge($base, [
            'status' => $executionPassed && $validationPassed ? 'verified' : 'failed',
            'read_only' => false,
            'run_id' => $run->id,
            'execution' => [
                'status' => $run->status,
                'result' => $run->result,
                'error' => $run->error,
            ],
            'tests' => $validationData,
            'regressions' => $validationPassed ? [] : ['La validación posterior a la ejecución falló.'],
            'rollback' => [
                'available' => false,
                'performed' => false,
                'reason' => 'No existe una operación reversible registrada para esta ejecución.',
            ],
            'phases' => $this->phases($executionPassed && $validationPassed ? 'verified' : 'failed'),
        ]);
    }

    /** @return array<int, array{phase: string, status: string}> */
    private function phases(string $terminal): array
    {
        $phases = [
            'problem' => 'completed',
            'investigation' => 'completed',
            'diagnosis' => 'completed',
            'proposal' => 'completed',
            'authorization' => 'pending',
            'execution' => 'pending',
            'tests' => 'pending',
            'verification' => 'pending',
        ];

        if ($terminal === 'authorization_required' || $terminal === 'rejected') {
            $phases['authorization'] = $terminal === 'authorization_required' ? 'required' : 'rejected';
        } else {
            $phases['authorization'] = 'completed';
            $phases['execution'] = 'completed';
            $phases['tests'] = 'completed';
            $phases['verification'] = $terminal === 'verified' ? 'completed' : 'failed';
        }

        return collect($phases)
            ->map(fn (string $status, string $phase): array => ['phase' => $phase, 'status' => $status])
            ->values()
            ->all();
    }
}
