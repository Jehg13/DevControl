<?php

namespace Tests\Unit;

use App\Contracts\NexusModel;
use App\Exceptions\NexusModelException;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;
use App\Services\Models\UnavailableNexusModel;
use App\Services\NexusInferenceEngine;
use Tests\TestCase;

class NexusInferenceEngineTest extends TestCase
{
    public function test_none_provider_reports_controlled_unavailability(): void
    {
        $engine = new NexusInferenceEngine(new UnavailableNexusModel());

        $this->expectException(NexusModelException::class);
        $this->expectExceptionMessage('No hay un modelo de inferencia configurado');

        $engine->complete(new NexusModelRequest('Nexus', 'Analiza el proyecto', [], []));
    }

    public function test_engine_delegates_to_internal_model_contract(): void
    {
        $model = new class implements NexusModel
        {
            public function complete(NexusModelRequest $request): NexusModelResponse
            {
                return new NexusModelResponse('test', 'Respuesta interna.');
            }
        };

        $response = (new NexusInferenceEngine($model))->complete(
            new NexusModelRequest('Nexus', 'Prueba', [], [])
        );

        $this->assertSame('test', $response->intent);
        $this->assertTrue((new NexusInferenceEngine($model))->capabilities()->toolCalling);
    }

    public function test_application_binds_none_provider_without_external_fallback(): void
    {
        config(['nexus.ai.driver' => 'none']);
        app()->forgetInstance(NexusModel::class);

        $model = app(NexusModel::class);

        $this->assertInstanceOf(NexusInferenceEngine::class, $model);
        $this->assertFalse($model->capabilities()->textGeneration);
        $this->assertFalse($model->capabilities()->toolCalling);
    }
}
