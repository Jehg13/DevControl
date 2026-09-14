<?php

namespace App\Services;

use App\Models\NexusFinding;
use App\Models\NexusProactiveAlert;
use Illuminate\Support\Facades\Schema;

final class NexusProactiveService
{
    public function __construct(
        private readonly NexusAuditService $audit,
        private readonly DevControlAlertService $alerts,
    ) {
    }

    public function run(?int $projectId = null): array
    {
        if (! (bool) config('nexus.proactive.enabled', true)) {
            return ['disabled' => true, 'groups' => 0, 'updated' => 0, 'notified' => 0, 'suppressed' => 0];
        }
        if (! Schema::hasTable('nexus_proactive_alerts')) {
            throw new \RuntimeException('La tabla nexus_proactive_alerts no existe. Ejecuta php artisan migrate.');
        }

        $scan = $this->audit->scan($projectId);
        $findings = $this->audit->findings($projectId)
            ->filter(fn (NexusFinding $finding): bool => $this->isRelevant($finding));
        $groups = $findings->groupBy(fn (NexusFinding $finding): string => implode(':', [
            $finding->proyecto_id ?? 0,
            $finding->tipo,
            strtolower((string) $finding->severidad),
        ]));
        $notified = 0;
        $updated = 0;
        $suppressed = 0;

        foreach ($groups as $groupKey => $items) {
            /** @var NexusFinding $representative */
            $representative = $items->sortByDesc(fn (NexusFinding $finding): int => $this->severity($finding->severidad))->first();
            $fingerprint = hash('sha256', $groupKey.':'.implode(',', $items->pluck('huella')->sort()->all()));
            $alert = NexusProactiveAlert::firstOrNew(['fingerprint' => $fingerprint]);
            $new = ! $alert->exists;
            $alert->fill([
                'proyecto_id' => $representative->proyecto_id,
                'source' => 'nexus_scan',
                'group_key' => $groupKey,
                'priority' => $this->priority($representative->severidad),
                'status' => 'active',
                'occurrences' => $new ? 1 : $alert->occurrences + 1,
                'first_seen' => $alert->first_seen ?? now(),
                'last_seen' => now(),
                'evidence' => $items->map(fn (NexusFinding $finding): array => [
                    'finding_id' => $finding->id,
                    'title' => $finding->titulo,
                    'severity' => $finding->severidad,
                    'description' => $finding->descripcion,
                ])->values()->all(),
                'metadata' => ['count' => $items->count(), 'diagnosis' => $this->diagnosis($items)],
            ]);
            $alert->save();
            $updated++;

            if ($this->shouldNotify($alert, $new)) {
                $this->alerts->send(
                    'Alerta proactiva Nexus',
                    $this->title($items->count(), $representative),
                    $representative->proyecto,
                    [
                        'Prioridad' => $alert->priority,
                        'Estado' => 'Activo',
                        'Hallazgos' => $items->count(),
                        'Diagnóstico' => $alert->metadata['diagnosis'],
                    ]
                );
                $alert->update(['last_notified_at' => now()]);
                $notified++;
            } else {
                $suppressed++;
            }
        }

        return [
            'scan' => $scan,
            'groups' => $groups->count(),
            'updated' => $updated,
            'notified' => $notified,
            'suppressed' => $suppressed,
        ];
    }

    public function silence(int $alertId, int $minutes): NexusProactiveAlert
    {
        if ($minutes < 1 || $minutes > 43200) {
            throw new \InvalidArgumentException('El silenciamiento debe estar entre 1 y 43200 minutos.');
        }
        $alert = NexusProactiveAlert::findOrFail($alertId);
        $alert->update(['silenced_until' => now()->addMinutes($minutes), 'status' => 'silenced']);
        return $alert->fresh();
    }

    private function shouldNotify(NexusProactiveAlert $alert, bool $new): bool
    {
        if ($alert->silenced_until?->isFuture()) {
            return false;
        }
        if ($new || ! $alert->last_notified_at) {
            return true;
        }
        return $alert->last_notified_at->addMinutes((int) config('nexus.proactive.cooldown_minutes', 60))->isPast()
            || $alert->priority === 'Alta' && $alert->occurrences % (int) config('nexus.proactive.high_priority_repeat', 3) === 0;
    }

    private function isRelevant(NexusFinding $finding): bool
    {
        return $this->severity($finding->severidad) >= (int) config('nexus.proactive.minimum_severity', 2);
    }

    private function severity(?string $value): int
    {
        return match (strtolower((string) $value)) {
            'critical', 'crítica', 'critica' => 4,
            'high', 'alta', 'warning' => 3,
            'medium', 'media' => 2,
            default => 1,
        };
    }

    private function priority(?string $severity): string
    {
        return $this->severity($severity) >= 3 ? 'Alta' : 'Media';
    }

    private function diagnosis($items): string
    {
        return sprintf('%d hallazgo(s) relacionado(s) requieren revisión; la severidad máxima es %s.', $items->count(), $items->max('severidad'));
    }

    private function title(int $count, NexusFinding $finding): string
    {
        return $count > 1 ? "{$count} hallazgos agrupados: {$finding->titulo}" : $finding->titulo;
    }
}
