<?php

namespace Tests\Unit;

use App\Models\NexusExperience;
use App\Models\NexusLearningRecord;
use App\Services\NexusLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusLearningServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_repeated_successful_strategies_as_inferred_learning(): void
    {
        foreach (['Revisar configuración', 'Revisar configuración'] as $index => $strategy) {
            NexusExperience::create([
                'problem' => 'Error de configuración '.$index,
                'strategy' => $strategy,
                'solution' => 'Corregir la configuración',
                'confidence' => 85,
                'relevance' => 80,
                'status' => 'active',
            ]);
        }

        $result = app(NexusLearningService::class)->analyze();
        $record = NexusLearningRecord::first();

        $this->assertSame(1, $result['created']);
        $this->assertSame('inferred', $record->status);
        $this->assertSame('effective_strategy', $record->pattern);
    }

    public function test_it_requires_validation_before_apply_and_supports_rollback(): void
    {
        $record = NexusLearningRecord::create([
            'learning_key' => 'strategy:test',
            'learning_type' => 'strategy',
            'status' => 'observed',
            'pattern' => 'effective_strategy',
            'observation' => ['strategy' => 'test'],
            'evidence' => ['experience_count' => 2],
            'proposed_update' => ['strategy' => 'test'],
            'confidence' => 70,
            'source_type' => 'experience',
        ]);
        $service = app(NexusLearningService::class);
        $this->expectException(\InvalidArgumentException::class);
        $service->apply($record);
    }

    public function test_it_applies_validated_learning_and_rolls_back(): void
    {
        $record = NexusLearningRecord::create([
            'learning_key' => 'strategy:validated',
            'learning_type' => 'strategy',
            'status' => 'validated',
            'pattern' => 'effective_strategy',
            'observation' => ['strategy' => 'test'],
            'evidence' => ['experience_count' => 2],
            'proposed_update' => ['strategy' => 'test'],
            'confidence' => 90,
            'source_type' => 'experience',
        ]);
        $service = app(NexusLearningService::class);
        $applied = $service->apply($record);
        $this->assertSame(['strategy' => 'test'], $applied->applied_update);
        $rolledBack = $service->rollback($applied);

        $this->assertNotNull($rolledBack->rolled_back_at);
        $this->assertNull($rolledBack->applied_update);
    }
}
