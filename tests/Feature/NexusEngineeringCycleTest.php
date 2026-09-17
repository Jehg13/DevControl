<?php

namespace Tests\Feature;

use App\Nexus\NexusRuntime;
use App\Nexus\NexusRuntimeRequest;
use App\Nexus\NexusRuntimeResponse;
use App\Nexus\NexusToolResult;
use App\Nexus\NexusToolContext;
use App\Models\NexusRun;
use App\Models\NexusAutonomousRun;
use App\Models\User;
use App\Http\Controllers\NexusController;
use Illuminate\Http\Request;
use App\Services\NexusControlledEngineeringCycleService;
use App\Services\NexusExecutionService;
use App\Services\NexusExperienceService;
use App\Services\NexusMemoryService;
use App\Services\NexusResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusEngineeringCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_controlled_cycle_resume_rejects_a_run_owned_by_another_user(): void
    {
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'secret', 'rol' => 'admin']);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret', 'rol' => 'admin']);
        $run = NexusRun::create(['usuario_id' => $owner->id, 'source' => 'test', 'message' => 'x', 'status' => 'awaiting_confirmation']);
        $autonomous = NexusAutonomousRun::create([
            'nexus_run_id' => $run->id, 'usuario_id' => $owner->id, 'objective' => 'x',
            'status' => 'awaiting_approval', 'limits' => [], 'state' => [],
        ]);
        $request = Request::create('/controlled-cycle/'.$autonomous->id.'/resume', 'POST', [
            'approved' => true,
        ]);
        $request->setUserResolver(fn () => $other);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NexusController::class)->resumeControlledCycle(
            $request,
            $autonomous,
            app(NexusControlledEngineeringCycleService::class)
        );
    }

    public function test_controlled_cycle_checkpoints_and_stops_until_explicit_authorization(): void
    {
        $execution = \Mockery::mock(NexusExecutionService::class);
        $child = new NexusRun(['status' => 'completed']);
        $child->id = 42;
        $execution->shouldReceive('execute')->once()->andReturn($child);
        $tools = new \App\Nexus\NexusToolRegistry;
        $tools->register(new class implements \App\Contracts\NexusTool {
            public function name(): string { return 'nexus.code.validate'; }
            public function description(): string { return 'test validator'; }
            public function parameters(): array { return []; }
            public function permissions(): array { return ['nexus.read']; }
            public function requiresConfirmation(): bool { return false; }
            public function execute(array $parameters, NexusToolContext $context): NexusToolResult
            {
                return NexusToolResult::success(['passed' => true]);
            }
        });
        $this->app->instance(NexusExecutionService::class, $execution);
        $this->app->instance(\App\Nexus\NexusToolRegistry::class, $tools);

        $cycle = app(NexusControlledEngineeringCycleService::class);
        $pending = $cycle->start('Fix the failing project listing.');
        $this->assertSame('awaiting_approval', $pending->status);
        $this->assertSame(
            ['problem', 'understanding', 'context', 'investigation', 'evidence', 'hypotheses',
                'diagnosis', 'impact', 'solution', 'plan', 'permissions'],
            array_column($pending->state['checkpoints'], 'stage')
        );
        $cycle->resume($pending, null, false);
        $this->assertSame('awaiting_approval', $pending->fresh()->status);

        $completed = $cycle->resume($pending->fresh(), null, true, ['nexus.read']);
        $this->assertSame('completed', $completed->status);
        $this->assertSame(
            ['problem', 'understanding', 'context', 'investigation', 'evidence', 'hypotheses',
                'diagnosis', 'impact', 'solution', 'plan', 'permissions', 'execution', 'tests',
                'analysis', 'correction', 'retest', 'validation', 'optional_commit',
                'rollback_if_failure', 'result', 'memory'],
            array_column($completed->state['checkpoints'], 'stage')
        );
        $checkpoints = collect($completed->state['checkpoints'])->keyBy('stage');
        $this->assertSame('completed', $checkpoints['tests']['status']);
        $this->assertSame(['passed' => true], $checkpoints['tests']['data']['data']);
        $this->assertSame('skipped', $checkpoints['optional_commit']['status']);
        $this->assertStringContainsString('explicit request', $checkpoints['optional_commit']['data']['reason']);
    }

    public function test_full_cycle_researches_diagnoses_proposes_and_keeps_execution_authorized(): void
    {
        $response = app(NexusRuntime::class)->handle(new NexusRuntimeRequest(
            message: 'Los proyectos no aparecen después de crearlos. Investiga por qué.',
            projectId: 1,
        ));

        $this->assertSame('diagnosis', $response->intent);
        $this->assertNotEmpty($response->toolResults);
        $this->assertNotEmpty($response->correlation['links']);
        $this->assertNotEmpty($response->hypotheses);
        $this->assertNotEmpty($response->verification);
        $this->assertNotEmpty($response->diagnosis);
        $this->assertNotEmpty($response->solution);
        $this->assertNotEmpty($response->selfEvaluation);

        $resolution = app(NexusResolutionService::class);
        $gated = $resolution->resolve(
            $response->diagnosis['problem_observed'],
            $response->diagnosis,
            $response->solution,
            ['project_id' => 1, 'permissions' => ['nexus.read']],
            false,
        );

        $this->assertSame('authorization_required', $gated['status']);
        $this->assertTrue($gated['read_only']);
        $this->assertSame('required', $gated['phases'][4]['status']);
    }

    public function test_verified_experience_can_be_promoted_to_candidate_knowledge_and_memory_stays_explicit(): void
    {
        $experienceService = app(NexusExperienceService::class);
        $experience = $experienceService->remember([
            'problem' => 'El listado de proyectos no refleja la creación.',
            'solution' => 'Verificar el flujo store, index y vista.',
            'lesson' => 'Correlacionar escritura, lectura y presentación antes de concluir.',
            'confidence' => 90,
            'relevance' => 90,
        ]);
        $evaluated = $experienceService->evaluateOutcome($experience, true, 'La prueba del flujo confirmó la corrección.');
        $candidate = $experienceService->createLearningCandidate($evaluated);
        $validated = $experienceService->validateLearningCandidate($candidate, 90);
        $knowledge = $experienceService->promoteValidatedLearning($validated);

        $memoryService = app(NexusMemoryService::class);
        $memory = $memoryService->createTechnicalMemory(
            null,
            'diagnosis',
            'El flujo de proyectos requiere correlacionar creación y listado.',
            1,
            'validated_experience',
            ['experience:'.$experience->id],
            90,
            'candidate',
        );

        $this->assertSame('knowledge_candidate', $knowledge['status']);
        $this->assertNotNull($memory);
        $this->assertCount(0, $memoryService->retrieveTechnicalMemory(1));
        $validatedMemory = $memoryService->validateTechnicalMemory($memory, 'Prueba de ciclo completada.');
        $this->assertCount(1, $memoryService->retrieveTechnicalMemory(1));
        $this->assertSame('validated', $validatedMemory->status);
    }
}
