<?php

namespace Tests\Unit;

use App\Models\NexusConversation;
use App\Models\NexusRun;
use App\Models\NexusToolCall;
use App\Services\NexusReflectionService;
use Tests\TestCase;

class NexusReflectionServiceTest extends TestCase
{
    public function test_it_persists_structured_reflection_after_a_completed_run(): void
    {
        $conversation = NexusConversation::create([
            'session_key' => 'reflection-test',
            'last_activity_at' => now(),
        ]);
        $run = NexusRun::create([
            'nexus_conversation_id' => $conversation->id,
            'message' => 'Analizar el proyecto',
            'status' => 'completed',
            'context' => [],
            'internal_state' => [
                'current_task' => 'code_analysis',
                'phase' => 'reporting',
                'next_action' => 'completed',
                'confidence' => 90,
                'known_information' => [['source' => 'tool', 'data' => ['files' => 3]]],
            ],
        ]);
        NexusToolCall::create([
            'nexus_run_id' => $run->id,
            'sequence' => 1,
            'tool_name' => 'nexus.code.analyze',
            'status' => 'succeeded',
            'result' => ['ok' => true, 'data' => ['files' => 3]],
        ]);

        $reflection = app(NexusReflectionService::class)->reflect(
            $run,
            $run->internal_state,
            ['message' => 'Análisis terminado'],
            $conversation
        );

        $this->assertTrue($reflection['goal_fulfilled']);
        $this->assertSame(['nexus.code.analyze'], $reflection['tools_used']);
        $this->assertArrayHasKey('should_remember', $reflection);
        $this->assertSame('success', $reflection['evaluation']['outcome']);
        $this->assertSame(0.9, $reflection['confidence_indicator']);
        $this->assertArrayHasKey('observed_facts', $reflection['evidence_classification']);
        $this->assertTrue($reflection['reflection_budget']['bounded']);
        $this->assertEquals($reflection, $run->fresh()->reflection);
    }

    public function test_it_detects_repeated_failures_and_produces_alternative_strategy(): void
    {
        $conversation = NexusConversation::create([
            'session_key' => 'reflection-failure-test',
            'last_activity_at' => now(),
        ]);
        $run = NexusRun::create([
            'nexus_conversation_id' => $conversation->id,
            'message' => 'Corregir autenticación',
            'status' => 'failed',
            'context' => [],
            'internal_state' => [
                'current_task' => 'validate',
                'next_action' => 'analyze_error',
                'confidence' => 40,
                'known_information' => [],
            ],
        ]);
        foreach ([1, 2] as $sequence) {
            NexusToolCall::create([
                'nexus_run_id' => $run->id,
                'sequence' => $sequence,
                'tool_name' => 'nexus.code.validate',
                'status' => 'failed',
                'error' => 'La prueba falló nuevamente.',
                'result' => ['ok' => false],
            ]);
        }

        $reflection = app(NexusReflectionService::class)->reflect(
            $run,
            $run->internal_state,
            ['status' => 'failed'],
            $conversation
        );

        $this->assertSame('failure', $reflection['evaluation']['outcome']);
        $this->assertSame('repeated_tool_failure', $reflection['error_patterns'][0]['type']);
        $this->assertSame('analyze_error', $reflection['alternative_strategy']['next_action']);
        $this->assertSame(0.4, $reflection['confidence_indicator']);
    }
}
