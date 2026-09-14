<?php

namespace Tests\Unit;

use App\Models\NexusPlan;
use App\Models\NexusRun;
use App\Services\NexusPlanService;
use Tests\TestCase;

class NexusPlanServiceTest extends TestCase
{
    public function test_it_normalizes_subtasks_and_advances_dependencies(): void
    {
        $run = NexusRun::create([
            'message' => 'Implementar una mejora',
            'status' => 'running',
            'context' => [],
            'internal_state' => [],
        ]);

        $service = app(NexusPlanService::class);
        $plan = $service->createOrUpdate($run, [
            'objective' => 'Implementar una mejora',
            'priority' => 90,
            'subtasks' => [
                ['id' => 'inspect', 'title' => 'Inspeccionar', 'status' => 'in_progress'],
                ['id' => 'change', 'title' => 'Cambiar', 'dependencies' => ['inspect']],
            ],
            'expected_result' => 'Cambio validado',
            'completion_criteria' => 'Las pruebas pasan',
        ], $run->message);

        $plan = $service->updateAfterAction($plan, 'nexus.code.analyze', ['ok' => true, 'data' => []]);

        $this->assertSame('completed', $plan->subtasks[0]['status']);
        $this->assertSame('in_progress', $plan->subtasks[1]['status']);
        $this->assertSame('in_progress', $plan->status);
    }
}
