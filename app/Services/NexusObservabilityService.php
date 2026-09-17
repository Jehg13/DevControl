<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\Actualizacion;
use App\Models\Bug;
use App\Models\Incidente;
use App\Models\NexusFinding;
use App\Models\NexusRun;
use App\Models\Proyecto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

final class NexusObservabilityService
{
    /** @return array<string, mixed> */
    public function observe(?int $projectId = null, int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $activities = $this->query(Actividad::query()->where('created_at', '>=', $since), $projectId);
        $incidents = $this->query(Incidente::query()->where('created_at', '>=', $since), $projectId);
        $updates = $this->query(Actualizacion::query()->where('created_at', '>=', $since), $projectId);
        $findings = $this->query(NexusFinding::query()->where('detectado_en', '>=', $since), $projectId);
        $runs = NexusRun::query()->where('created_at', '>=', $since)->latest()->limit(500)->get();
        $logs = $this->logs($since);

        $events = collect()
            ->merge($activities->map(fn ($item) => $this->event('activity', $item->created_at, $item->toArray(), $item->proyecto_id)))
            ->merge($incidents->map(fn ($item) => $this->event('incident', $item->created_at, $item->toArray(), $item->proyecto_id)))
            ->merge($updates->map(fn ($item) => $this->event('update', $item->created_at, $item->toArray(), $item->proyecto_id)))
            ->merge($findings->map(fn ($item) => $this->event('finding', $item->detectado_en, $item->toArray(), $item->proyecto_id)))
            ->merge($runs->map(fn ($item) => $this->event('nexus_run', $item->created_at, $item->toArray(), null)))
            ->merge($logs->map(fn (array $item) => $this->event('log', $item['timestamp'], $item, $projectId)))
            ->sortBy('timestamp')
            ->values();

        return [
            'window' => ['since' => $since->toIso8601String(), 'hours' => $hours],
            'events' => $events->all(),
            'anomalies' => $this->anomalies($events, $incidents, $findings),
            'error_groups' => $this->groupErrors($logs, $findings),
            'correlations' => $this->correlate($incidents, $updates, $logs),
            'possible_causes' => $this->possibleCauses($incidents, $updates, $logs),
            'evidence' => $this->evidence($events),
            'application_state' => [
                'runs' => $runs->groupBy('status')->map->count()->all(),
                'failed_runs' => $runs->where('status', 'failed')->count(),
                'active_runs' => $runs->whereIn('status', ['running', 'pending'])->count(),
            ],
            'context_update' => [
                'observed_project_id' => $projectId,
                'event_count' => $events->count(),
                'anomaly_count' => count($this->anomalies($events, $incidents, $findings)),
                'available_until' => now()->toIso8601String(),
            ],
            'read_only' => true,
        ];
    }

