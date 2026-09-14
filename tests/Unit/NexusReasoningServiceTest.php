<?php

namespace Tests\Unit;

use App\Contracts\NexusModel;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;
use App\Nexus\NexusToolRegistry;
use App\Services\NexusReasoningService;
use PHPUnit\Framework\TestCase;

class NexusReasoningServiceTest extends TestCase
{
    public function test_it_sends_identity_message_context_and_tools_to_the_model(): void
    {
        $request = null;
        $model = new class($request) implements NexusModel
        {
            private mixed $captured;

            public function __construct(mixed &$captured)
            {
                $this->captured =& $captured;
            }

            public function complete(NexusModelRequest $request): NexusModelResponse
            {
                $this->captured = $request;

                return new NexusModelResponse('list_tasks', 'Consultaré las tareas.', []);
            }
        };

        $result = (new NexusReasoningService($model, new NexusToolRegistry()))->reason(
            '¿Qué tareas siguen abiertas?',
            ['project_id' => 1],
            [['role' => 'user', 'content' => 'Hola']]
        );

        $this->assertTrue($result->successful);
        $this->assertSame('¿Qué tareas siguen abiertas?', $request->message);
        $this->assertSame(['project_id' => 1], $request->context);
        $this->assertStringContainsString('Eres Nexus', $request->identity);
        $this->assertSame([], $request->tools);
    }

    public function test_it_rejects_tool_calls_that_are_not_registered(): void
    {
        $model = new class implements NexusModel
        {
            public function complete(NexusModelRequest $request): NexusModelResponse
            {
                return new NexusModelResponse('unknown', 'No sé.', [
                    ['name' => 'not.registered', 'arguments' => []],
                ]);
            }
        };

        $result = (new NexusReasoningService($model, new NexusToolRegistry()))->reason('Haz algo');

        $this->assertFalse($result->successful);
        $this->assertSame('tool_not_found', $result->errorCode);
    }
}
