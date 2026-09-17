<?php

namespace Tests\Unit;

use App\Services\NexusDeveloperIntelligenceService;
use Tests\TestCase;

class NexusDeveloperIntelligenceServiceTest extends TestCase
{
    public function test_it_builds_read_only_project_signal_summary(): void
    {
        $service = app(NexusDeveloperIntelligenceService::class);

        $report = $service->analyzeProject(1, 168);

        $this->assertArrayHasKey('git', $report);
        $this->assertArrayHasKey('deployment', $report);
        $this->assertArrayHasKey('monitoring', $report);
        $this->assertArrayHasKey('root_cause', $report);
        $this->assertTrue($report['read_only']);
        $this->assertArrayHasKey('commit_count', $report['git']);
        $this->assertArrayHasKey('correlations', $report['deployment']);
        $this->assertArrayHasKey('alerts', $report['monitoring']);
    }

    public function test_root_cause_analysis_distinguishes_hypothesis_from_fact(): void
    {
        $service = app(NexusDeveloperIntelligenceService::class);
        $report = $service->analyzeProject(1, 168);

        $this->assertArrayHasKey('probable_cause', $report['root_cause']);
        $this->assertArrayHasKey('hypotheses', $report['root_cause']);
        $this->assertArrayHasKey('facts', $report['root_cause']);
        $this->assertArrayHasKey('missing_information', $report['root_cause']);
        $this->assertTrue($report['root_cause']['read_only']);
    }
}
