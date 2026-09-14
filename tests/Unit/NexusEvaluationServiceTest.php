<?php

namespace Tests\Unit;

use App\Services\NexusEvaluationService;
use Tests\TestCase;

class NexusEvaluationServiceTest extends TestCase
{
    public function test_it_exposes_a_fixed_benchmark_catalog(): void
    {
        $service = new NexusEvaluationService();

        $this->assertCount(12, $service->benchmarks());
        $this->assertSame(
            ['PHP', 'Laravel', 'Flutter', 'Dart', 'SQL', 'debugging', 'arquitectura', 'DevControl', 'diagnóstico', 'tool calling', 'planificación', 'seguridad'],
            array_column($service->benchmarks(), 'category')
        );
    }

    public function test_it_scores_only_declared_metrics_and_requires_safe_permissions(): void
    {
        $service = new NexusEvaluationService();
        $benchmark = $service->benchmarks()[9];

        $result = $service->evaluate([
            'exactness' => true,
            'errors' => 0,
            'unsupported_claims' => 0,
            'code_issues' => 0,
            'diagnosis_correct' => true,
            'tool_calling_correct' => true,
            'tool_calls_validated' => true,
            'permission_compliance' => false,
            'memory_recall' => true,
            'plan_valid' => true,
        ], $benchmark);

        $this->assertSame(0.0, $result['metrics']['permissions']);
        $this->assertFalse($result['passed']);
    }

    public function test_it_compares_versions_by_coverage_score_and_passes(): void
    {
        $service = new NexusEvaluationService();
        $response = [
            'exactness' => true,
            'errors' => 0,
            'unsupported_claims' => 0,
            'code_issues' => 0,
            'diagnosis_correct' => true,
            'tool_calling_correct' => true,
            'tool_calls_validated' => true,
            'permission_compliance' => true,
            'memory_recall' => true,
            'plan_valid' => true,
        ];

        $result = $service->compare([
            'Nexus AI v0.1' => ['php.syntax' => $response],
            'Nexus AI v0.2' => ['php.syntax' => $response, 'sql.query' => $response],
        ]);

        $this->assertSame('Nexus AI v0.2', $result['ranking'][0]);
        $this->assertSame(2, $result['versions']['Nexus AI v0.2']['evaluated']);
        $this->assertSame(1, $result['versions']['Nexus AI v0.1']['evaluated']);
    }
}
