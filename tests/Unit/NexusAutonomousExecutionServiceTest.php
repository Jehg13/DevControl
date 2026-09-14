<?php

namespace Tests\Unit;

use App\Models\NexusAutonomousRun;
use App\Models\NexusPlan;
use App\Models\NexusRun;
use App\Nexus\NexusModelResponse;
use App\Nexus\NexusReasoningResult;
use App\Services\NexusAutonomousExecutionService;
use App\Services\NexusExecutionService;
use App\Services\NexusPlanService;
use App\Services\NexusPlannerService;
use Mockery;
use Tests\TestCase;

class NexusAutonomousExecutionServiceTest extends TestCase
{
    public function test_it_creates_a_plan_before_starting_autonomous_execution(): void
    {
        $planner = Mockery::mock(NexusPlannerService::class);
        $planner->shouldReceive('create')->once()->andReturn(NexusReasoningResult::success(
            new NexusModelResponse('plan', 'Plan creado.', [], false, [
                'subtasks' => [[
                    'id' => 'inspect',
                    'title' => 'Inspeccionar',
                    'description' => 'Inspeccionar el proyecto',
                ]],
            ])
        ));
        $planner->shouldReceive('persist')->once()->andReturnUsing(function (NexusRun $run): NexusPlan {
            return NexusPlan::create([
                'nexus_run_id' => $run->id,
                'objective' => $run->message,
                'subtasks' => [[
                    'id' => 'inspect',
                    'title' => 'Inspeccionar',
                    'description' => 'Inspeccionar el proyecto',
                    'dependencies' => [],
                    'status' => 'pending',
                    'attempts' => 0,
                ]],
                'status' => 'in_progress',
                'priority' => 50,
                'expected_result' => 'Inspección',
                'completion_criteria' => 'Resultado disponible',
                'metadata' => [],
            ]);
        });
        $execution = Mockery::mock(NexusExecutionService::class);
        $execution->shouldReceive('execute')->once()->andReturn($this->childRun('completed'));

        $result = $this->service($execution, $planner)->start('Analiza el proyecto.', [], null, 'test', [
            'max_steps' => 1,
        ]);

        $this->assertSame('completed', $result->status, $result->error ?? '');
        $this->assertNotNull($result->nexus_plan_id);
    }

    public function test_it_completes_a_successful_execution(): void
    {
        [$autonomous, $execution] = $this->fixture(Mockery::mock(NexusExecutionService::class));
        $execution->shouldReceive('execute')->once()->andReturn($this->childRun('completed'));

        $result = $this->service($execution)->resume($autonomous, null);

        $this->assertSame('completed', $result->status);
        $this->assertSame(1, $result->steps);
        $this->assertSame('completed', $result->planModel->fresh()->status);
    }

    public function test_it_retries_recoverable_errors(): void
    {
        [$autonomous, $execution] = $this->fixture(Mockery::mock(NexusExecutionService::class));
        $execution->shouldReceive('execute')->twice()->andReturn(
            $this->childRun('failed', 'execution_failed: temporary'),
            $this->childRun('completed')
        );

        $result = $this->service($execution)->resume($autonomous);

        $this->assertSame('completed', $result->status);
        $this->assertSame(1, $result->retries);
    }

    public function test_it_blocks_when_permission_is_missing(): void
    {
        [$autonomous, $execution] = $this->fixture(Mockery::mock(NexusExecutionService::class));
        $execution->shouldReceive('execute')->once()->andReturn(
            $this->childRun('failed', 'permission_denied: missing github.write')
        );

        $result = $this->service($execution)->resume($autonomous);

        $this->assertSame('blocked', $result->status);
        $this->assertStringContainsString('permission_denied', $result->error);
    }

    public function test_it_pauses_for_human_approval_and_resumes_after_approval(): void
    {
        [$autonomous, $execution] = $this->fixture(Mockery::mock(NexusExecutionService::class));
        $execution->shouldReceive('execute')->twice()->andReturn(
            $this->childRun('awaiting_confirmation'),
            $this->childRun('completed')
        );

        $waiting = $this->service($execution)->resume($autonomous);
        $this->assertSame('awaiting_approval', $waiting->status);

        $result = $this->service($execution)->resume($waiting, null, true);
        $this->assertSame('completed', $result->status);
    }

