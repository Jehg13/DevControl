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
        $this->assertEquals($reflection, $run->fresh()->reflection);
    }
}
