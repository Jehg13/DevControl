<?php

namespace Tests\Unit;

use App\Models\NexusDatasetExample;
use App\Services\NexusDatasetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusDatasetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_clean_structured_example_and_redacts_secrets(): void
    {
        $service = app(NexusDatasetService::class);
        $example = $service->ingest([
            'PROBLEMA' => 'La conexión falla',
            'CONTEXTO' => ['api_key' => 'secret', 'framework' => 'Laravel'],
            'EVIDENCIA' => ['log' => 'timeout'],
            'ANÁLISIS' => 'El servicio no responde',
            'HIPÓTESIS' => 'Configuración incorrecta',
            'DECISIÓN' => 'Revisar configuración',
            'ACCIÓN' => 'Validar variables de entorno',
            'RESULTADO' => 'Se confirmó el timeout',
            'SOLUCIÓN' => 'Corregir endpoint',
            'VALIDACIÓN' => 'Health check exitoso',
        ], 'manual', 'case-1', ['version' => '1.0', 'training_percent' => 80, 'validation_percent' => 10]);

        $this->assertNotNull($example);
        $this->assertSame('[redacted]', $example->payload['CONTEXTO']['api_key']);
        $this->assertSame(100, $example->quality_score);
        $this->assertContains($example->split, ['training', 'validation', 'test']);
    }

    public function test_it_deduplicates_and_exports_jsonl(): void
    {
        $service = app(NexusDatasetService::class);
        $record = [
            'PROBLEMA' => 'Error de autenticación',
            'CONTEXTO' => ['framework' => 'Laravel'],
            'EVIDENCIA' => ['status' => 401],
            'SOLUCIÓN' => 'Corregir middleware',
        ];

        $options = ['minimum_quality' => 20];
        $service->ingest($record, 'manual', 'one', $options);
        $service->ingest($record, 'manual', 'two', $options);

        $this->assertCount(1, NexusDatasetExample::all());
        $export = $service->export(['minimum_quality' => 40]);
        $this->assertStringContainsString('"PROBLEMA":"Error de autenticación"', $export);
        $this->assertCount(1, preg_split('/\R/', $export));
    }

    public function test_it_rejects_low_quality_examples(): void
    {
        $example = app(NexusDatasetService::class)->ingest(
            ['PROBLEMA' => 'Sin datos'],
            'manual',
            'low',
            ['minimum_quality' => 60]
        );

        $this->assertNull($example);
        $this->assertCount(0, NexusDatasetExample::all());
    }
}