    public function test_it_stops_at_the_step_limit_and_supports_pause_and_resume(): void
    {
        [$autonomous, $execution] = $this->fixture(Mockery::mock(NexusExecutionService::class), [
            'max_steps' => 1,
        ], 2);
        $execution->shouldReceive('execute')->once()->andReturn($this->childRun('completed'));

        $result = $this->service($execution)->resume($autonomous);
        $this->assertSame('limit_exceeded', $result->status);

        [$paused, $pausedExecution] = $this->fixture(Mockery::mock(NexusExecutionService::class));
        $pausedExecution->shouldReceive('execute')->once()->andReturn($this->childRun('completed'));
        $pausedResult = $this->service($pausedExecution)->pause($paused);
        $this->assertSame('paused', $pausedResult->status);
        $resumed = $this->service($pausedExecution)->resume($pausedResult);
        $this->assertSame('completed', $resumed->status);
    }

    public function test_it_stops_after_the_retry_limit(): void
    {
        [$autonomous, $execution] = $this->fixture(Mockery::mock(NexusExecutionService::class), [
            'max_retries' => 0,
        ]);
        $execution->shouldReceive('execute')->once()->andReturn($this->childRun('failed', 'execution_failed: permanent'));

        $result = $this->service($execution)->resume($autonomous);

        $this->assertSame('failed', $result->status);
        $this->assertSame(0, $result->retries);
    }

    public function test_it_enforces_timeout_before_running_another_step(): void
    {
        [$autonomous, $execution] = $this->fixture(Mockery::mock(NexusExecutionService::class), [
            'timeout_seconds' => 1,
        ]);
        $autonomous->update(['started_at' => now()->subSeconds(2)]);
        $execution->shouldReceive('execute')->never();

        $result = $this->service($execution)->resume($autonomous);

        $this->assertSame('limit_exceeded', $result->status);
        $this->assertStringContainsString('tiempo máximo', $result->error);
    }

    private function fixture($execution, array $limits = [], int $stepCount = 1): array
    {
        $run = NexusRun::create([
            'message' => 'Objetivo autónomo de prueba',
            'source' => 'test',
            'status' => 'paused',
            'context' => [],
        ]);
        $subtasks = [];
        for ($index = 1; $index <= $stepCount; $index++) {
            $subtasks[] = [
                'id' => 'step-'.$index,
                'title' => 'Paso '.$index,
                'description' => 'Ejecutar paso '.$index,
                'dependencies' => $index === 1 ? [] : ['step-'.($index - 1)],
                'required_tools' => [],
                'status' => 'pending',
                'attempts' => 0,
            ];
        }
        $plan = NexusPlan::create([
            'nexus_run_id' => $run->id,
            'objective' => $run->message,
            'subtasks' => $subtasks,
            'status' => 'in_progress',
            'priority' => 50,
            'expected_result' => 'Completar',
            'completion_criteria' => 'Todos los pasos completados',
            'metadata' => [],
        ]);
        $autonomous = NexusAutonomousRun::create([
            'nexus_run_id' => $run->id,
            'nexus_plan_id' => $plan->id,
            'objective' => $run->message,
            'status' => 'paused',
            'plan' => $plan->toArray(),
            'limits' => array_merge([
                'max_steps' => 10,
                'max_retries' => 2,
                'timeout_seconds' => 300,
                'budget' => 20,
                'allowed_tools' => null,
                'prohibited_tools' => [],
            ], $limits),
            'state' => ['current_step' => null, 'results' => [], 'errors' => []],
            'started_at' => now(),
        ]);

        return [$autonomous, $execution];
    }

    private function service($execution, $planner = null): NexusAutonomousExecutionService
    {
        if ($planner === null) {
            $planner = Mockery::mock(NexusPlannerService::class);
            $planner->shouldReceive('persist')->zeroOrMoreTimes();
        }

        return new NexusAutonomousExecutionService(
            $planner,
            app(NexusPlanService::class),
            $execution,
            app(\App\Nexus\NexusToolRegistry::class),
        );
    }

    private function childRun(string $status, ?string $error = null): NexusRun
    {
        return NexusRun::create([
            'message' => 'Paso autónomo',
            'source' => 'autonomous_step',
            'status' => $status,
            'result' => ['status' => $status],
            'error' => $error,
        ]);
    }
}
