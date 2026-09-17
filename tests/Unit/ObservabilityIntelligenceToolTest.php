<?php

namespace Tests\Unit;

use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use App\Nexus\Tools\ObservabilityIntelligenceTool;
use App\Services\NexusObservabilityService;
use Tests\TestCase;

class ObservabilityIntelligenceToolTest extends TestCase
{
    public function test_rejects_an_observation_window_outside_the_allowed_range(): void
    {
        $tool = new ObservabilityIntelligenceTool(new NexusObservabilityService());

        $result = $tool->execute(['hours' => 0], new NexusToolContext(system: true));

        $this->assertFalse($result->successful);
        $this->assertSame('validation_failed', $result->errorCode);
    }

    public function test_is_registered_as_a_read_only_devcontrol_tool(): void
    {
        $registry = new NexusToolRegistry([new ObservabilityIntelligenceTool(new NexusObservabilityService())]);
        $definition = collect($registry->definitions())->firstWhere('name', 'devcontrol.observability.read');

        $this->assertSame(['devcontrol.read'], $definition['permissions']);
        $this->assertFalse($definition['requires_confirmation']);
        $this->assertStringContainsString('logs', strtolower($definition['description']));
    }

    public function test_does_not_request_confirmation_for_read_only_observation(): void
    {
        $registry = new NexusToolRegistry([new ObservabilityIntelligenceTool(new NexusObservabilityService())]);
        $definition = collect($registry->definitions())->firstWhere('name', 'devcontrol.observability.read');

        $this->assertFalse($definition['requires_confirmation']);
        $this->assertNotContains('devcontrol.write', $definition['permissions']);
    }
}
