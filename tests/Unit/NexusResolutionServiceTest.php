<?php

namespace Tests\Unit;

use App\Models\NexusRun;
use App\Nexus\NexusToolRegistry;
use App\Services\NexusExecutionService;
use App\Services\NexusResolutionService;
use Mockery;
use Tests\TestCase;

class NexusResolutionServiceTest extends TestCase
{
    public function test_it_stops_before_execution_without_explicit_authorization(): void
    {
        $execution = Mockery::mock(NexusExecutionService::class);
        $execution->shouldReceive('execute')->never();

        $result = (new NexusResolutionService($execution, app(NexusToolRegistry::class)))->resolve(
            'Los proyectos no aparecen.',
            ['diagnosis' => 'evidencia insuficiente'],
            ['status' => 'propuesta', 'not_confirmed' => true, 'objective' => 'Corregir el listado'],
        );

        $this->assertSame('authorization_required', $result['status']);
        $this->assertTrue($result['read_only']);
        $this->assertSame('required', $result['phases'][4]['status']);
        $this->assertSame('pending', $result['phases'][5]['status']);
    }

    public function test_it_rejects_a_proposal_that_is_not_explicitly_unconfirmed(): void
    {
        $execution = Mockery::mock(NexusExecutionService::class);
        $execution->shouldReceive('execute')->never();

        $result = (new NexusResolutionService($execution, app(NexusToolRegistry::class)))->resolve(
            'Problema',
            [],
            ['status' => 'confirmed', 'not_confirmed' => false],
            [],
            true,
        );

        $this->assertSame('rejected', $result['status']);
        $this->assertSame('rejected', $result['phases'][4]['status']);
    }
}
