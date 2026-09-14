<?php

namespace App\Services;

use App\Models\NexusInfrastructure;
use App\Models\NexusInfrastructureEvent;
use App\Models\Proyecto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class NexusInfrastructureService
{
    public function inspect(
        ?int $projectId = null,
        ?int $infrastructureId = null,
        ?string $endpoint = null,
        int $timeout = 5,
        array $thresholds = [],
        ?int $userId = null,
    ): array {
        $infrastructure = $infrastructureId
            ? NexusInfrastructure::findOrFail($infrastructureId)
            : NexusInfrastructure::query()->where('proyecto_id', $projectId)->latest('last_check')->first();

        if (! $infrastructure) {
            return [
                'available' => false,
                'error_code' => 'infrastructure_not_configured',
                'message' => 'No existe una infraestructura configurada para este proyecto.',
            ];
        }

        $health = $this->health($infrastructure, $endpoint, $timeout, $thresholds);
        $infrastructure->update([
            'health' => $health,
            'last_check' => now(),
            'metadata' => array_merge($infrastructure->metadata ?? [], [
                'last_observed_by' => $userId,
                'last_observation_at' => now()->toISOString(),
            ]),
        ]);

        $this->recordEvents($infrastructure, $health['anomalies'] ?? []);

        return [
            'available' => true,
            'infrastructure' => $infrastructure->fresh()->toArray(),
            'health' => $health,
            'events' => $infrastructure->events()->latest('detected_at')->limit(50)->get()->toArray(),
        ];
    }

    public function events(int $infrastructureId, bool $activeOnly = true): array
    {
        return NexusInfrastructureEvent::query()
            ->where('infrastructure_id', $infrastructureId)
            ->when($activeOnly, fn ($query) => $query->whereNull('resolved_at'))
            ->latest('detected_at')
            ->limit(100)
            ->get()
            ->toArray();
    }

    private function health(
        NexusInfrastructure $infrastructure,
        ?string $endpoint,
        int $timeout,
        array $thresholds,
    ): array {
        $snapshot = [
            'status' => 'unknown',
            'checks' => [],
            'anomalies' => [],
            'checked_at' => now()->toISOString(),
        ];
        $resources = $infrastructure->resources ?? [];
        $limits = array_merge(config('nexus.infrastructure.thresholds', []), $thresholds);

        foreach ([
            'cpu_percent' => 'CPU alta',
            'memory_percent' => 'Memoria alta',
            'disk_percent' => 'Disco casi lleno',
        ] as $key => $title) {
            if (isset($resources[$key]) && (float) $resources[$key] >= (float) $limits[$key]) {
                $snapshot['anomalies'][] = [
                    'type' => str_replace('_percent', '_high', $key),
                    'severity' => 'warning',
                    'title' => $title,
                    'description' => sprintf('%s está en %.1f%%.', $key, $resources[$key]),
                    'evidence' => ['value' => $resources[$key], 'threshold' => $limits[$key]],
                ];
            }
        }

        foreach ((array) ($infrastructure->services ?? []) as $service) {
            if (($service['status'] ?? null) === 'stopped' || ($service['active'] ?? null) === false) {
                $snapshot['anomalies'][] = [
                    'type' => 'service_stopped',
                    'severity' => 'critical',
                    'title' => 'Servicio detenido',
                    'description' => 'El servicio '.($service['name'] ?? 'desconocido').' no está activo.',
                    'evidence' => $service,
                ];
            }
        }

        foreach ((array) ($infrastructure->ssl ?? []) as $certificate) {
            if (! isset($certificate['expires_at'])) {
                continue;
            }
            $days = now()->diffInDays($certificate['expires_at'], false);
            if ($days <= (int) $limits['ssl_days']) {
                $snapshot['anomalies'][] = [
                    'type' => 'ssl_expiring',
                    'severity' => $days < 0 ? 'critical' : 'warning',
                    'title' => 'Certificado SSL próximo a expirar',
                    'description' => sprintf('El certificado de %s vence en %d días.', $certificate['domain'] ?? 'dominio desconocido', $days),
                    'evidence' => ['certificate' => $certificate, 'days_remaining' => $days],
                ];
            }
        }

        $urls = array_values(array_filter(array_merge(
            $endpoint ? [$endpoint] : [],
            collect($infrastructure->applications ?? [])->pluck('url')->all(),
            collect($infrastructure->domains ?? [])->map(fn ($domain) => is_string($domain) ? $domain : ($domain['url'] ?? null))->all(),
        ), fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL)));

        foreach ($urls as $url) {
            if (! $this->isAllowedEndpoint($url)) {
                $snapshot['checks'][] = ['type' => 'http', 'target' => $url, 'status' => 'blocked', 'error' => 'endpoint_not_allowed'];
                continue;
            }
            try {
                $response = Http::timeout(max(1, min(30, $timeout)))->withOptions(['allow_redirects' => false])->get($url);
                $snapshot['checks'][] = [
                    'type' => 'http',
                    'target' => $url,
                    'status' => $response->successful() ? 'healthy' : 'unhealthy',
                    'http_status' => $response->status(),
                    'latency_ms' => null,
                ];
                if (! $response->successful()) {
                    $snapshot['anomalies'][] = [
                        'type' => 'application_unavailable',
                        'severity' => 'critical',
                        'title' => 'Aplicación inaccesible',
                        'description' => "La aplicación respondió con HTTP {$response->status()}.",
                        'evidence' => ['url' => $url, 'status' => $response->status()],
                    ];
                }
            } catch (ConnectionException $exception) {
                $snapshot['checks'][] = [
                    'type' => 'http',
                    'target' => $url,
                    'status' => 'unreachable',
                    'error' => 'timeout_or_connection_error',
                ];
                $snapshot['anomalies'][] = [
                    'type' => 'server_unreachable',
                    'severity' => 'critical',
                    'title' => 'Servidor inaccesible',
                    'description' => "No se pudo conectar con {$url}.",
                    'evidence' => ['url' => $url],
                ];
            }
        }

        $snapshot['status'] = $snapshot['anomalies'] === [] ? 'healthy' : 'degraded';

        return $snapshot;
    }

    private function isAllowedEndpoint(string $url): bool
    {
        $parts = parse_url($url);
        if (! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($parts['host'])
            || ! empty($parts['user'])
            || ! empty($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
        return $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private function recordEvents(NexusInfrastructure $infrastructure, array $anomalies): void
    {
        foreach (collect($anomalies)->unique('type')->values() as $anomaly) {
            NexusInfrastructureEvent::firstOrCreate(
                [
                    'infrastructure_id' => $infrastructure->id,
                    'type' => $anomaly['type'],
                    'resolved_at' => null,
                ],
                [
                    'proyecto_id' => $infrastructure->proyecto_id,
                    'severity' => $anomaly['severity'],
                    'title' => $anomaly['title'],
                    'description' => $anomaly['description'],
                    'evidence' => $anomaly['evidence'] ?? [],
                    'detected_at' => now(),
                ]
            );
        }
    }
}
