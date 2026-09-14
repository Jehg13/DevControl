<?php

namespace App\Services;

use App\Nexus\NexusToolRegistry;

final class NexusCognitiveCore
{
    private const CATEGORIES = [
        'project_understanding' => ['proyecto', 'estructura', 'módulo', 'modulo', 'tecnología', 'tecnologia', 'login', 'autenticación', 'autenticacion'],
        'analysis' => ['analiza', 'analizar', 'revisa', 'revisar', 'problema', 'hallazgo', 'diagnóstico', 'diagnostico'],
        'planning' => ['plan', 'planifica', 'implementar', 'agregar', 'añadir', 'anadir', 'funcionalidad'],
        'task_management' => ['tarea', 'bug', 'incidencia', 'ticket'],
        'infrastructure' => ['servidor', 'vps', 'ram', 'cpu', 'ssl', 'disco', 'infraestructura', 'servicio'],
        'github' => ['github', 'repositorio', 'rama', 'commit', 'pull request', 'issue'],
    ];

    public function __construct(
        private readonly NexusKnowledgeEngine $knowledge,
        private readonly NexusExperienceService $experiences,
        private readonly NexusToolRegistry $tools,
        private readonly NexusPermissionManager $permissions,
    ) {
    }

    public function evaluate(string $goal, array $context = []): array
    {
        $classification = $this->classify($goal);
        $knowledge = $this->knowledge->search($context, $goal);
        $experiences = $this->experiences->similar(
            $goal,
            $context['project_id'] ?? $context['proyecto_id'] ?? null,
            (array) ($context['technologies'] ?? []),
            5
        );
        $actions = $this->availableActions($goal, $context);
        $plan = $this->plan($goal, $classification, $actions);

        return [
            'goal' => $goal,
            'classification' => $classification,
            'known' => [
                'context' => $knowledge,
                'experiences' => $experiences,
            ],
            'unknown' => $this->unknown($context, $knowledge),
            'available_actions' => $actions,
            'plan' => $plan,
            'expected_result' => $this->expectedResult($classification),
            'decision' => $this->decision($actions, $plan),
            'permission_policy' => $this->permissions->policy(),
            'rules' => [
                'llm_required' => false,
                'execution_allowed' => false,
                'permission_manager_authority' => true,
            ],
        ];
    }

    public function classify(string $goal): array
    {
        $normalized = mb_strtolower($goal);
        $scores = [];
        foreach (self::CATEGORIES as $category => $terms) {
            $scores[$category] = 0;
            foreach ($terms as $term) {
                if (str_contains($normalized, $term)) {
                    $scores[$category]++;
                }
            }
        }

        arsort($scores);
        $category = array_key_first(array_filter($scores, fn (int $score): bool => $score > 0)) ?: 'unknown';

        return [
            'category' => $category,
            'scores' => $scores,
            'confidence' => $category === 'unknown' ? 0 : min(100, $scores[$category] * 25),
            'basis' => $category === 'unknown' ? 'no_matching_rule' : 'keyword_rules',
        ];
    }

    public function availableActions(string $goal, array $context = []): array
    {
        $tokens = $this->tokens($goal);
        $actions = [];
        foreach ($this->tools->definitions() as $definition) {
            $text = $definition['name'].' '.$definition['description'];
            $score = count(array_intersect($tokens, $this->tokens($text)));
            if ($score === 0) {
                continue;
            }

            $permissions = (array) ($definition['permissions'] ?? []);
            $granted = array_values(array_intersect(
                $permissions,
                (array) ($context['granted_permissions'] ?? $context['permissions'] ?? [])
            ));
            $actions[] = [
                'tool' => $definition['name'],
                'score' => $score,
                'permissions' => $permissions,
                'granted_permissions' => $granted,
                'requires_permission' => array_diff($permissions, $granted) !== [],
                'requires_confirmation' => $definition['requires_confirmation'],
                'risk_level' => $this->permissions->risk($definition['name'], $permissions),
            ];
        }

        return array_values(array_slice(
            collect($actions)->sortByDesc('score')->values()->all(),
            0,
            10
        ));
    }

    public function evaluateCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $actual === $expected,
            'not_equals' => $actual !== $expected,
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            default => false,
        };
    }

    private function plan(string $goal, array $classification, array $actions): array
    {
        if ($actions === []) {
            return [[
                'id' => 'investigate',
                'description' => 'Investigar la información necesaria para determinar una acción disponible.',
                'dependencies' => [],
                'status' => 'pending',
                'required_tools' => [],
            ]];
        }

        $selectedActions = array_slice(
            $actions,
            0,
            $classification['category'] === 'unknown' ? 1 : 3
        );

        return array_map(
            fn (array $action, int $index): array => [
                'id' => 'cognitive-step-'.($index + 1),
                'description' => 'Evaluar y, si está autorizado, utilizar '.$action['tool'].'.',
                'dependencies' => $index === 0 ? [] : ['cognitive-step-'.$index],
                'status' => 'pending',
                'required_tools' => [$action['tool']],
                'requires_permission' => $action['requires_permission'],
            ],
            $selectedActions,
            array_keys($selectedActions)
        );
    }

    private function decision(array $actions, array $plan): array
    {
        if ($actions === []) {
            return ['type' => 'investigate', 'reason' => 'No hay una acción registrada que coincida con el objetivo.'];
        }
        if (collect($actions)->every(fn (array $action): bool => $action['requires_permission'])) {
            return ['type' => 'blocked', 'reason' => 'Las acciones candidatas requieren permisos no concedidos.'];
        }

        return ['type' => 'propose', 'reason' => 'Existe al menos una acción registrada; la ejecución requiere una capa posterior autorizada.'];
    }

    private function expectedResult(array $classification): string
    {
        return match ($classification['category']) {
            'project_understanding' => 'Evidencia estructurada sobre el proyecto.',
            'analysis' => 'Hallazgos basados en evidencia disponible.',
            'planning' => 'Pasos verificables con dependencias.',
            'task_management' => 'Consulta o cambio controlado en DevControl.',
            'infrastructure' => 'Estado observado de la infraestructura.',
            'github' => 'Información verificable del repositorio.',
            default => 'Información suficiente para decidir el siguiente paso.',
        };
    }

    private function unknown(array $context, array $knowledge): array
    {
        $unknown = [];
        if (($context['project_id'] ?? $context['proyecto_id'] ?? null) === null) {
            $unknown[] = 'project_id';
        }
        if ($knowledge === []) {
            $unknown[] = 'evidence_for_goal';
        }

        return $unknown;
    }

    private function tokens(string $value): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}_]+/u', mb_strtolower($value)) ?: [],
            fn (string $token): bool => mb_strlen($token) > 2
        ));
    }
}
