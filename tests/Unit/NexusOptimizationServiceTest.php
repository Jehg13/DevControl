<?php

namespace Tests\Unit;

use App\Models\NexusOptimizationProposal;
use App\Models\NexusRun;
use App\Models\NexusToolCall;
use App\Models\User;
use App\Services\NexusOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusOptimizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_metrics_and_detects_redundant_tools(): void
    {
        $run = NexusRun::create([
            'message' => 'Analizar autenticación',
            'source' => 'test',
            'status' => 'completed',
            'steps' => 7,
            'context' => [],
        ]);
        NexusToolCall::create([
            'nexus_run_id' => $run->id,
            'sequence' => 1,
            'tool_name' => 'nexus.code.analyze',
            'status' => 'completed',
        ]);
        NexusToolCall::create([
            'nexus_run_id' => $run->id,
            'sequence' => 2,
            'tool_name' => 'nexus.code.analyze',
            'status' => 'completed',
        ]);

        $service = app(NexusOptimizationService::class);
        $metric = $service->record($run);
        $report = $service->report();
        $proposals = $service->propose();

        $this->assertSame(['nexus.code.analyze'], $metric->redundant_tools);
        $this->assertSame(7.0, $report['average_steps']);
        $this->assertNotEmpty($report['redundant_tools']);
        $this->assertTrue(collect($proposals)->contains(
            fn (NexusOptimizationProposal $proposal) => str_starts_with($proposal->proposal_key, 'redundant_tool:')
        ));
    }

    public function test_approval_is_explicit_and_does_not_implement_the_proposal(): void
    {
        $proposal = NexusOptimizationProposal::create([
            'proposal_key' => 'test:global',
            'category' => 'plan',
            'observation' => 'Observación medida',
            'proposal' => 'Probar una estrategia alternativa',
            'status' => 'proposed',
            'risk' => 'medium',
        ]);

        $user = User::create([
            'name' => 'Reviewer',
            'email' => 'reviewer@example.com',
            'password' => 'password',
            'rol' => 'admin',
        ]);
        $approved = app(NexusOptimizationService::class)->approve($proposal, $user->id);

        $this->assertSame('approved', $approved->status);
        $this->assertSame($user->id, $approved->approved_by);
        $this->assertNull($approved->implemented_at);
    }
}
