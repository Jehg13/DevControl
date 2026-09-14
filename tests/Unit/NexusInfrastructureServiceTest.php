<?php

namespace Tests\Unit;

use App\Models\NexusInfrastructure;
use App\Services\NexusInfrastructureService;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusInfrastructureServiceTest extends TestCase
{
    use RefreshDatabase;
    public function test_it_observes_health_and_records_basic_anomalies(): void
    {
        Http::fake([
            'https://app.example.test/health' => Http::response([], 503),
        ]);

        $infrastructure = NexusInfrastructure::create([
            'name' => 'Producción',
            'provider' => 'VPS',
            'server' => ['host' => 'vps.example.test'],
            'operating_system' => ['name' => 'Ubuntu'],
            'resources' => [
                'cpu_percent' => 95,
                'memory_percent' => 40,
                'disk_percent' => 92,
            ],
            'services' => [['name' => 'nginx', 'status' => 'stopped']],
            'applications' => [['name' => 'DevControl', 'url' => 'https://app.example.test/health']],
            'databases' => [['engine' => 'mysql', 'status' => 'available']],
            'domains' => ['https://app.example.test'],
            'ssl' => [['domain' => 'app.example.test', 'expires_at' => now()->addDays(5)->toISOString()]],
        ]);

        $result = app(NexusInfrastructureService::class)->inspect(
            infrastructureId: $infrastructure->id,
            timeout: 5,
        );

        $this->assertTrue($result['available']);
        $this->assertSame('degraded', $result['health']['status']);
        $this->assertContains('service_stopped', collect($result['health']['anomalies'])->pluck('type')->all());
        $this->assertContains('application_unavailable', collect($result['health']['anomalies'])->pluck('type')->all());
        $this->assertGreaterThanOrEqual(5, $infrastructure->fresh()->events()->count());
    }

    public function test_it_reports_unreachable_servers_without_throwing(): void
    {
        Http::fake([
            'https://offline.example.test/*' => function () {
                throw new ConnectionException('offline');
            },
        ]);

        $infrastructure = NexusInfrastructure::create([
            'name' => 'Servidor offline',
            'applications' => [['url' => 'https://offline.example.test/health']],
        ]);

        $result = app(NexusInfrastructureService::class)->inspect(infrastructureId: $infrastructure->id);

        $this->assertTrue($result['available'] ?? false, json_encode($result));
        $this->assertSame('server_unreachable', $result['health']['anomalies'][0]['type']);
        $this->assertSame('unreachable', $result['health']['checks'][0]['status']);
    }

    public function test_it_reports_unconfigured_projects(): void
    {
        $result = app(NexusInfrastructureService::class)->inspect(projectId: 999999);

        $this->assertFalse($result['available']);
        $this->assertSame('infrastructure_not_configured', $result['error_code']);
    }
}
