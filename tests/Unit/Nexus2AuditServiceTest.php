<?php

namespace Tests\Unit;

use App\Services\Nexus2AuditService;
use Tests\TestCase;

class Nexus2AuditServiceTest extends TestCase
{
    public function test_audit_does_not_declare_nexus_two_ready_without_local_artifacts(): void
    {
        $report = (new Nexus2AuditService())->audit();

        $this->assertSame('not_ready', $report['verdict']);
        $this->assertFalse($report['capabilities']['local_model_artifacts']);
        $this->assertFalse($report['capabilities']['local_tool_calling']);
        $this->assertContains('run_real_local_checkpoint', $report['required_proof']);
    }

    public function test_audit_confirms_external_provider_is_not_required_by_local_runtime(): void
    {
        $report = (new Nexus2AuditService())->audit();

        $this->assertFalse($report['external_dependencies']['external_required_by_local_runtime']);
    }
}
