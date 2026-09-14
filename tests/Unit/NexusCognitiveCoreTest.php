<?php

namespace Tests\Unit;

use App\Services\NexusCognitiveCore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusCognitiveCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_and_plans_a_basic_project_query_without_a_model(): void
    {
        $result = app(NexusCognitiveCore::class)->evaluate(
            '¿Dónde está implementado el login de este proyecto?',
            ['project_id' => 1]
        );

        $this->assertSame('project_understanding', $result['classification']['category']);
        $this->assertFalse($result['rules']['llm_required']);
        $this->assertNotEmpty($result['plan']);
        $this->assertArrayHasKey('known', $result);
        $this->assertArrayHasKey('unknown', $result);
    }

    public function test_it_reports_missing_permissions_without_authorizing_itself(): void
    {
        $result = app(NexusCognitiveCore::class)->evaluate(
            'Crea una tarea para corregir el bug de autenticación.',
            ['project_id' => 1, 'permissions' => []]
        );

        $this->assertSame('task_management', $result['classification']['category']);
        $this->assertTrue($result['rules']['permission_manager_authority']);
        $this->assertFalse($result['rules']['execution_allowed']);
        $this->assertNotContains('project_id', $result['unknown']);
    }

    public function test_it_evaluates_conditions_deterministically(): void
    {
        $core = app(NexusCognitiveCore::class);

        $this->assertTrue($core->evaluateCondition(3, 'greater_than', 2));
        $this->assertTrue($core->evaluateCondition('login', 'contains', 'log'));
        $this->assertFalse($core->evaluateCondition('a', 'equals', 'b'));
        $this->assertFalse($core->evaluateCondition(1, 'unknown', 1));
    }
}
