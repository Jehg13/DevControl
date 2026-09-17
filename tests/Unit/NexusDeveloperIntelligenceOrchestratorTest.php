<?php

namespace Tests\Unit;

use App\Services\NexusDeveloperIntelligenceOrchestrator;
use Tests\TestCase;

class NexusDeveloperIntelligenceOrchestratorTest extends TestCase
{
    public function test_it_builds_a_read_only_project_intelligence_snapshot(): void
    {
        $service = app(NexusDeveloperIntelligenceOrchestrator::class);
        $report = $service->analyze(1, '.');

        $this->assertArrayHasKey('repository', $report);
        $this->assertArrayHasKey('code', $report);
        $this->assertArrayHasKey('commits', $report);
        $this->assertArrayHasKey('dependencies', $report);
        $this->assertArrayHasKey('tests', $report);
        $this->assertArrayHasKey('issues', $report);
        $this->assertArrayHasKey('logs', $report);
        $this->assertArrayHasKey('security', $report);
        $this->assertArrayHasKey('deployment', $report);
        $this->assertTrue($report['read_only']);
    }
}
