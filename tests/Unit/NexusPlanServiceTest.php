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

    public function test_it_tracks_step_metadata_and_allows_dynamic_plan_updates(): void
    {
        $run = NexusRun::create([
            'message' => 'Resolver autenticación',
            'status' => 'running',
            'context' => [],
            'internal_state' => [],
        ]);

        $plan = app(NexusPlanService::class)->createOrUpdate($run, [
            'objective' => 'Resolver autenticación',
            'subtasks' => [[
                'id' => 'inspect',
                'title' => 'Inspeccionar autenticación',
                'description' => 'Revisar rutas y controladores.',
                'goal' => 'Encontrar el flujo actual.',
                'required_tools' => ['nexus.project.query'],
            ]],
        ], $run->message);

        $this->assertSame('pending', $plan->subtasks[0]['status']);
        $this->assertSame(['nexus.project.query'], $plan->subtasks[0]['required_tools']);
        $this->assertSame(0, $plan->subtasks[0]['attempts']);

        $updated = app(NexusPlanService::class)->modify($plan, [
            'reason' => 'Se encontró una dependencia adicional.',
            'subtasks' => array_merge($plan->subtasks, [[
                'id' => 'mail',
                'title' => 'Revisar correo',
                'dependencies' => ['inspect'],
                'status' => 'pending',
            ]]),
        ]);

        $this->assertCount(2, $updated->subtasks);
        $this->assertSame('pending', $updated->subtasks[1]['status']);
        $this->assertSame('Se encontró una dependencia adicional.', $updated->metadata['modified_reason']);
    }
}
