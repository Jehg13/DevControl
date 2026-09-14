<?php

namespace Tests\Unit;

use App\Models\NexusMemory;
use App\Services\NexusMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusTechnicalMemoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_candidate_with_traceable_origin_and_evidence(): void
    {
        $service = app(NexusMemoryService::class);

        $memory = $service->createTechnicalMemory(
            null,
            'architecture',
            'La ruta de proyectos entra por ProyectoController.',
            1,
            'project_understanding',
            ['routes/web.php'],
            80,
        );

        $this->assertInstanceOf(NexusMemory::class, $memory);
        $this->assertSame('candidate', $memory->status);
        $this->assertSame('project_understanding', $memory->metadata['origin']);
        $this->assertSame(['routes/web.php'], $memory->metadata['evidence']);
        $this->assertNull($memory->validated_at);
    }

    public function test_rejects_technical_memory_without_evidence(): void
    {
        $memory = app(NexusMemoryService::class)->createTechnicalMemory(
            null,
            'flow',
            'El sistema persiste el proyecto.',
            1,
            'runtime',
            [],
            90,
        );

        $this->assertNull($memory);
        $this->assertDatabaseMissing('nexus_memories', [
            'content' => 'El sistema persiste el proyecto.',
        ]);
    }

    public function test_validates_and_recovers_only_validated_technical_memory(): void
    {
        $service = app(NexusMemoryService::class);
        $memory = $service->createTechnicalMemory(
            null,
            'relation',
            'Proyecto tiene muchas tareas.',
            1,
            'code_analysis',
            ['app/Models/Proyecto.php', 'app/Models/Tarea.php'],
            70,
        );

        $validated = $service->validateTechnicalMemory($memory, 'Confirmado en ambas entidades.');
        $this->assertSame('validated', $validated->status);
        $this->assertNotNull($validated->validated_at);
        $this->assertGreaterThanOrEqual(75, $validated->confidence);
        $this->assertCount(1, $service->retrieveTechnicalMemory(1, 'relation'));
    }

    public function test_invalidates_technical_memory_and_removes_it_from_retrieval(): void
    {
        $service = app(NexusMemoryService::class);
        $memory = $service->createTechnicalMemory(
            null,
            'diagnosis',
            'El filtro excluye el proyecto.',
            1,
            'diagnosis',
            ['app/Http/Controllers/ProyectoController.php'],
            90,
            'validated',
        );

        $invalidated = $service->invalidateTechnicalMemory($memory, 'El código fue corregido.');
        $this->assertSame('invalidated', $invalidated->status);
        $this->assertSame([], $service->retrieveTechnicalMemory(1)->all());
        $this->assertSame('El código fue corregido.', $invalidated->metadata['invalidated_reason']);
    }
}
