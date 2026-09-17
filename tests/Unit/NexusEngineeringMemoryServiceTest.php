<?php

namespace Tests\Unit;

use App\Services\NexusMemoryService;
use Tests\TestCase;

class NexusEngineeringMemoryServiceTest extends TestCase
{
    public function test_rejects_technical_facts_without_evidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requiere evidencia');

        (new NexusMemoryService())->recordEngineeringExperience([
            'type' => 'fact',
            'content' => 'La migración falló por una columna inexistente.',
        ], 1, 10);
    }

    public function test_rejects_validated_knowledge_with_low_confidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('confianza mínima');

        (new NexusMemoryService())->recordEngineeringExperience([
            'type' => 'validated_knowledge',
            'content' => 'El despliegue requiere ejecutar las migraciones.',
            'confidence' => 60,
            'evidence' => [['source' => 'test']],
        ], 1, 10);
    }

    public function test_requires_an_authenticated_owner_for_technical_memory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('usuario autenticado');

        (new NexusMemoryService())->recordEngineeringExperience([
            'content' => 'Experiencia técnica validada.',
        ], null, 10);
    }
}
