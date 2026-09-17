<?php

namespace Tests\Unit;

use App\Services\NexusPhpIntelligenceService;
use Tests\TestCase;

class NexusPhpIntelligenceServiceTest extends TestCase
{
    public function test_it_reports_advanced_php_categories_and_runtime_evidence(): void
    {
        $report = app(NexusPhpIntelligenceService::class)->diagnose();

        $this->assertSame(16, count($report['categories']));
        $this->assertTrue($report['read_only']);
        $this->assertArrayHasKey('php_version', $report['runtime']);
        $this->assertArrayHasKey('opcache', $report['runtime']);
        $this->assertArrayHasKey('by_severity', $report['summary']);
        $this->assertIsArray($report['findings']);
    }

    public function test_it_detects_unsafe_serialization_and_dynamic_processes(): void
    {
        $service = app(NexusPhpIntelligenceService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('findings');
        $method->setAccessible(true);

        $findings = $method->invoke(
            $service,
            'tests/fixture.php',
            "<?php\nunserialize(\$_POST['payload']);\nexec(\$command);\n",
            ['references' => false]
        );

        $codes = array_column($findings, 'code');
        $this->assertContains('unsafe_unserialize_input', $codes);
        $this->assertContains('dynamic_process_input', $codes);
    }
}
