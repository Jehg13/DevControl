<?php

namespace App\Providers;

use App\Contracts\NexusModel;
use App\Nexus\NexusToolRegistry;
use App\Nexus\Tools\AuditApplyProposalTool;
use App\Nexus\Tools\AuditFindingsTool;
use App\Nexus\Tools\AuditHealthTool;
use App\Nexus\Tools\AuditProposalsTool;
use App\Nexus\Tools\AuditScanTool;
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
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NexusModel::class, function ($app): NexusModel {
            return match (config('nexus.ai.driver')) {
                'openai_compatible' => $app->make(OpenAICompatibleNexusModel::class),
                default => throw new \RuntimeException(
                    'El proveedor de Nexus configurado no está soportado: '.config('nexus.ai.driver')
                ),
            };
        });

        $this->app->singleton(NexusToolRegistry::class, function ($app): NexusToolRegistry {
            return new NexusToolRegistry([
                $app->make(AuditScanTool::class),
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
