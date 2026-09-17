<?php

namespace App\Services;

use App\Models\Bug;
use App\Models\Incidente;
use App\Models\NexusRun;
use App\Models\Actualizacion;
use Illuminate\Support\Collection;

final class NexusMonitoringIntelligenceService
{
    public function detect(?int $projectId = null, int $hours = 72): array
    {
        $windowStart = now()->subHours($hours);

        $incidents = Incidente::query()
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->where('fecha_detectado', '>=', $windowStart->toDateString())
            ->orderBy('fecha_detectado', 'desc')
            ->get();

        $bugs = Bug::query()
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->where('fecha_detectado', '>=', $windowStart->toDateString())
            ->orderBy('fecha_detectado', 'desc')
            ->get();

        $updates = Actualizacion::query()
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->where('fecha', '>=', $windowStart->toDateString())
            ->orderBy('fecha', 'desc')
            ->get();

        $runs = NexusRun::query()
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at', 'desc')
            ->get();

        $baseline = $this->baseline($projectId, $hours);
        $alerts = [];

        if ($incidents->count() > 0) {
            $priorityHigh = $incidents->where('prioridad', 'Alta')->count();
            if ($priorityHigh > 0) {
                $alerts[] = $this->alert(
                    'open_high_priority_incidents',
                    'Hay incidentes de prioridad alta abiertos en la ventana observada.',
                    [
                        'incident_count' => $incidents->count(),
                        'priority_high_count' => $priorityHigh,
                    ],
                    $projectId,
                    'medium',
                    'La presencia de varios incidentes abiertos puede degradar la estabilidad del proyecto.',
                    0.78,
                );
            }
        }

        if ($bugs->count() > 0 && $bugs->count() > ($baseline['bug_count'] ?? 0) * 2) {
            $alerts[] = $this->alert(
                'bug_spike',
                'El número de bugs en la ventana observada supera la línea base.',
                [
                    'bug_count' => $bugs->count(),
                    'baseline_bug_count' => $baseline['bug_count'] ?? 0,
                ],
                $projectId,
                'high',
                'Aumento de errores operativos y posible regresión funcional.',
                0.8,
            );
        }

        if ($runs->where('status', 'failed')->count() > 0 && $runs->where('status', 'failed')->count() > ($baseline['failed_run_count'] ?? 0) * 2) {
            $alerts[] = $this->alert(
                'nexus_run_failures',
                'Las ejecuciones de Nexus registran un aumento anormal de fallos.',
                [
                    'failed_runs' => $runs->where('status', 'failed')->count(),
                    'baseline_failed_runs' => $baseline['failed_run_count'] ?? 0,
                ],
                $projectId,
                'medium',
                'Puede indicar regresión o degradación en la tubería técnica.',
                0.74,
            );
        }

        return [
            'hours' => $hours,
            'baseline' => $baseline,
            'incidents' => $incidents->map(fn ($item) => ['id' => $item->id, 'title' => $item->titulo, 'state' => $item->estado, 'date' => $item->fecha_detectado])->all(),
            'bugs' => $bugs->map(fn ($item) => ['id' => $item->id, 'title' => $item->titulo, 'state' => $item->estado, 'date' => $item->fecha_detectado])->all(),
            'updates' => $updates->map(fn ($item) => ['id' => $item->id, 'title' => $item->titulo, 'date' => $item->fecha, 'commit' => $item->commit])->all(),
            'alerts' => $alerts,
            'anomaly_count' => count($alerts),
            'read_only' => true,
        ];
    }

    private function baseline(?int $projectId = null, int $hours = 72): array
    {
        $historicalStart = now()->subDays(30);

        $incidents = Incidente::query()
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->where('fecha_detectado', '>=', $historicalStart->toDateString())
            ->get();

        $bugs = Bug::query()
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->where('fecha_detectado', '>=', $historicalStart->toDateString())
            ->get();

        $failedRuns = NexusRun::query()
            ->where('created_at', '>=', $historicalStart)
            ->where('status', 'failed')
            ->count();

        return [
            'incident_count' => $incidents->count(),
            'bug_count' => $bugs->count(),
            'failed_run_count' => $failedRuns,
            'baseline_window_days' => 30,
        ];
    }

    private function alert(
        string $type,
        string $whatHappened,
        array $evidence,
        ?int $projectId,
        string $severity,
        string $impact,
        float $confidence,
    ): array {
        return [
            'type' => $type,
            'what_happened' => $whatHappened,
            'evidence' => $evidence,
            'when' => now()->toIso8601String(),
            'where' => $projectId !== null ? 'project:'.$projectId : 'workspace',
            'severity' => $severity,
            'confidence' => round($confidence, 2),
            'possible_impact' => $impact,
            'status' => 'alert',
            'read_only' => true,
        ];
    }
}
