<?php

namespace Tests\Unit;

use App\Services\NexusProductionReadinessService;
use Tests\TestCase;

final class NexusProductionReadinessServiceTest extends TestCase
{
    public function test_phase_forty_audit_is_read_only_and_does_not_claim_unsupported_autonomy(): void
    {
        $report = app(NexusProductionReadinessService::class)->audit(new NexusToolRegistry());

        $this->assertSame('Nexus 3.0', $report['product']);
        $this->assertSame('not_production_ready', $report['verdict']);
        $this->assertSame('not_claimed', $report['autonomy_claim']);
        $this->assertNotEmpty($report['trace_id']);
        $this->assertSame('unverified', $report['controls']['traceability']['status']);
        $this->assertStringContainsString('Python', $report['limitations']['python_tool_execution']);
        $this->assertStringContainsString('does not open a database connection', $report['database_policy']);
    }

    public function test_phase_forty_audit_inspects_real_layer_and_contract_presence(): void
    {
        $report = app(NexusProductionReadinessService::class)->audit();

        $this->assertSame('available', $report['layers']['runtime']['status']);
        $this->assertSame('available', $report['layers']['security']['status']);
        $this->assertSame('verified', $report['contracts']['ai_transport']['status']);
        $this->assertSame('verified', $report['contracts']['controlled_cycle']['status']);
        $this->assertSame('verified', $report['controls']['authorization_boundary']['status']);
        $this->assertSame(0, $report['controls']['tool_catalog']['tool_count']);
        $this->assertStringContainsString('Registry was not supplied', $report['controls']['tool_catalog']['reason']);
    }
}
