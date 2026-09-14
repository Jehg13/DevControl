<?php

namespace App\Http\Controllers;

use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use App\Services\NexusReasoningService;
use App\Services\NexusExecutionService;
use App\Services\NexusMemoryService;
use App\Services\NexusProjectUnderstandingService;
use App\Services\NexusPlannerService;
use App\Services\NexusAutonomousExecutionService;
use App\Models\NexusAutonomousRun;
use App\Services\NexusInfrastructureService;
use App\Services\NexusExperienceService;
use App\Services\NexusOptimizationService;
use App\Models\NexusOptimizationProposal;
use App\Models\NexusRun;
use Illuminate\Http\Request;
use Throwable;

class NexusController extends Controller
{
    public function definitions(NexusToolRegistry $tools)
    {
        return response()->json(['herramientas' => $tools->definitions()]);
    }

    public function reason(Request $request, NexusReasoningService $reasoning, NexusMemoryService $memory)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:20000'],
            'context' => ['nullable', 'array'],
        ]);

        $conversation = $memory->conversation($request->session()->getId(), $request->user()?->id);
        $result = $reasoning->reason(
            $data['message'],
            $memory->relevantContext($conversation, $data['message'], $data['context'] ?? [])
        );

        return $result->successful
            ? response()->json($result->toArray())
            : response()->json($result->toArray(), 503);
    }

    public function execute(Request $request, NexusExecutionService $execution)
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:20000'],
            'context' => ['nullable', 'array'],
            'confirmed' => ['nullable', 'boolean'],
        ]);

        $run = $execution->execute(
            message: $data['message'],
            context: $data['context'] ?? [],
            userId: $request->user()?->id,
            source: 'http',
            confirmed: (bool) ($data['confirmed'] ?? false),
            conversationKey: $request->session()->getId(),
        );

        return response()->json([
            'run' => $run->load('toolCalls'),
        ], $run->status === 'failed' ? 503 : 200);
    }

    public function autonomous(Request $request, NexusAutonomousExecutionService $autonomous)
    {
        $data = $request->validate([
            'objective' => ['required', 'string', 'max:20000'],
            'context' => ['nullable', 'array'],
            'limits' => ['nullable', 'array'],
        ]);

        return response()->json([
            'autonomous_run' => $autonomous->start(
                $data['objective'],
                $data['context'] ?? [],
                $request->user()?->id,
                'http_autonomous',
                $data['limits'] ?? [],
            ),
        ]);
    }

    public function resumeAutonomous(
        Request $request,
        NexusAutonomousRun $autonomousRun,
        NexusAutonomousExecutionService $autonomous
    ) {
        $data = $request->validate(['approved' => ['nullable', 'boolean']]);

        return response()->json([
            'autonomous_run' => $autonomous->resume(
                $autonomousRun,
                $request->user()?->id,
                (bool) ($data['approved'] ?? false),
            ),
        ]);
    }

    public function pauseAutonomous(
        NexusAutonomousRun $autonomousRun,
        NexusAutonomousExecutionService $autonomous
    ) {
        return response()->json(['autonomous_run' => $autonomous->pause($autonomousRun)]);
    }

    public function autonomousRun(NexusAutonomousRun $autonomousRun)
    {
        return response()->json([
            'autonomous_run' => $autonomousRun->load(['run.toolCalls', 'planModel']),
        ]);
    }

    public function infrastructure(Request $request, NexusInfrastructureService $infrastructure)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'min:1'],
            'infrastructure_id' => ['nullable', 'integer', 'min:1'],
            'endpoint' => ['nullable', 'url', 'max:500'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:30'],
            'thresholds' => ['nullable', 'array'],
        ]);

        $result = $infrastructure->inspect(
            $data['project_id'] ?? null,
            $data['infrastructure_id'] ?? null,
            $data['endpoint'] ?? null,
            (int) ($data['timeout'] ?? config('nexus.infrastructure.default_timeout', 5)),
            $data['thresholds'] ?? [],
            $request->user()?->id,
        );

        return ($result['available'] ?? false)
            ? response()->json(['infrastructure' => $result])
            : response()->json($result, 404);
    }

    public function experiences(Request $request, NexusExperienceService $experiences)
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:4000'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'technologies' => ['nullable', 'array'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json([
            'experiences' => $experiences->similar(
                $data['query'],
                $data['project_id'] ?? null,
                $data['technologies'] ?? [],
                (int) ($data['limit'] ?? 5),
            ),
        ]);
    }

    public function optimizationReport(Request $request, NexusOptimizationService $optimization)
    {
        $data = $request->validate(['project_id' => ['nullable', 'integer', 'min:1']]);

        return response()->json([
            'report' => $optimization->report($data['project_id'] ?? null),
            'proposals' => $optimization->propose($data['project_id'] ?? null),
        ]);
    }

    public function approveOptimization(
        Request $request,
        NexusOptimizationProposal $proposal,
        NexusOptimizationService $optimization
    ) {
        abort_unless((bool) $request->user(), 403);

        return response()->json([
            'proposal' => $optimization->approve($proposal, (int) $request->user()->id),
        ]);
    }

    public function plan(Request $request, NexusPlannerService $planner, NexusToolRegistry $tools)
    {
        $data = $request->validate([
            'objective' => ['required', 'string', 'max:20000'],
            'context' => ['nullable', 'array'],
            'project' => ['nullable', 'array'],
            'restrictions' => ['nullable', 'array'],
            'permissions' => ['nullable', 'array'],
        ]);

        $result = $planner->create(
            $data['objective'],
            $data['context'] ?? [],
            $data['project'] ?? [],
            $data['restrictions'] ?? [],
            $data['permissions'] ?? [],
            $tools->definitions(),
        );

        return $result->successful
            ? response()->json(['plan' => $result->response?->toArray()])
            : response()->json($result->toArray(), 503);
    }

    public function run(NexusRun $run)
    {
        return response()->json(['run' => $run->load(['toolCalls', 'plan'])]);
    }

    public function memories(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:project,decision,experience,problem,solution,preference,knowledge'],
            'proyecto_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json([
            'memories' => \App\Models\NexusMemory::query()
                ->where('usuario_id', $request->user()?->id)
                ->where('status', 'active')
                ->when($data['type'] ?? null, fn ($query, $type) => $query->where('memory_type', $type))
                ->when($data['proyecto_id'] ?? null, fn ($query, $projectId) => $query->where('proyecto_id', $projectId))
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function analyses(Request $request)
    {
        $data = $request->validate([
            'proyecto_id' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json([
            'analisis' => \App\Models\NexusAnalysisResult::query()
                ->where('usuario_id', $request->user()?->id)
                ->when($data['proyecto_id'] ?? null, fn ($query, $id) => $query->where('proyecto_id', $id))
                ->when($data['tipo'] ?? null, fn ($query, $type) => $query->where('analysis_type', $type))
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function projectUnderstanding(Request $request, NexusProjectUnderstandingService $understanding)
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'min:1'],
            'path' => ['nullable', 'string', 'max:300'],
            'include_documentation' => ['nullable', 'boolean'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'understanding' => $understanding->understand(
                $data['project_id'] ?? null,
                $data['path'] ?? null,
                (bool) ($data['include_documentation'] ?? true),
                (bool) ($data['refresh'] ?? false),
                $request->user()?->id,
            ),
        ]);
    }

    public function projectQuestion(Request $request, NexusProjectUnderstandingService $understanding)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'path' => ['nullable', 'string', 'max:300'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'answer' => $understanding->query(
                $data['question'],
                $data['project_id'] ?? null,
                $data['path'] ?? null,
                (bool) ($data['refresh'] ?? false),
                $request->user()?->id,
            ),
        ]);
    }

    public function forgetMemory(Request $request, \App\Models\NexusMemory $memory, NexusMemoryService $service)
    {
        $service->forget($memory, $request->user()?->id);

        return response()->json(['ok' => true]);
    }

    public function findings(Request $request, NexusToolRegistry $tools)
    {
        $data = $request->validate([
            'proyecto_id' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable', 'string', 'max:40'],
        ]);
        try {
            $result = $tools->execute(
                'nexus.audit.findings',
                [
                    'project_id' => $data['proyecto_id'] ?? null,
                    'type' => $data['tipo'] ?? null,
                ],
                new NexusToolContext($request->user(), 'http')
            );

            if (! $result->successful) {
                return response()->json(['message' => $result->error], 503);
            }

            $health = $tools->execute(
                'nexus.audit.health',
                [],
                new NexusToolContext($request->user(), 'http')
            );

            if (! $health->successful) {
                return response()->json(['message' => $health->error], 503);
            }

            return response()->json([
                'hallazgos' => $result->data,
                'salud' => $health->data,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'No se pudieron consultar los hallazgos.'], 503);
        }
    }

    public function health(Request $request, NexusToolRegistry $tools)
    {
        $result = $tools->execute(
            'nexus.audit.health',
            [],
            new NexusToolContext($request->user(), 'http')
        );

        return $result->successful
            ? response()->json($result->data)
            : response()->json(['message' => $result->error], 503);
    }

    public function proposals(Request $request, NexusToolRegistry $tools)
    {
        $data = $request->validate([
            'proyecto_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $result = $tools->execute(
                'nexus.audit.proposals',
                ['project_id' => $data['proyecto_id'] ?? null],
                new NexusToolContext($request->user(), 'http')
            );

            if (! $result->successful) {
                return response()->json(['message' => $result->error], 503);
            }

            return response()->json([
                'propuestas' => $result->data,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'No se pudieron generar propuestas.'], 503);
        }
    }
}
