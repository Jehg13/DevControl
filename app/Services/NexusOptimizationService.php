<?php

namespace App\Services;

use App\Models\NexusOptimizationProposal;
use App\Models\NexusPerformanceMetric;
use App\Models\NexusRun;
use Illuminate\Support\Facades\DB;

class NexusOptimizationService
{
    public function record(NexusRun $run): NexusPerformanceMetric
    {
        $calls = $run->toolCalls()->get();
        $counts = $calls->groupBy('tool_name')->map->count();
        $redundant = $counts->filter(fn (int $count) => $count > 1)->keys()->values()->all();
        $duration = $run->created_at && $run->updated_at
            ? max(0, $run->updated_at->diffInMilliseconds($run->created_at))
            : 0;
        $reflection = $run->reflection ?? [];

        return NexusPerformanceMetric::updateOrCreate(
            ['nexus_run_id' => $run->id],
            [
                'objective_hash' => hash('sha256', $run->message),
                'project_id' => $run->context['project_id'] ?? $run->context['proyecto_id'] ?? null,
                'status' => $run->status,
                'duration_ms' => $duration,
                'steps' => $run->steps,
                'tool_calls' => $calls->count(),
                'failed_calls' => $calls->filter(fn ($call) => $call->status === 'failed')->count(),
                'retries' => (int) data_get($run->result, 'retries', data_get($run->context, 'retries', 0)),
                'memory_items' => count((array) ($reflection['new_information'] ?? [])),
                'redundant_tools' => $redundant,
                'metrics' => [
                    'tools' => $counts->all(),
                    'goal_fulfilled' => (bool) ($reflection['goal_fulfilled'] ?? $run->status === 'completed'),
                    'error_patterns' => $reflection['error_patterns'] ?? [],
                ],
            ]
        );
    }

    public function report(?int $projectId = null, int $limit = 100): array
    {
        $metrics = NexusPerformanceMetric::query()
            ->when($projectId !== null, fn ($query) => $query->where('project_id', $projectId))
            ->latest()
            ->limit(min($limit, (int) config('nexus.optimization.report_limit', 100)))
            ->get();

        $toolUsage = [];
        foreach ($metrics as $metric) {
            foreach ((array) data_get($metric->metrics, 'tools', []) as $tool => $count) {
                $toolUsage[$tool] = ($toolUsage[$tool] ?? 0) + $count;
            }
        }

        $redundantTools = [];
        foreach ($metrics as $metric) {
            $tools = is_string($metric->redundant_tools)
                ? json_decode($metric->redundant_tools, true, 512, JSON_THROW_ON_ERROR)
                : (array) $metric->redundant_tools;
            foreach ($tools as $tool) {
                $redundantTools[$tool] = ($redundantTools[$tool] ?? 0) + 1;
            }
        }

        return [
            'runs' => $metrics->count(),
            'average_duration_ms' => (int) round($metrics->avg('duration_ms') ?? 0),
            'average_steps' => round((float) ($metrics->avg('steps') ?? 0), 2),
            'success_rate' => $metrics->count() > 0
                ? round($metrics->where('status', 'completed')->count() / $metrics->count(), 3)
                : 0,
            'total_errors' => (int) $metrics->sum('failed_calls'),
            'tool_usage' => $toolUsage,
            'redundant_tools' => $redundantTools,
            'metrics' => $metrics,
        ];
    }

    public function propose(?int $projectId = null): array
    {
        $report = $this->report($projectId);
        $proposals = [];

        if (($report['average_steps'] ?? 0) >= (float) config('nexus.optimization.step_threshold', 6)) {
            $proposals[] = $this->upsertProposal($projectId, 'step_reduction', 'plan', 
                'Las ejecuciones similares usan un promedio elevado de pasos.',
                'Revisar pasos redundantes y probar un plan más corto antes de cambiar el flujo base.',
                ['average_steps' => $report['average_steps']], 'Reducir pasos sin perder validación.', 'medium');
        }
        foreach (($report['redundant_tools'] ?? []) as $tool => $count) {
            if ($count >= 1) {
                $proposals[] = $this->upsertProposal($projectId, 'redundant_tool', 'tools',
                    "La herramienta {$tool} aparece repetida en ejecuciones.",
                    "Evaluar si la herramienta puede sustituirse por una consulta única.",
                    ['tool' => $tool, 'repetitions' => $count], 'Menos llamadas redundantes.', 'low');
            }
        }
        if (($report['total_errors'] ?? 0) > 0) {
            $proposals[] = $this->upsertProposal($projectId, 'recurring_errors', 'reliability',
                'Se detectaron errores en ejecuciones recientes.',
                'Analizar patrones de error y actualizar el Planner únicamente después de validación controlada.',
                ['total_errors' => $report['total_errors']], 'Reducir reintentos fallidos.', 'high');
        }

        return $proposals;
    }

    public function approve(NexusOptimizationProposal $proposal, int $userId): NexusOptimizationProposal
    {
        $proposal->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $proposal->fresh();
    }

    public function validateProposal(NexusOptimizationProposal $proposal, array $validation): NexusOptimizationProposal
    {
        $proposal->update([
            'status' => ($validation['improved'] ?? false) ? 'validated' : 'rejected',
            'validation' => $validation,
        ]);

        return $proposal->fresh();
    }

    private function upsertProposal(?int $projectId, string $key, string $category, string $observation, string $proposal, array $evidence, string $impact, string $risk): NexusOptimizationProposal
    {
        $record = NexusOptimizationProposal::firstOrNew([
            'proposal_key' => $key.':'.($projectId ?? 'global'),
        ]);
        $record->fill([
            'project_id' => $projectId,
            'category' => $category,
            'observation' => $observation,
            'proposal' => $proposal,
            'evidence' => $evidence,
            'expected_impact' => $impact,
            'risk' => $risk,
        ]);
        if (! $record->exists) {
            $record->status = 'proposed';
        }
        $record->save();

        return $record;
    }
}
