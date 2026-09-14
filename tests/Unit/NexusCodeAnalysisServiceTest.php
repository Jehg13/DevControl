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

    public function test_analysis_accepts_a_single_php_file_path(): void
    {
        $result = app(NexusCodeAnalysisService::class)->analyze(
            null,
            'app/Http/Controllers/ProyectoController.php',
            false
        );

        $this->assertCount(1, $result['files']);
        $this->assertSame(
            'app/Http/Controllers/ProyectoController.php',
            $result['files'][0]['path']
        );
        $this->assertNotEmpty($result['symbols'][0]['functions']);
    }
}
