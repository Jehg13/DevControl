<?php

namespace App\Services;

use App\Models\Actualizacion;
use App\Models\Bug;
use App\Models\Incidente;
use Illuminate\Support\Carbon;

final class NexusDeploymentIntelligenceService
{
    public function analyze(?int $projectId = null, int $hours = 168): array
    {
        $windowStart = now()->subHours($hours);

        $updates = Actualizacion::query()
            ->when($projectId !== null, fn ($query) => $query->where('proyecto_id', $projectId))
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at', 'desc')
            ->get();

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

        $correlations = [];
        foreach ($incidents as $incident) {
            $incidentDate = $incident->fecha_detectado instanceof Carbon ? $incident->fecha_detectado : Carbon::parse($incident->fecha_detectado);
            foreach ($updates as $update) {
                $updateDate = $update->created_at instanceof Carbon ? $update->created_at : Carbon::parse($update->created_at);
                $hoursGap = abs($incidentDate->diffInHours($updateDate, false));
                $sameProject = $projectId !== null ? (int) $update->proyecto_id === (int) $projectId : (int) $update->proyecto_id === (int) $incident->proyecto_id;

                if ($sameProject && $hoursGap <= 72) {
                    $confidence = min(0.82, 0.42 + (72 - $hoursGap) / 120);
                    $correlations[] = [
                        'incident_id' => $incident->id,
                        'incident_title' => $incident->titulo,
                        'update_id' => $update->id,
                        'update_title' => $update->titulo,
                        'commit' => $update->commit,
                        'project_id' => $incident->proyecto_id,
                        'hours_gap' => $hoursGap,
                        'confidence' => round($confidence, 2),
                        'status' => 'possible_correlation',
                    ];
                }
            }
        }

        $likelyCauses = [];
        foreach ($correlations as $correlation) {
            $likelyCauses[] = [
                'what' => 'Cambio reciente relacionado con la incidencia.',
                'evidence' => [
                    'incident' => $correlation['incident_title'],
                    'update' => $correlation['update_title'],
                    'commit' => $correlation['commit'],
                    'hours_gap' => $correlation['hours_gap'],
                ],
                'confidence' => $correlation['confidence'],
                'status' => 'hypothesis',
            ];
        }

        $historicalPatterns = [];
        $projectGroups = $incidents->groupBy('proyecto_id');
        foreach ($projectGroups as $projectIdValue => $items) {
            $historicalPatterns[] = [
                'project_id' => $projectIdValue,
                'incidents_count' => $items->count(),
                'bugs_count' => $bugs->where('proyecto_id', $projectIdValue)->count(),
                'pattern' => $items->count() > 1 ? 'repeated_incidents' : 'single_incident',
            ];
        }

        return [
            'window_hours' => $hours,
            'updates' => $updates->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->titulo,
                'project_id' => $item->proyecto_id,
                'date' => $item->created_at,
                'commit' => $item->commit,
            ])->all(),
            'incidents' => $incidents->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->titulo,
                'project_id' => $item->proyecto_id,
                'date' => $item->fecha_detectado,
                'state' => $item->estado,
                'priority' => $item->prioridad,
            ])->all(),
            'bugs' => $bugs->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->titulo,
                'project_id' => $item->proyecto_id,
                'date' => $item->fecha_detectado,
                'state' => $item->estado,
            ])->all(),
            'correlations' => $correlations,
            'likely_causes' => $likelyCauses,
            'historical_patterns' => $historicalPatterns,
            'probable_cause' => $likelyCauses === [] ? null : collect($likelyCauses)->sortByDesc('confidence')->first(),
            'read_only' => true,
        ];
    }
}
