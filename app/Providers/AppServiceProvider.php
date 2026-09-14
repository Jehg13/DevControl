<?php

namespace App\Providers;

use App\Contracts\NexusModel;
use App\Nexus\NexusRuntime as CoreNexusRuntime;
use App\Nexus\NexusToolRegistry;
use App\Nexus\Tools\AuditApplyProposalTool;
use App\Nexus\Tools\AuditFindingsTool;
use App\Nexus\Tools\AuditHealthTool;
use App\Nexus\Tools\AuditProposalsTool;
use App\Nexus\Tools\AuditScanTool;
use App\Nexus\Tools\CodeAnalyzeTool;
use App\Nexus\Tools\CodeIntelligenceTool;
use App\Nexus\Tools\DatasetGenerateTool;
use App\Nexus\Tools\LearningAnalyzeTool;
use App\Nexus\Tools\CodeValidateTool;
use App\Nexus\Tools\AnalysisSaveTool;
use App\Nexus\Tools\ProjectUnderstandTool;
use App\Nexus\Tools\ProjectQueryTool;
use App\Nexus\Tools\PlanCreateTool;
use App\Nexus\Tools\GithubInspectTool;
use App\Nexus\Tools\GithubFileWriteTool;
use App\Nexus\Tools\GithubMutationTool;
use App\Nexus\Tools\InfrastructureInspectTool;
use App\Nexus\Tools\ExperienceSearchTool;
use App\Nexus\Tools\KnowledgeQueryTool;
use App\Nexus\Tools\PerformanceReportTool;
use App\Nexus\Tools\OptimizationProposalsTool;
use App\Nexus\Tools\IncidentCreateTool;
use App\Nexus\Tools\BugCreateTool;
use App\Nexus\Tools\BugDeleteTool;
use App\Nexus\Tools\BugListTool;
use App\Nexus\Tools\BugUpdateTool;
use App\Nexus\Tools\ProjectListTool;
use App\Nexus\Tools\TaskCreateTool;
use App\Nexus\Tools\TaskDeleteTool;
use App\Nexus\Tools\TaskListTool;
use App\Nexus\Tools\TaskUpdateTool;
use App\Services\Models\OpenAICompatibleNexusModel;
use App\Services\Models\UnavailableNexusModel;
use App\Services\Models\LocalNexusModel;
use App\Services\NexusInferenceEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NexusModel::class, function ($app): NexusModel {
            $adapter = match (config('nexus.ai.driver')) {
                'none' => $app->make(UnavailableNexusModel::class),
                'openai_compatible' => $app->make(OpenAICompatibleNexusModel::class),
                'local' => $app->make(LocalNexusModel::class),
                default => throw new \RuntimeException(
                    'El proveedor de Nexus configurado no está soportado: '.config('nexus.ai.driver')
                ),
            };

            return new NexusInferenceEngine($adapter);
        });

        $this->app->singleton(NexusToolRegistry::class, function ($app): NexusToolRegistry {
            return new NexusToolRegistry([
                $app->make(AuditScanTool::class),
                $app->make(CodeAnalyzeTool::class),
                $app->make(CodeIntelligenceTool::class),
                $app->make(DatasetGenerateTool::class),
                $app->make(LearningAnalyzeTool::class),
                $app->make(CodeValidateTool::class),
                $app->make(AnalysisSaveTool::class),
                $app->make(ProjectUnderstandTool::class),
                $app->make(ProjectQueryTool::class),
                $app->make(PlanCreateTool::class),
                $app->make(GithubInspectTool::class),
                $app->make(GithubFileWriteTool::class),
                $app->make(GithubMutationTool::class),
                $app->make(InfrastructureInspectTool::class),
                $app->make(ExperienceSearchTool::class),
                $app->make(KnowledgeQueryTool::class),
                $app->make(PerformanceReportTool::class),
                $app->make(OptimizationProposalsTool::class),
                $app->make(AuditFindingsTool::class),
                $app->make(AuditHealthTool::class),
                $app->make(AuditProposalsTool::class),
                $app->make(AuditApplyProposalTool::class),
                $app->make(ProjectListTool::class),
                $app->make(TaskListTool::class),
                $app->make(TaskCreateTool::class),
                $app->make(TaskUpdateTool::class),
                $app->make(TaskDeleteTool::class),
                $app->make(IncidentCreateTool::class),
                $app->make(BugListTool::class),
                $app->make(BugCreateTool::class),
                $app->make(BugUpdateTool::class),
                $app->make(BugDeleteTool::class),
            ]);
        });

        $this->app->singleton(CoreNexusRuntime::class, function ($app): CoreNexusRuntime {
            return new CoreNexusRuntime(
                $app->make(NexusToolRegistry::class),
                $app->bound(\App\Services\NexusExecutionService::class)
                    ? $app->make(\App\Services\NexusExecutionService::class)
                    : null,
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
