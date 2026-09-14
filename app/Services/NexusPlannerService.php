<?php

namespace App\Services;

use App\Models\NexusPlan;
use App\Models\NexusRun;
use App\Contracts\NexusModel;
use App\Exceptions\NexusModelException;
use App\Exceptions\NexusGithubException;
use App\Nexus\NexusIdentity;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;
use App\Nexus\NexusReasoningResult;
use App\Services\NexusPermissionManager;
use Illuminate\Support\Facades\Log;
use Throwable;

class NexusPlannerService
{
    public function __construct(
        private readonly NexusModel $model,
        private readonly NexusPlanService $plans,
        private readonly NexusProjectUnderstandingService $understanding,
        private readonly NexusGithubService $github,
        private readonly NexusPermissionManager $permissions,
        private readonly ?NexusExperienceService $experiences = null,
    ) {
    }

    public function create(
        string $objective,
        array $context = [],
        array $project = [],
        array $restrictions = [],
        array $permissions = [],
        array $tools = [],
    ): NexusReasoningResult {
        $projectId = $project['project_id'] ?? $project['proyecto_id'] ?? $context['project_id'] ?? $context['proyecto_id'] ?? null;
        if ($projectId !== null && ! isset($project['understanding'])) {
            $project['understanding'] = $this->understanding->understand(
                (int) $projectId,
                $project['path'] ?? null,
                true,
                false,
                null
            );
        }
        if ($projectId !== null && ! isset($project['github_repository'])) {
            try {
                $project['github_repository'] = $this->github->repository(\App\Models\Proyecto::findOrFail($projectId));
            } catch (NexusGithubException $exception) {
                $project['github_repository'] = [
                    'available' => false,
                    'error_code' => $exception->errorCode,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $planningContext = [
            'planner' => [
                'objective' => $objective,
                'project' => $project,
                'restrictions' => $restrictions,
                'permissions' => $permissions,
                'security_policy' => $this->permissions->policy(),
                'instruction' => 'Produce un plan mínimo, ejecutable y verificable. Usa 1 a 3 pasos para objetivos simples y divide objetivos complejos. No ejecutes herramientas.',
            ],
            'available_context' => $context,
            'similar_experiences' => $this->experiences?->similar(
                $objective,
                $projectId !== null ? (int) $projectId : null,
                (array) ($project['technologies'] ?? $context['technologies'] ?? []),
                (int) config('nexus.experiences.planner_limit', 5),
            ) ?? [],
        ];

        try {
            $response = $this->model->complete(new NexusModelRequest(
                identity: NexusIdentity::prompt(),
                message: 'Crea un plan estructurado para este objetivo: '.$objective,
                context: $planningContext,
                tools: $tools,
            ));

            if ($response->intent === '' || $response->message === '') {
                throw new NexusModelException('El modelo devolvió un plan incompleto.', 'invalid_plan_response');
            }

            return NexusReasoningResult::success(new NexusModelResponse(
                intent: 'create_plan',
                message: $response->message,
                toolCalls: [],
                requiresConfirmation: false,
                metadata: $response->metadata,
            ));
        } catch (NexusModelException $exception) {
            return NexusReasoningResult::failure($exception->errorCode, $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Nexus planning failed.', ['exception' => $exception]);

            return NexusReasoningResult::failure('planning_failed', 'Nexus no pudo crear el plan.');
        }
    }

    public function persist(
        NexusRun $run,
        string $objective,
        array $metadata,
        array $context = [],
    ): ?NexusPlan {
        $metadata['objective'] = $metadata['objective'] ?? $objective;
        $metadata['planner_context'] = $context;

        return $this->plans->createOrUpdate($run, $metadata, $objective);
    }

    public function advance(NexusPlan $plan, string $status, array $result = [], ?string $error = null): NexusPlan
    {
        $subtasks = $plan->subtasks ?? [];
        $index = collect($subtasks)->search(fn (array $task) => $task['status'] === 'in_progress');
        if ($index === false) {
            $index = collect($subtasks)->search(fn (array $task) => $task['status'] === 'pending'
                && $this->dependenciesCompleted($subtasks, $task['dependencies'] ?? []));
        }
        if ($index === false) {
            return $plan;
        }

        $subtasks[$index]['status'] = $status;
        $subtasks[$index]['result'] = $result ?: ($subtasks[$index]['result'] ?? null);
        $subtasks[$index]['error'] = $error;
        $subtasks[$index]['attempts'] = max(0, (int) ($subtasks[$index]['attempts'] ?? 0)) + ($status === 'in_progress' ? 1 : 0);
        $subtasks[$index]['started_at'] = $subtasks[$index]['started_at'] ?? now()->toISOString();
        $subtasks[$index]['completed_at'] = in_array($status, ['completed', 'failed', 'blocked', 'skipped'], true)
            ? now()->toISOString()
            : null;

        return $this->plans->modify($plan, [
            'subtasks' => $subtasks,
            'status' => in_array($status, ['failed', 'blocked'], true) ? 'blocked' : $plan->status,
            'reason' => 'planner_step_advanced',
        ]);
    }

    private function dependenciesCompleted(array $subtasks, array $dependencies): bool
    {
        $completed = collect($subtasks)->filter(fn (array $task) => in_array($task['status'], ['completed', 'skipped'], true))->pluck('id');

        return collect($dependencies)->every(fn ($dependency) => $completed->contains($dependency));
    }
}
