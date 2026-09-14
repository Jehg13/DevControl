<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

final class Nexus2AuditService
{
    private const REQUIRED_FILES = [
        'app/Services/NexusCognitiveCore.php',
        'app/Services/NexusMemoryService.php',
        'app/Services/NexusKnowledgeEngine.php',
        'app/Services/NexusExperienceService.php',
        'app/Services/NexusCodeIntelligenceService.php',
        'app/Services/NexusPlannerService.php',
        'app/Services/NexusReflectionService.php',
        'app/Nexus/NexusToolRegistry.php',
        'app/Services/NexusPermissionManager.php',
        'app/Services/NexusGithubService.php',
        'app/Services/NexusInfrastructureService.php',
        'app/Services/NexusLearningService.php',
        'app/Services/NexusDatasetService.php',
        'nexus_runtime/runtime.py',
        'app/Services/NexusProactiveService.php',
    ];

    public function audit(): array
    {
        $files = collect(self::REQUIRED_FILES)->mapWithKeys(
            fn (string $file): array => [$file => is_file(base_path($file))]
        )->all();
        $missing = array_keys(array_filter($files, fn (bool $exists): bool => ! $exists));
        $artifacts = [
            'checkpoint' => is_file(base_path((string) config('nexus.local.checkpoint'))),
            'tokenizer' => is_file(base_path((string) config('nexus.local.tokenizer'))),
            'runtime_manifest' => is_file(base_path((string) env('NEXUS_RUNTIME_MANIFEST', 'config/nexus-runtime.json'))),
        ];
        $localReady = ! in_array(false, $artifacts, true);
        $externalProviders = [
            'openai_compatible_adapter_available' => class_exists(\App\Services\Models\OpenAICompatibleNexusModel::class),
            'production_driver_is_local' => config('nexus.ai.driver') === 'local',
            'external_required_by_local_runtime' => false,
        ];
        $database = [
            'nexus_hallazgos' => Schema::hasTable('nexus_hallazgos'),
            'nexus_proactive_alerts' => Schema::hasTable('nexus_proactive_alerts'),
            'nexus_runs' => Schema::hasTable('nexus_runs'),
            'nexus_memories' => Schema::hasTable('nexus_memories'),
            'nexus_learning_records' => Schema::hasTable('nexus_learning_records'),
        ];
        $chain = [
            'user_to_nexus' => $this->classExists(\App\Http\Controllers\NexusController::class),
            'nexus_to_cognitive_core' => $this->classExists(NexusCognitiveCore::class),
            'cognitive_to_knowledge' => $this->classExists(NexusKnowledgeEngine::class),
            'cognitive_to_memory' => $this->classExists(NexusMemoryService::class),
            'cognitive_to_planner' => $this->classExists(NexusPlannerService::class),
            'planner_to_permissions' => $this->classExists(NexusPermissionManager::class),
            'permissions_to_tools' => $this->classExists(\App\Nexus\NexusToolRegistry::class),
            'tools_to_project' => $this->classExists(\App\Services\NexusExecutionService::class),
            'result_to_reflection' => $this->classExists(NexusReflectionService::class),
            'reflection_to_learning' => $this->classExists(NexusLearningService::class),
            'learning_to_dataset' => $this->classExists(NexusDatasetService::class),
        ];
        $capabilities = [
            'project_understanding' => $this->classExists(NexusProjectUnderstandingService::class),
            'code_intelligence' => $this->classExists(NexusCodeIntelligenceService::class),
            'diagnosis' => $this->classExists(NexusAuditService::class),
            'planning' => $this->classExists(NexusPlannerService::class),
            'permissions' => $this->classExists(NexusPermissionManager::class),
            'proactive_intelligence' => $this->classExists(NexusProactiveService::class),
            'local_model_artifacts' => $localReady,
            'local_tool_calling' => false,
        ];
        $blocking = [];
        if ($missing !== []) {
            $blocking[] = 'faltan componentes: '.implode(', ', $missing);
        }
        if (! $localReady) {
            $blocking[] = 'faltan artefactos locales verificables (checkpoint, tokenizer o manifest)';
        }
        if (! $capabilities['local_tool_calling']) {
            $blocking[] = 'el modelo local todavía no produce tool calls estructurados';
        }
        if (in_array(false, $chain, true)) {
            $blocking[] = 'la cadena end-to-end no puede considerarse demostrada solo por existencia de clases';
        }

        return [
            'product' => 'Nexus AI 2.0',
            'audited_at' => now()->toISOString(),
            'verdict' => $blocking === [] ? 'ready_for_end_to_end_validation' : 'not_ready',
            'claim' => 'IA especializada y autónoma dentro de los límites de su arquitectura y permisos; no consciencia real.',
            'architecture' => $files,
            'artifacts' => $artifacts,
            'external_dependencies' => $externalProviders,
            'database' => $database,
            'chain' => $chain,
            'capabilities' => $capabilities,
            'blocking_findings' => $blocking,
            'required_proof' => [
                'run_real_local_checkpoint',
                'execute_full_benchmark_against_that_checkpoint',
                'complete_confirmed_tool_call_round_trip',
                'verify_reflection_learning_dataset_with_approval',
                'verify_rollback_and_health_check',
            ],
        ];
    }

    private function classExists(string $class): bool
    {
        return class_exists($class);
    }
}
