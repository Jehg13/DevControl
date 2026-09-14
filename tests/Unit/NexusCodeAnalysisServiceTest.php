<?php

namespace Tests\Unit;

use App\Services\NexusCodeAnalysisService;
use Tests\TestCase;

class NexusCodeAnalysisServiceTest extends TestCase
{
    public function test_analysis_exposes_project_evidence_and_a_fingerprint(): void
    {
        $result = app(NexusCodeAnalysisService::class)->analyze(null, null, false);

        $this->assertContains('PHP', $result['technologies']['detected']);
        $this->assertContains('Laravel', $result['technologies']['detected']);
        $this->assertNotEmpty($result['files']);
        $this->assertNotEmpty($result['fingerprint']);
        $this->assertIsArray($result['route_bindings']);
        $this->assertTrue($result['read_only']);
    }
}