    private function query($query, ?int $projectId): Collection
    {
        return $query->when($projectId, fn ($builder) => $builder->where('proyecto_id', $projectId))
            ->latest()
            ->limit(500)
            ->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function logs($since): Collection
    {
        $path = storage_path('logs/laravel.log');
        if (! File::exists($path) || ! is_readable($path)) {
            return collect();
        }

        $lines = collect(preg_split('/\R/', File::get($path)) ?: []);
        return $lines->filter(function (string $line) use ($since): bool {
            if ($line === '') {
                return false;
            }
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[^]]+)\]/', $line, $match)) {
                return $match[1] >= $since->toDateTimeString();
            }
            return true;
        })->take(-500)->map(fn (string $line): array => [
            'timestamp' => $this->logTimestamp($line),
            'message' => trim($line),
            'level' => $this->logLevel($line),
            'fingerprint' => sha1($this->normalize($line)),
        ])->values();
    }

    /** @return array<string, mixed> */
    private function event(string $source, mixed $timestamp, array $data, ?int $projectId): array
    {
        return [
            'source' => $source,
            'timestamp' => is_object($timestamp) && method_exists($timestamp, 'toIso8601String')
                ? $timestamp->toIso8601String()
                : (string) $timestamp,
            'project_id' => $projectId ?? ($data['proyecto_id'] ?? null),
            'data' => $data,
            'evidence_id' => $source.':'.($data['id'] ?? sha1(json_encode($data))),
        ];
    }

    private function anomalies(Collection $events, Collection $incidents, Collection $findings): array
    {
        $anomalies = [];
        $errors = $events->filter(fn (array $event) => in_array($event['source'], ['log', 'finding'], true)
            && preg_match('/error|exception|fatal|critical|failed|failure/i', json_encode($event['data'])));
        if ($errors->count() >= 3) {
            $anomalies[] = [
                'type' => 'error_burst',
                'severity' => 'warning',
                'description' => 'Se observaron múltiples eventos de error en la ventana consultada.',
                'count' => $errors->count(),
                'evidence_ids' => $errors->pluck('evidence_id')->values()->all(),
                'confidence' => 0.8,
            ];
        }
        $openHigh = $incidents->filter(fn ($incident) => $incident->estado !== 'Resuelto' && $incident->prioridad === 'Alta');
        if ($openHigh->isNotEmpty()) {
            $anomalies[] = [
                'type' => 'open_high_priority_incidents',
                'severity' => 'high',
                'description' => 'Existen incidentes de prioridad alta sin resolver.',
                'count' => $openHigh->count(),
                'evidence_ids' => $openHigh->map(fn ($item) => 'incident:'.$item->id)->values()->all(),
                'confidence' => 1.0,
            ];
        }
        return $anomalies;
    }

    private function groupErrors(Collection $logs, Collection $findings): array
    {
        return $logs->filter(fn (array $log) => preg_match('/error|exception|fatal|critical|failed/i', $log['message']))
            ->groupBy('fingerprint')
            ->map(fn (Collection $items, string $fingerprint): array => [
                'fingerprint' => $fingerprint,
                'count' => $items->count(),
                'sample' => $items->first()['message'],
                'sources' => ['laravel.log'],
                'evidence_ids' => $items->map(fn (array $item) => 'log:'.$item['fingerprint'])->values()->all(),
            ])->values()->all();
    }

    private function correlate(Collection $incidents, Collection $updates, Collection $logs): array
    {
        return $incidents->map(function ($incident) use ($updates, $logs): array {
            $nearby = $updates->filter(fn ($update) => $update->proyecto_id === $incident->proyecto_id
                && $incident->created_at->diffInHours($update->created_at, true) <= 48);
            $possible = $nearby->isNotEmpty();
            return [
                'incident_id' => $incident->id,
                'project_id' => $incident->proyecto_id,
                'related_updates' => $nearby->map(fn ($update) => [
                    'id' => $update->id,
                    'commit' => $update->commit,
                    'title' => $update->titulo,
                    'evidence_id' => 'update:'.$update->id,
                ])->values()->all(),
                'related_log_count' => 0,
                'possible_change_relation' => $possible,
                'confidence' => $possible ? 0.65 : 0.0,
                'conclusion_status' => $possible ? 'possible_correlation' : 'no_correlated_change_found',
            ];
        })->values()->all();
    }

    private function possibleCauses(Collection $incidents, Collection $updates, Collection $logs): array
    {
        $causes = [];
        foreach ($incidents as $incident) {
            $related = $updates->filter(fn ($update) => $update->proyecto_id === $incident->proyecto_id
                && $incident->created_at->diffInHours($update->created_at, true) <= 48);
            if ($related->isNotEmpty()) {
                $causes[] = [
                    'type' => 'change_related',
                    'statement' => 'Una actualización reciente podría estar relacionada con el incidente.',
                    'incident_id' => $incident->id,
                    'update_ids' => $related->pluck('id')->values()->all(),
                    'evidence_ids' => $related->map(fn ($update) => 'update:'.$update->id)->values()->all(),
                    'confidence' => 0.65,
                    'status' => 'hypothesis',
                ];
            }
        }
        if ($logs->filter(fn (array $log) => preg_match('/error|exception|fatal|critical|failed/i', $log['message']))->count() >= 3) {
            $causes[] = [
                'type' => 'error_burst',
                'statement' => 'Existe una concentración de errores en los logs durante la ventana observada.',
                'evidence_ids' => $logs->pluck('fingerprint')->map(fn ($fingerprint) => 'log:'.$fingerprint)->values()->all(),
                'confidence' => 0.8,
                'status' => 'observation',
            ];
        }
        return $causes;
    }

    private function evidence(Collection $events): array
    {
        return $events->map(fn (array $event): array => [
            'source' => $event['source'],
            'evidence_id' => $event['evidence_id'],
            'timestamp' => $event['timestamp'],
            'project_id' => $event['project_id'],
        ])->all();
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\d+/', '#', strtolower($value)) ?? strtolower($value);
    }

    private function logTimestamp(string $line): string
    {
        return preg_match('/^\[([^]]+)\]/', $line, $match) ? $match[1] : now()->toIso8601String();
    }

    private function logLevel(string $line): string
    {
        return preg_match('/\b( emergency|alert|critical|error|warning|notice|info|debug)\b/i', $line, $match)
            ? strtolower(trim($match[1]))
            : 'unknown';
    }
}
