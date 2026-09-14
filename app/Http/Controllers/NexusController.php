<?php

namespace App\Http\Controllers;

use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use App\Services\NexusReasoningService;
use App\Services\NexusExecutionService;
use App\Services\NexusMemoryService;
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
