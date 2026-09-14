<?php

namespace Tests\Unit;

use App\Models\NexusKnowledgeClaim;
use App\Services\NexusKnowledgeGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class NexusKnowledgeGraphServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_traceable_typed_relationships_and_searches_them(): void
    {
        $service = app(NexusKnowledgeGraphService::class);

        $service->relate(
            ['type' => 'project', 'key' => 'project:1', 'label' => 'DevControl'],
            'contains_file',
            ['type' => 'file', 'key' => 'file:app/Models/User.php', 'label' => 'app/Models/User.php'],
            'FACT',
            'project_understanding',
            'understanding:10',
            null,
            95,
            'fingerprint-10'
        );

        $results = $service->search('¿Qué archivo contiene el modelo User?');

        $this->assertCount(1, $results);
        $this->assertSame('FACT', $results[0]['epistemic_type']);
        $this->assertSame('project_understanding', $results[0]['source']['type']);
        $this->assertSame('fingerprint-10', $results[0]['source']['fingerprint']);
    }

    public function test_it_versions_changes_and_keeps_old_claims_traceable(): void
    {
        $service = app(NexusKnowledgeGraphService::class);
        $first = $service->fact(
            ['type' => 'application', 'key' => 'app:nexus', 'label' => 'Nexus'],
            'uses_database',
            'mysql',
            'FACT',
            'manual',
            'note:1',
            null,
            90
        );
        $second = $service->fact(
            ['type' => 'application', 'key' => 'app:nexus', 'label' => 'Nexus'],
            'uses_database',
            'sqlite',
            'INFERENCE',
            'reflection',
            'run:2',
            null,
            60
        );

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame($first->id, $second->supersedes_id);
    }

    public function test_it_reports_impact_and_invalidates_a_claim(): void
    {
        $service = app(NexusKnowledgeGraphService::class);
        $claim = $service->relate(
            ['type' => 'route', 'key' => 'route:login', 'label' => '/login'],
            'handled_by',
            ['type' => 'controller', 'key' => 'controller:AuthController', 'label' => 'AuthController']
        );

        $this->assertCount(1, $service->impact('controller', 'controller:AuthController'));
        $service->invalidate($claim, 'La ruta fue eliminada.');

        $this->assertSame('invalidated', $claim->fresh()->status);
        $this->assertCount(0, $service->impact('controller', 'controller:AuthController'));
    }

    public function test_it_rejects_unclassified_knowledge(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NexusKnowledgeGraphService::class)->fact(
            ['type' => 'file', 'key' => 'file:unknown', 'label' => 'unknown'],
            'is',
            'certain',
            'CERTAIN',
            'manual'
        );
    }
}
