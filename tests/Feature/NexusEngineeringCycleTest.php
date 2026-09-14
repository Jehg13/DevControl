<?php

namespace Tests\Feature;

use App\Nexus\NexusRuntime;
use App\Nexus\NexusRuntimeRequest;
use App\Services\NexusExperienceService;
use App\Services\NexusMemoryService;
use App\Services\NexusResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusEngineeringCycleTest extends TestCase
{
    use RefreshDatabase;

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
