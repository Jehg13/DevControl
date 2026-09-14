<?php

namespace Tests\Unit;

use App\Models\NexusExperience;
use App\Services\NexusExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusExperienceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_structured_relevant_experiences_and_redacts_sensitive_values(): void
    {
        $experience = app(NexusExperienceService::class)->remember([
            'problem' => 'Laravel no encuentra la base de datos',
            'context' => ['technology' => 'Laravel', 'password' => 'secret-value'],
            'hypothesis' => 'Configuración cacheada incorrecta',
            'action' => 'Revisar configuración',
            'result' => ['ok' => true],
            'solution' => 'Limpiar la configuración cacheada',
            'lesson' => 'Verificar cache cuando .env parece correcto',
            'strategy' => 'diagnóstico incremental',
            'technologies' => ['Laravel', 'PHP'],
            'category' => 'configuration',
            'relevance' => 90,
            'confidence' => 85,
        ]);

        $this->assertNotNull($experience);
        $this->assertSame('active', $experience->status);
        $this->assertSame('[redacted]', $experience->context['password']);
        $this->assertCount(1, NexusExperience::all());
    }

    public function test_it_retrieves_similar_experiences_and_ignores_invalidated_ones(): void
    {
        $service = app(NexusExperienceService::class);
        $valid = $service->remember([
            'problem' => 'Laravel falla al conectar con la base de datos',
            'solution' => 'Limpiar configuración cacheada',
            'lesson' => 'Revisar configuración cacheada',
            'technologies' => ['Laravel'],
            'relevance' => 80,
            'confidence' => 80,
        ]);
        $invalid = $service->remember([
            'problem' => 'Laravel falla al conectar con Redis',
            'solution' => 'Reiniciar Redis',
            'technologies' => ['Laravel'],
            'relevance' => 80,
            'confidence' => 80,
        ]);
        $service->invalidate($invalid, 'La hipótesis fue incorrecta.');

        $results = $service->similar('Laravel no encuentra la base de datos', null, ['Laravel']);

        $this->assertSame($valid->id, $results[0]['id']);
        $this->assertCount(1, $results);
    }

    public function test_it_corrects_an_experience_without_treating_it_as_confirmed_truth(): void
    {
        $service = app(NexusExperienceService::class);
        $experience = $service->remember([
            'problem' => 'Falla de autenticación',
            'solution' => 'Cambiar la sesión',
            'relevance' => 80,
            'confidence' => 80,
        ]);

        $corrected = $service->correct($experience, 'La causa real era el middleware.', 65);

        $this->assertSame('corrected', $corrected->status);
        $this->assertSame(65, $corrected->confidence);
        $this->assertSame('La causa real era el middleware.', $corrected->correction);
    }
}
