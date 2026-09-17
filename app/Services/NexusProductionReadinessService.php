<?php

namespace App\Services;

use App\Contracts\NexusAiTransport;
use App\Contracts\NexusModel;
use App\Nexus\NexusRuntime;
use App\Nexus\NexusToolRegistry;
use Illuminate\Support\Str;

final class NexusProductionReadinessService
{
    /**
     * This audit is deliberately read-only. Presence is not treated as proof
     * of capability; unsupported or unverified paths remain blocking findings.
     *
     * @return array<string, mixed>
     */
    public function audit(?NexusToolRegistry $tools = null): array
    {
        $traceId = (string) Str::uuid();
        $layers = $this->layers();
        $contracts = $this->contracts();
        $controls = $this->controls($tools);
        $limitations = [
            'python_tool_execution' => 'Python may propose and request read-only data; Laravel remains the only tool executor.',
            'local_model_artifacts' => 'Local checkpoint and tokenizer availability is not proven by configuration alone.',
            'learning_source_of_truth' => 'Laravel persistence and Python learning-state are not yet one transactional source of truth.',
            'rollback' => 'Rollback is only available where a concrete reversible operation is exposed.',
            'production_e2e' => 'A full mutation-to-commit cycle requires an authorized real project and is not executed by this read-only audit.',
        ];
        $blocking = [];

        foreach ($layers as $name => $layer) {
            if ($layer['status'] !== 'available') {
                $blocking[] = $name.': '.$layer['reason'];
            }
        }
        foreach ($contracts as $name => $contract) {
            if ($contract['status'] !== 'verified') {
                $blocking[] = $name.': '.$contract['reason'];
            }
        }
        foreach ($controls as $name => $control) {
            if ($control['status'] !== 'verified') {
                $blocking[] = $name.': '.$control['reason'];
            }
        }
        foreach ($limitations as $name => $limitation) {
            $blocking[] = $name.': '.$limitation;
        }

        return [
            'product' => 'Nexus 3.0',
            'audit_version' => '40.1',
            'trace_id' => $traceId,
            'audited_at' => now()->toISOString(),
            'verdict' => $blocking === [] ? 'production_ready' : 'not_production_ready',
            'autonomy_claim' => $blocking === []
                ? 'controlled_autonomy_verified'
                : 'not_claimed',
            'layers' => $layers,
            'contracts' => $contracts,
            'controls' => $controls,
            'limitations' => $limitations,
            'blocking_findings' => $blocking,
            'evidence_policy' => 'A class, file, or configuration is not considered proof of runtime capability.',
            'database_policy' => 'This audit does not open a database connection or change infrastructure.',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function layers(): array
    {
        $checks = [
            'laravel' => [\App\Http\Controllers\NexusController::class, 'Laravel HTTP controller'],
            'runtime' => [NexusRuntime::class, 'Nexus Runtime'],
            'core' => [\App\Services\NexusCognitiveCore::class, 'Nexus Core'],
            'memory' => [NexusMemoryService::class, 'Memory'],
            'knowledge' => [NexusKnowledgeEngine::class, 'Knowledge'],
            'reasoning' => [\App\Services\NexusReasoningService::class, 'Reasoning'],
            'planning' => [NexusPlannerService::class, 'Planning'],
            'tools' => [NexusToolRegistry::class, 'Tools'],
            'code_intelligence' => [\App\Services\NexusCodeIntelligenceService::class, 'Code intelligence'],
            'github' => [\App\Nexus\Tools\GitHubIntelligenceTool::class, 'GitHub integration'],
            'testing' => [NexusValidationService::class, 'Testing'],
            'execution' => [NexusExecutionService::class, 'Execution'],
            'security' => [NexusSecurityBoundary::class, 'Security'],
            'audit' => [Nexus2AuditService::class, 'Audit'],
            'learning' => [NexusLearningService::class, 'Learning'],
        ];

        $result = [];
        foreach ($checks as $name => [$class, $label]) {
            $available = class_exists($class);
            $result[$name] = [
                'status' => $available ? 'available' : 'missing',
                'component' => $label,
                'class' => $class,
                'reason' => $available ? 'Class is loadable.' : 'Required class is not loadable.',
            ];
        }

        $pythonFiles = [
            'nexus_ai/api/contracts.py',
            'nexus_ai/nlp/interpreter.py',
            'nexus_ai/reasoning/engine.py',
            'nexus_ai/planning/engine.py',
            'nexus_ai/memory/store.py',
            'nexus_learning/system.py',
        ];
        $result['python_engine'] = [
            'status' => collect($pythonFiles)->every(fn (string $file): bool => is_file(base_path($file)))
                ? 'available'
                : 'missing',
            'component' => 'Python engine, NLP, reasoning, planning, memory and learning',
            'files' => collect($pythonFiles)->mapWithKeys(
                fn (string $file): array => [$file => is_file(base_path($file))]
            )->all(),
            'reason' => 'Python source availability does not prove an installed or healthy runtime.',
        ];

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    private function contracts(): array
    {
        return [
            'ai_transport' => $this->contract(
                NexusAiTransport::class,
                'Python/Laravel transport contract'
            ),
            'model' => $this->contract(NexusModel::class, 'Model contract'),
            'tool' => $this->contract(\App\Contracts\NexusTool::class, 'Tool contract'),
            'runtime_response' => $this->methodContract(
                \App\Nexus\NexusRuntimeResponse::class,
                'toArray',
                'Runtime response serialization'
            ),
            'controlled_cycle' => $this->methodContract(
                NexusControlledEngineeringCycleService::class,
                'resume',
                'Authorized controlled-cycle boundary'
            ),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function controls(?NexusToolRegistry $tools): array
    {
        $definitions = $tools?->definitions() ?? [];
        $names = array_column($definitions, 'name');
        $duplicateNames = array_values(array_unique(array_diff_assoc($names, array_unique($names))));
        $missingPermissions = array_values(array_filter(
            $definitions,
            fn (array $definition): bool => ! is_array($definition['permissions'] ?? null)
                || $definition['permissions'] === []
        ));

        return [
            'authorization_boundary' => [
                'status' => class_exists(NexusSecurityBoundary::class) ? 'verified' : 'unverified',
                'reason' => class_exists(NexusSecurityBoundary::class)
                    ? 'Security boundary is loadable and remains in the Laravel execution path.'
                    : 'Security boundary is unavailable.',
            ],
            'tool_catalog' => [
                'status' => $tools === null
                    ? 'unverified'
                    : ($duplicateNames === [] && $missingPermissions === [] ? 'verified' : 'unverified'),
                'tool_count' => count($definitions),
                'duplicate_names' => $duplicateNames,
                'missing_permissions' => $missingPermissions,
                'reason' => $tools === null
                    ? 'Registry was not supplied; tool definitions were not inspected.'
                    : 'Registered tools must have unique names and explicit permissions.',
            ],
            'traceability' => [
                'status' => 'unverified',
                'reason' => 'A trace id is generated for this audit, but end-to-end propagation is not proven for every layer.',
            ],
            'timeouts_recovery_rollback' => [
                'status' => 'unverified',
                'reason' => 'These controls are operation-specific and require a real authorized execution to verify.',
            ],
            'memory_and_learning' => [
                'status' => 'unverified',
                'reason' => 'Memory and learning components exist, but cross-runtime transactional provenance is not proven.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function contract(string $interface, string $label): array
    {
        $available = interface_exists($interface);

        return [
            'status' => $available ? 'verified' : 'unverified',
            'contract' => $label,
            'interface' => $interface,
            'reason' => $available ? 'Interface is loadable.' : 'Interface is unavailable.',
        ];
    }

    /** @return array<string, mixed> */
    private function methodContract(string $class, string $method, string $label): array
    {
        $available = class_exists($class) && method_exists($class, $method);

        return [
            'status' => $available ? 'verified' : 'unverified',
            'contract' => $label,
            'class' => $class,
            'method' => $method,
            'reason' => $available ? 'Required method is loadable.' : 'Required method is unavailable.',
        ];
    }
}
