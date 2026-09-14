<?php

namespace Tests\Unit;

use App\Services\NexusExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusExperienceLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_experience_requires_evaluation_before_learning_candidate(): void
    {
        $service = app(NexusExperienceService::class);
        $experience = $service->remember([
            'problem' => 'Los proyectos no aparecen en el listado.',
            'solution' => 'Alinear la consulta y la redirección.',
            'lesson' => 'Verificar escritura y lectura como un solo flujo.',
            'confidence' => 85,
            'relevance' => 85,
        ]);

        $this->assertNull($service->createLearningCandidate($experience));
        $evaluated = $service->evaluateOutcome($experience, true, 'El proyecto aparece después de crear y listar.');
        $candidate = $service->createLearningCandidate($evaluated);

        $this->assertSame('evaluated_success', $evaluated->status);
        $this->assertSame('observed', $candidate->status);
        $this->assertSame($experience->id, (int) $candidate->source_id);
    }

    public function test_failed_experience_becomes_a_candidate_but_not_validated_knowledge(): void
    {
        $service = app(NexusExperienceService::class);
        $experience = $service->remember([
            'problem' => 'La relación de tareas falla.',
            'solution' => 'Cambiar el modelo.',
            'lesson' => 'No asumir la relación sin revisar claves.',
            'confidence' => 80,
            'relevance' => 80,
        ]);

        $evaluated = $service->evaluateOutcome($experience, false, 'El error continúa.', ['La consulta sigue fallando.']);
        $candidate = $service->createLearningCandidate($evaluated);

        $this->assertSame('evaluated_failure', $evaluated->status);
        $this->assertSame('observed', $candidate->status);
        $this->assertNull($service->promoteValidatedLearning($candidate));
    }

    public function test_contradictory_experience_cannot_become_learning_candidate(): void
    {
        $service = app(NexusExperienceService::class);
        $experience = $service->remember([
            'problem' => 'La ruta no responde.',
            'solution' => 'Cambiar el método HTTP.',
            'confidence' => 90,
            'relevance' => 90,
        ]);

        $evaluated = $service->evaluateOutcome(
            $experience,
            true,
            'Un entorno funciona y otro no.',
            [],
            ['La evidencia de rutas contradice el resultado.'],
        );

        $this->assertSame('contradictory', $evaluated->status);
        $this->assertNull($service->createLearningCandidate($evaluated));
    }

    public function test_only_validated_candidate_can_be_promoted_to_knowledge(): void
    {
        $service = app(NexusExperienceService::class);
        $experience = $service->remember([
            'problem' => 'La configuración cacheada causa el error.',
            'solution' => 'Limpiar la configuración.',
            'lesson' => 'Validar cache después de cambiar configuración.',
            'confidence' => 85,
            'relevance' => 85,
        ]);
        $evaluated = $service->evaluateOutcome($experience, true, 'La aplicación funciona tras limpiar cache.');
        $candidate = $service->createLearningCandidate($evaluated);
        $validated = $service->validateLearningCandidate($candidate, 90);
        $knowledge = $service->promoteValidatedLearning($validated);

        $this->assertSame('validated', $validated->status);
        $this->assertSame('knowledge_candidate', $knowledge['status']);
        $this->assertTrue($knowledge['validated']);
        $this->assertSame($experience->id, (int) $knowledge['source']['id']);
    }
}
