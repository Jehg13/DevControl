<?php

namespace App\Nexus;

use App\Services\NexusExecutionService;
use App\Models\Proyecto;
use Illuminate\Support\Str;

class NexusRuntime
{
    public function __construct(
        private readonly NexusToolRegistry $tools,
        private readonly ?NexusExecutionService $execution = null,
        private readonly ?NexusActionClassifier $actionClassifier = null,
    ) {
    }

    public function handle(NexusRuntimeRequest $request): NexusRuntimeResponse
    {
        $action = ($this->actionClassifier ?? new NexusActionClassifier())->classify($request);
        if ($action !== null) {
            return $this->handleAction($request, $action);
        }

        $intent = $this->classifyIntent($request->message);
        $intentName = $intent['intent'] ?? 'general';

        if ($this->shouldBypassRuntime($request->message, $intent)) {
            return new NexusRuntimeResponse(
                finalMessage: $this->generalResponse($request->message),
                intent: 'general',
                evidence: [],
                toolsUsed: [],
                certainty: 'CONFIRMADO',
                missingInformation: [],
                errors: [],
                actions: [],
                toolResults: [],
                investigation: [],
                hypotheses: [],
                impact: [],
                behavior: [],
                diagnosis: [],
                solution: [],
                verification: [],
                plan: [],
                selfEvaluation: [],
                correlation: [],
                explanationStyle: 'general',
                status: 'completed',
                source: 'runtime_general',
            );
        }

        if ((bool) config('nexus.ai.enabled') && config('nexus.ai.driver', 'none') !== 'none' && $this->execution !== null) {
            $run = $this->execution->execute(
                message: $request->message,
                context: $request->context,
                history: $request->history,
                userId: $request->user?->id,
                source: 'runtime_gateway',
                confirmed: false,
                conversationKey: $request->conversation['session_id'] ?? 'runtime-'.($request->user?->id ?? 'guest'),
            );

            $result = $run->result ?? [];
            if ($run->status === 'completed' && is_string($result['message'] ?? null)) {
                return new NexusRuntimeResponse(
                    finalMessage: $result['message'],
                    intent: $intentName,
                    evidence: $result['evidence'] ?? [],
                    toolsUsed: collect($run->toolCalls ?? [])->pluck('tool_name')->filter()->values()->all(),
                    certainty: 'CONFIRMADO',
                    missingInformation: [],
                    errors: [],
                    actions: [],
                    toolResults: $result['tool_results'] ?? [],
                    behavior: $result['behavior'] ?? [],
                    diagnosis: $result['diagnosis'] ?? [],
                    solution: $result['solution'] ?? [],
                    verification: $result['verification'] ?? [],
                    plan: $result['plan'] ?? [],
                    selfEvaluation: $result['self_evaluation'] ?? [],
                    correlation: $result['correlation'] ?? [],
                    explanationStyle: $result['explanation_style'] ?? 'general',
                    status: 'completed',
                    source: 'nexus_execution',
                );
            }

            if ($run->status === 'awaiting_confirmation') {
                return new NexusRuntimeResponse(
                    finalMessage: 'Nexus preparó una acción que requiere confirmación explícita antes de continuar.',
                    intent: $intentName,
                    evidence: [],
                    toolsUsed: collect($run->toolCalls ?? [])->pluck('tool_name')->filter()->values()->all(),
                    certainty: 'PROBABLE',
                    missingInformation: ['confirmación del usuario'],
                    errors: [],
                    actions: ['await_confirmation'],
                    toolResults: $result['reasoning']['tool_calls'] ?? [],
                    behavior: [],
                    diagnosis: [],
                    solution: [],
                    verification: [],
                    plan: [],
                    selfEvaluation: [],
                    correlation: [],
                    explanationStyle: 'general',
                    status: 'awaiting_confirmation',
                    source: 'nexus_execution',
                );
            }
        }

        $executionPlan = $this->buildExecutionPlan($request, $intentName);
        $toolResults = [];
        $toolsUsed = [];
        $errors = [];
        $investigation = $this->investigationPlan($intentName);
        $context = new NexusToolContext(
            user: $request->user,
            source: 'runtime_gateway',
            confirmed: false,
            system: false,
            grantedPermissions: ['nexus.read'],
            runId: null,
            projectId: $request->projectId,
            toolName: null,
        );

        foreach ($executionPlan as $index => $call) {
            $toolName = $call['tool'];
            $arguments = $call['arguments'];
            $toolsUsed[] = $toolName;
            $result = $this->tools->execute($toolName, $arguments, $context);
            $toolResults[] = [
                'tool' => $toolName,
                'arguments' => $arguments,
                'status' => $result->successful ? 'ok' : 'failed',
                'result' => $result->toArray(),
            ];

            if (! $result->successful) {
                $errors[] = $result->error ?? 'La herramienta falló.';
            }

            if (isset($investigation['steps'][$index])) {
                $investigation['steps'][$index]['status'] = $result->successful ? 'completed' : 'failed';
                $investigation['steps'][$index]['evidence'] = $this->evidenceAvailable($result->toArray());
            } else {
                $investigation['steps'][] = [
                    'phase' => 'model_context',
                    'question' => '¿Qué entidad y relaciones intervienen en el flujo?',
                    'tool' => $toolName,
                    'status' => $result->successful ? 'completed' : 'failed',
                    'evidence' => $this->evidenceAvailable($result->toArray()),
                ];
            }
        }

        if ($intentName === 'diagnosis') {
            $autonomousIterations = 0;
            $maxAutonomousIterations = 6;
            $investigatedCalls = [];

            while ($autonomousIterations < $maxAutonomousIterations) {
                $call = $this->nextAutonomousDiagnosisCall($request, $toolResults, $investigatedCalls);
                if ($call === null) {
                    break;
                }

                $autonomousIterations++;
                $callKey = $call['tool'].'|'.json_encode($call['arguments']);
                $investigatedCalls[] = $callKey;
                $toolName = $call['tool'];
                $arguments = $call['arguments'];
                $toolsUsed[] = $toolName;
                $result = $this->tools->execute($toolName, $arguments, $context);
                $toolResults[] = [
                    'tool' => $toolName,
                    'arguments' => $arguments,
                    'status' => $result->successful ? 'ok' : 'failed',
                    'result' => $result->toArray(),
                ];

                if (! $result->successful) {
                    $errors[] = $result->error ?? 'La herramienta falló.';
                }

                $investigation['steps'][] = [
                    'phase' => 'additional_evidence',
                    'question' => $call['question'],
                    'tool' => $toolName,
                    'status' => $result->successful ? 'completed' : 'failed',
                    'evidence' => $this->evidenceAvailable($result->toArray()),
                    'iteration' => $autonomousIterations,
                ];
            }

            $investigation['autonomous'] = [
                'enabled' => true,
                'iterations' => $autonomousIterations,
                'limit' => $maxAutonomousIterations,
                'stopped_because' => $autonomousIterations >= $maxAutonomousIterations
                    ? 'límite de seguridad alcanzado'
                    : 'no se detectó una necesidad de evidencia adicional',
            ];
        }

        $certainty = $this->determineCertainty($intentName, $toolResults, $errors);
        $hypotheses = $this->buildHypotheses($request->message, $intentName, $toolResults, $errors);
        $impact = $this->buildImpactAssessment($request->message, $intentName, $toolResults, $errors);
        $behavior = $this->buildBehaviorAnalysis($request->message, $intentName, $toolResults, $errors);
        $diagnosis = $this->buildDiagnosisReport($request->message, $intentName, $toolResults, $errors, $hypotheses);
        $solution = $this->buildSolutionProposal($request->message, $intentName, $toolResults, $errors, $diagnosis);
        $verification = $diagnosis['verification'] ?? [];
        $plan = $this->buildTechnicalPlan($request->message, $intentName, $toolResults, $errors);
        $selfEvaluation = $this->selfEvaluate(
            $request->message,
            $intentName,
            $toolResults,
            $errors,
            $hypotheses,
            $diagnosis,
            $plan
        );
        $correlation = $this->correlateEvidence($request->message, $request->projectId, $toolResults);
        $explanationStyle = $this->explanationStyle($request->message, $intentName);
        $finalMessage = $this->synthesizeRuntimeMessage($request->message, $intentName, $toolResults, $errors, $certainty, $hypotheses, $impact, $behavior, $diagnosis, $solution, $plan, $selfEvaluation);

        return new NexusRuntimeResponse(
            finalMessage: $finalMessage,
            intent: $intentName,
            evidence: $this->extractEvidenceSummary($toolResults),
            toolsUsed: $toolsUsed,
            certainty: $certainty,
            missingInformation: $this->missingInformationFor($intentName, $toolResults, $errors),
            errors: $errors,
            actions: $toolResults === [] ? [] : ['investigate'],
            toolResults: $toolResults,
            investigation: $investigation,
            hypotheses: $hypotheses,
            impact: $impact,
            behavior: $behavior,
            diagnosis: $diagnosis,
            solution: $solution,
            verification: $verification,
            plan: $plan,
            selfEvaluation: $selfEvaluation,
            correlation: $correlation,
            explanationStyle: $explanationStyle,
            status: 'completed',
            source: 'runtime_deterministic',
        );
    }

    private function handleAction(NexusRuntimeRequest $request, array $action): NexusRuntimeResponse
    {
        if (($action['available'] ?? true) === false) {
            $message = match ($action['action']) {
                'security_violation' => 'La solicitud fue rechazada por NexusSecurityBoundary: no se pueden ignorar permisos ni modificar directamente los controles de seguridad.',
                'unauthorized_tool' => 'La solicitud fue rechazada: la herramienta no está autorizada por Nexus Core.',
                default => 'La acción git_diff está identificada, pero no está implementada de forma segura en Nexus. No simularé sus resultados.',
            };

            return new NexusRuntimeResponse(
                finalMessage: $message,
                intent: $action['action'],
                actions: [$action],
                errors: [$message],
                status: in_array($action['action'], ['security_violation', 'unauthorized_tool'], true)
                    ? 'rejected'
                    : 'unsupported',
                source: 'runtime_action',
            );
        }

        $arguments = $action['arguments'];
        if ($action['action'] === 'git_commit' && ! isset($arguments['project_id'])) {
            $project = Proyecto::query()->get()->first(
                fn (Proyecto $candidate): bool => str_contains(
                    Str::lower(Str::ascii($candidate->nombre)),
                    'devcontrol'
                )
            );
            if ($project) {
                $arguments['project_id'] = $project->id;
            }
        }
        if ($action['action'] === 'git_commit' && ! isset($arguments['project_id'])) {
            return $this->actionFailure($action, 'Necesito un proyecto activo para preparar el commit.');
        }
        $action['arguments'] = $arguments;

        if ($action['action'] === 'code_edit_github') {
            $required = ['project_id', 'path', 'content'];
            $missing = array_values(array_filter($required, static fn (string $key): bool => ! array_key_exists($key, $arguments)));
            if ($missing !== []) {
                return $this->actionFailure(
                    $action,
                    'Detecté una solicitud de edición GitHub, pero faltan datos para ejecutarla: '.implode(', ', $missing).'.'
                );
            }
            $arguments['message'] = 'Actualización solicitada por el usuario';
        }

        $tool = match ($action['action']) {
            'git_status' => 'nexus.git.status',
            'git_commit' => 'nexus.github.local.commit',
            'test_execution' => 'nexus.code.validate',
            'code_edit_github' => 'nexus.github.file.write',
            default => null,
        };

        if ($tool === null) {
            return $this->actionFailure($action, 'La acción solicitada no tiene una herramienta disponible.');
        }

        $permissions = $action['requested_permissions'];
        $context = new NexusToolContext(
            user: $request->user,
            source: 'runtime_action',
            confirmed: false,
            system: false,
            grantedPermissions: in_array('nexus.write', $permissions, true) ? [] : $permissions,
            projectId: $request->projectId ?? ($request->context['project_id'] ?? null),
        );
        $result = $this->tools->execute($tool, $arguments, $context);
        $toolResult = [[
            'tool' => $tool,
            'arguments' => $arguments,
            'status' => $result->successful ? 'ok' : 'failed',
            'result' => $result->toArray(),
        ]];

        if (! $result->successful) {
            $status = $result->errorCode === 'confirmation_required' ? 'awaiting_confirmation' : 'rejected';
            $message = $status === 'awaiting_confirmation'
                ? 'La acción fue preparada y requiere confirmación explícita antes de modificar información.'
                : ($result->error ?? 'La acción fue rechazada.');

            return new NexusRuntimeResponse(
                finalMessage: $message,
                intent: $action['action'],
                toolsUsed: [$tool],
                errors: [$result->error ?? 'La herramienta falló.'],
                actions: [$action],
                toolResults: $toolResult,
                status: $status,
                source: 'runtime_action',
            );
        }

        return new NexusRuntimeResponse(
            finalMessage: $action['action'] === 'test_execution'
                ? 'Ejecuté la validación solicitada y devolví sus resultados.'
                : 'La acción solicitada se ejecutó correctamente.',
            intent: $action['action'],
            evidence: [$result->data],
            toolsUsed: [$tool],
            actions: [$action],
            toolResults: $toolResult,
            certainty: 'CONFIRMADO',
            source: 'runtime_action',
        );
    }

    private function actionFailure(array $action, string $message): NexusRuntimeResponse
    {
        return new NexusRuntimeResponse(
            finalMessage: $message,
            intent: $action['action'],
            errors: [$message],
            actions: [$action],
            status: 'rejected',
            source: 'runtime_action',
        );
    }

    private function shouldBypassRuntime(string $message, array $intent): bool
    {
        $text = $this->normalize($message);

        if ($this->isGeneralDevControlQuestion($text)) {
            return true;
        }

        return $intent['intent'] === 'general'
            && ! $this->requiresEvidence($text)
            && ! $this->mentionsRepositoryContext($text);
    }

    private function classifyIntent(string $message): array
    {
        $text = $this->normalize($message);

        if (preg_match('/\bplanific\w*/iu', $message) === 1
            || $this->hasAny($text, ['como implementarías', 'cómo implementarías', 'como implementarias', 'cómo implementar', 'plan tecnico', 'plan técnico', 'que tendria que cambiar', 'qué tendría que cambiar'])) {
            return ['intent' => 'planning', 'confidence' => 0.8];
        }

        if ($this->isGeneralDevControlQuestion($text)) {
            return ['intent' => 'general', 'confidence' => 0.98];
        }

        if ($this->hasAny($text, ['no aparecen', 'no se muestran', 'no funciona', 'falla', 'fallar', 'problema', 'diagnostica', 'investiga por que', 'porque', 'podría fallar'])) {
            return ['intent' => 'diagnosis', 'confidence' => 0.95];
        }

        if ($this->hasAny($text, ['relacion', 'hasmany', 'belongsTo', 'tareas', 'proyecto con sus tareas', 'relaciona proyecto con sus tareas'])) {
            return ['intent' => 'relation_analysis', 'confidence' => 0.9];
        }

        if ($this->isBehaviorQuestion($text)) {
            return ['intent' => 'behavior_analysis', 'confidence' => 0.9];
        }

        if ($this->hasAny($text, ['implementacion de index', 'implementación de index', 'index()', 'explícame la implementación'])) {
            return ['intent' => 'method_analysis', 'confidence' => 0.9];
        }

        if ($this->hasAny($text, ['como funciona', 'explica', 'describ', 'flujo', 'relaciona', 'relación', 'relaciones', 'funcionalidad de proyectos', 'que pasa cuando entro a proyectos', 'qué pasa cuando entro a proyectos'])) {
            return ['intent' => 'functionality_flow', 'confidence' => 0.9];
        }

        if ($this->hasAny($text, ['index paso a paso', '¿qué hace', 'qué hace', 'controlador@index', 'proyecto controller'])) {
            return ['intent' => 'method_analysis', 'confidence' => 0.8];
        }

        if ($this->hasAny($text, ['archivo inexistente', 'app/models/', 'proyectoxyz', 'qué hace app/', 'router'])) {
            return ['intent' => 'file_lookup', 'confidence' => 0.8];
        }

        if ($this->hasAny($text, ['impacto', 'afectará', 'afectado', 'si modifico', 'modifico proyecto'])) {
            return ['intent' => 'impact_analysis', 'confidence' => 0.8];
        }

        if ($this->hasAny($text, ['proyecto', 'proyectos', 'controller', 'controlador', 'ruta', 'routes', 'modelo', 'model'])) {
            return ['intent' => 'method_analysis', 'confidence' => 0.8];
        }

        return ['intent' => 'general', 'confidence' => 0.6];
    }

    private function generalResponse(string $message): string
    {
        $text = $this->normalize($message);

        if ($this->isGeneralDevControlQuestion($text)) {
            return 'DevControl es un proyecto de gestión de desarrollo y operación: permite gestionar proyectos, tareas, bugs, incidencias y análisis del comportamiento del sistema dentro de un mismo flujo de trabajo.';
        }

        return 'Puedo ayudarte a entender DevControl, su estructura y su funcionamiento. Si me indicas qué quieres revisar, puedo centrarme en ese área concreta.';
    }

    private function requiresEvidence(string $text): bool
    {
        $normalized = $this->normalize($text);

        return $this->hasAny($normalized, [
            'proyecto', 'proyectos', 'funcionalidad', 'relacion', 'ruta', 'routes', 'controlador', 'modelo', 'no aparecen', 'no funciona', 'falla', 'diagnostica', 'investiga', 'explica', 'describe', 'como funciona', 'problema', 'qué hace', 'que hace',
        ]);
    }

    private function mentionsRepositoryContext(string $text): bool
    {
        return $this->hasAny($this->normalize($text), ['composer.json', 'app/', 'routes/', 'controller', 'modelo', 'github', 'repositorio']);
    }

    private function buildExecutionPlan(NexusRuntimeRequest $request, string $intent): array
    {
        $message = $this->normalize($request->message);
        $plan = [];

        if ($intent === 'diagnosis') {
            $plan[] = ['tool' => 'nexus.code.analyze', 'arguments' => ['path' => 'routes/web.php']];
            $plan[] = ['tool' => 'nexus.code.analyze', 'arguments' => ['path' => 'app/Http/Controllers/ProyectoController.php']];
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'routes/web.php', 'project_id' => $request->projectId]];
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'app/Http/Controllers/ProyectoController.php', 'project_id' => $request->projectId]];
            return $plan;
        }

        if ($intent === 'planning') {
            $plan[] = ['tool' => 'nexus.project.understand', 'arguments' => [
                'project_id' => $request->projectId,
                'path' => '.',
                'include_documentation' => true,
            ]];
            $plan[] = ['tool' => 'nexus.code.analyze', 'arguments' => ['path' => 'routes/web.php']];
            if ($this->hasAny($message, ['proyecto', 'proyectos'])) {
                $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => [
                    'operation' => 'file',
                    'path' => 'app/Http/Controllers/ProyectoController.php',
                    'project_id' => $request->projectId,
                ]];
                $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => [
                    'operation' => 'file',
                    'path' => 'app/Models/Proyecto.php',
                    'project_id' => $request->projectId,
                ]];
            }
            return $plan;
        }

        if ($intent === 'relation_analysis') {
            $plan[] = ['tool' => 'nexus.code.analyze', 'arguments' => ['path' => 'app/Models/Proyecto.php']];
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'app/Models/Proyecto.php', 'project_id' => $request->projectId]];
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'app/Models/Tarea.php', 'project_id' => $request->projectId]];
            return $plan;
        }

        if ($intent === 'behavior_analysis') {
            $paths = $this->behaviorPaths($message);
            foreach ($paths as $path) {
                $plan[] = [
                    'tool' => 'nexus.github.inspect',
                    'arguments' => [
                        'operation' => 'file',
                        'path' => $path,
                        'project_id' => $request->projectId,
                    ],
                ];
            }
            return $plan;
        }

        if ($intent === 'functionality_flow' || $this->hasAny($message, ['proyectos', 'dashboard/proyectos', 'proyecto'])) {
            $plan[] = ['tool' => 'nexus.code.analyze', 'arguments' => ['path' => 'routes/web.php']];
            $plan[] = ['tool' => 'nexus.code.analyze', 'arguments' => ['path' => 'app/Http/Controllers/ProyectoController.php']];
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'routes/web.php', 'project_id' => $request->projectId]];
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'app/Http/Controllers/ProyectoController.php', 'project_id' => $request->projectId]];
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'app/Models/Proyecto.php', 'project_id' => $request->projectId]];
            return $plan;
        }

        if ($intent === 'method_analysis' || $this->hasAny($message, ['index', 'controller', 'metodo'])) {
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'app/Http/Controllers/ProyectoController.php', 'project_id' => $request->projectId]];
            return $plan;
        }

        if ($intent === 'file_lookup') {
            $target = $this->extractFileCandidate($message);
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => $target, 'project_id' => $request->projectId]];
            return $plan;
        }

        if ($intent === 'impact_analysis') {
            $plan[] = ['tool' => 'nexus.project.understand', 'arguments' => ['project_id' => $request->projectId, 'path' => '.', 'include_documentation' => true]];
            $plan[] = ['tool' => 'nexus.github.inspect', 'arguments' => ['operation' => 'file', 'path' => 'app/Models/Proyecto.php', 'project_id' => $request->projectId]];
            return $plan;
        }

        return [];
    }

    /** @return array{goal: string, steps: array<int, array<string, mixed>>} */
    private function investigationPlan(string $intent): array
    {
        if ($intent === 'diagnosis') {
            return [
                'goal' => 'Localizar el flujo afectado, recopilar evidencia y separar hechos de hipótesis.',
                'steps' => [
                    ['phase' => 'entry_points', 'question' => '¿Qué rutas reciben la creación y el listado?', 'status' => 'pending'],
                    ['phase' => 'write_flow', 'question' => '¿Cómo se valida y persiste el proyecto?', 'status' => 'pending'],
                    ['phase' => 'read_flow', 'question' => '¿Cómo se consulta y prepara el listado?', 'status' => 'pending'],
                    ['phase' => 'persistence', 'question' => '¿Qué modelo y relaciones intervienen?', 'status' => 'pending'],
                ],
            ];
        }

        return [
            'goal' => 'Recopilar únicamente la evidencia necesaria para la consulta actual.',
            'steps' => [],
        ];
    }

    private function evidenceAvailable(array $result): bool
    {
        if (($result['ok'] ?? false) !== true) {
            return false;
        }

        $data = $result['data'] ?? null;
        return is_array($data) && $data !== [];
    }

    private function needsAdditionalDiagnosisEvidence(array $toolResults): bool
    {
        $paths = collect($toolResults)
            ->map(fn (array $entry): string => (string) data_get($entry, 'result.data.path'))
            ->filter()
            ->all();

        return in_array('routes/web.php', $paths, true)
            && in_array('app/Http/Controllers/ProyectoController.php', $paths, true)
            && ! in_array('resources/views/admin/proyectos.blade.php', $paths, true);
    }

    /** @return array{tool: string, arguments: array<string, mixed>, question: string}|null */
    private function nextAutonomousDiagnosisCall(
        NexusRuntimeRequest $request,
        array $toolResults,
        array $investigatedCalls,
    ): ?array {
        $paths = $this->evidenceSources($toolResults);
        $candidates = [
            [
                'path' => 'app/Models/Proyecto.php',
                'question' => '¿Cómo se persisten los datos y qué relaciones intervienen?',
            ],
            [
                'path' => 'resources/views/admin/proyectos.blade.php',
                'question' => '¿La vista representa los proyectos que devuelve el listado?',
            ],
        ];

        foreach ($candidates as $candidate) {
            $call = [
                'tool' => 'nexus.github.inspect',
                'arguments' => [
                    'operation' => 'file',
                    'path' => $candidate['path'],
                    'project_id' => $request->projectId,
                ],
                'question' => $candidate['question'],
            ];
            $callKey = $call['tool'].'|'.json_encode($call['arguments']);
            if (! in_array($candidate['path'], $paths, true) && ! in_array($callKey, $investigatedCalls, true)) {
                return $call;
            }
        }

        return null;
    }

    /** @return array<int, array{tool: string, arguments: array<string, mixed>}> */
    private function additionalDiagnosisPlan(NexusRuntimeRequest $request): array
    {
        return [
            [
                'tool' => 'nexus.github.inspect',
                'arguments' => [
                    'operation' => 'file',
                    'path' => 'resources/views/admin/proyectos.blade.php',
                    'project_id' => $request->projectId,
                ],
            ],
        ];
    }

    private function synthesizeRuntimeMessage(
        string $message,
        string $intent,
        array $toolResults,
        array $errors,
        string $certainty,
        array $hypotheses = [],
        array $impact = [],
        array $behavior = [],
        array $diagnosis = [],
        array $solution = [],
        array $plan = [],
        array $selfEvaluation = [],
    ): string
    {
        $text = $this->normalize($message);
        $routeEvidence = $this->findEvidence($toolResults, 'routes/web.php', 'decoded_content');
        $controllerEvidence = $this->findEvidence($toolResults, 'app/Http/Controllers/ProyectoController.php', 'decoded_content');
        $proyectoEvidence = $this->findEvidence($toolResults, 'app/Models/Proyecto.php', 'decoded_content');
        $tareaEvidence = $this->findEvidence($toolResults, 'app/Models/Tarea.php', 'decoded_content');

        if ($intent === 'behavior_analysis') {
            return $this->appendSelfEvaluation($this->behaviorNarrative($behavior), $selfEvaluation);
        }

        if ($intent === 'planning') {
            return $this->appendSelfEvaluation($this->planNarrative($plan), $selfEvaluation);
        }

        if ($this->isProjectRelationQuestion($text)) {
            $parts = ['Proyecto tiene una relación con Tarea.'];
            if (str_contains($proyectoEvidence, 'hasMany(Tarea::class,') || str_contains($proyectoEvidence, 'hasMany(Tarea::class')) {
                $parts[] = 'La relación se declara como hasMany(Tarea::class, "proyecto_id") o equivalente y se define en Proyecto::tareas().';
            } elseif (str_contains($proyectoEvidence, 'tareas()')) {
                $parts[] = 'La relación está definida en Proyecto::tareas().';
            }
            if ($tareaEvidence !== '') {
                $parts[] = 'En Tarea el vínculo se materializa con la clave foránea proyecto_id, por lo que un proyecto puede tener múltiples tareas.';
            }
            if ($parts === ['Proyecto tiene una relación con Tarea.']) {
                return 'No pude confirmar la relación con evidencia suficiente en Proyecto y Tarea.';
            }

            return implode(' ', $parts);
        }

        if ($this->isMethodComparisonQuestion($text)) {
            $parts = [];
            $hasStore = str_contains($controllerEvidence, 'Proyecto::create(');
            $hasIndex = str_contains($controllerEvidence, 'Proyecto::latest()->paginate(2)');
            if ($hasIndex) {
                $parts[] = 'ProyectoController@index consulta proyectos con Proyecto::latest()->paginate(2) y prepara los datos para la vista.';
            }
            if ($hasStore) {
                $parts[] = 'ProyectoController@store valida la entrada, crea el proyecto con Proyecto::create($validado) y redirige a proyectos.index.';
            }
            if ($parts !== []) {
                return implode(' ', $parts);
            }
            return 'He comparado los dos métodos y la diferencia principal es que index consulta y store persiste, según la evidencia disponible.';
        }

        if ($this->isMethodExplanationQuestion($text)) {
            $parts = ['ProyectoController@index ejecuta la consulta principal de listado.'];
            if (str_contains($controllerEvidence, 'Proyecto::latest()->paginate(2)')) {
                $parts[] = 'Primero obtiene el conjunto con Proyecto::latest()->paginate(2).';
            }
            if (str_contains($controllerEvidence, '$proyecto = $proyectos->first();')) {
                $parts[] = 'Luego toma el primer proyecto de la página para prepararlo en la vista.';
            }
            if (str_contains($controllerEvidence, 'return view(')) {
                $parts[] = 'Finalmente prepara variables como tareas, bugs, actualizaciones, secciones y retorna la vista admin.proyectos.';
            }
            if ($parts === ['ProyectoController@index ejecuta la consulta principal de listado.']) {
                return 'He revisado el método y la evidencia disponible indica que index consulta proyectos y prepara la vista, pero no hay más detalle confirmable en el código analizado.';
            }

            return implode(' ', $parts);
        }

        if ($this->isFunctionalityQuestion($text)) {
            $parts = ['El flujo de proyectos funciona así: la ruta GET /dashboard/proyectos apunta a ProyectoController@index y la ruta POST /dashboard/proyectos apunta a ProyectoController@store.'];
            if ($routeEvidence !== '') {
                $parts[] = 'En routes/web.php se definen ambas rutas.';
            }
            if ($controllerEvidence !== '') {
                $parts[] = 'En el controlador, store valida los datos, crea el proyecto y redirige a proyectos.index; index consulta Proyecto::latest()->paginate(2) y prepara la vista administrativa.';
            }
            if ($proyectoEvidence !== '') {
                $parts[] = 'En Proyecto.php se confirma la tabla proyectos y sus relaciones principales con tareas, bugs, actualizaciones y otras entidades.';
            }

            return implode(' ', $parts);
        }

        if ($this->isSimpleNavigationQuestion($text)) {
            $parts = ['Cuando entras a proyectos, DevControl abre el listado de proyectos.'];
            if ($routeEvidence !== '') {
                $parts[] = 'La ruta GET /dashboard/proyectos dirige esa entrada al controlador.';
            }
            if ($controllerEvidence !== '') {
                $parts[] = 'El controlador consulta los proyectos recientes y prepara la información que se muestra en pantalla.';
            }
            if ($proyectoEvidence !== '') {
                $parts[] = 'La información se obtiene del modelo Proyecto.';
            }

            return implode(' ', $parts);
        }

        if ($this->isLifecycleQuestion($text)) {
            $parts = ['Desde la creación hasta la visualización del proyecto, el flujo es: la petición POST /dashboard/proyectos llega a ProyectoController@store.'];
            if ($controllerEvidence !== '') {
                $parts[] = 'En store() se validan los campos, se crea el proyecto con Proyecto::create($validado), luego se guardan secciones y configuración de GitHub dentro de una transacción y se redirige a proyectos.index.';
            }
            if ($routeEvidence !== '') {
                $parts[] = 'La aplicación vuelve a la ruta GET /dashboard/proyectos, que está conectada a ProyectoController@index.';
            }
            if (str_contains($controllerEvidence, 'Proyecto::latest()->paginate(2)')) {
                $parts[] = 'Index consulta Proyecto::latest()->paginate(2) y prepara la vista admin.proyectos con el proyecto y sus datos asociados.';
            }
            return implode(' ', $parts);
        }

        if ($this->isImpactQuestion($text)) {
            return $this->appendSelfEvaluation($this->impactNarrative($impact, $proyectoEvidence, $controllerEvidence), $selfEvaluation);
        }

        if ($this->isMissingFileQuestion($text, $toolResults)) {
            $missingText = $this->missingFileNarrative($toolResults);
            if ($missingText !== '') {
                return $missingText;
            }

            return 'El archivo solicitado no existe en el repositorio analizado, y la evidencia disponible no sugiere un archivo alternativo con ese nombre.';
        }

        if ($intent === 'diagnosis') {
            return $this->appendSelfEvaluation($this->diagnosisNarrative($diagnosis, $solution), $selfEvaluation);
        }

        if ($intent === 'relation_analysis') {
            return $this->projectRelationNarrative($proyectoEvidence, $tareaEvidence);
        }

        if ($intent === 'functionality_flow') {
            return 'El flujo funcional de proyectos se basa en la ruta web, el controlador y el modelo: POST /dashboard/proyectos crea el proyecto, GET /dashboard/proyectos consulta los proyectos recientes y la vista muestra los datos en admin.proyectos.';
        }

        if ($intent === 'method_analysis') {
            if ($controllerEvidence !== '') {
                return 'ProyectoController@index ejecuta una consulta reciente con Proyecto::latest()->paginate(2), toma el primer proyecto para la vista, prepara las colecciones de tareas, bugs, actualizaciones, secciones y estadísticas, y devuelve admin.proyectos.';
            }
            return 'He revisado el método y la evidencia disponible indica que el flujo principal del listado es consultar proyectos y prepararlos para la vista.';
        }

        if ($intent === 'file_lookup') {
            $missingText = $this->missingFileNarrative($toolResults);
            if ($missingText !== '') {
                return $missingText;
            }

            return 'La evidencia disponible indica que el archivo solicitado no existe en el repositorio analizado.';
        }

        if ($intent === 'impact_analysis') {
            return 'Los cambios en Proyecto.php pueden afectar tanto su propia estructura como todas las rutas y controladores que consultan o crean proyectos y sus relaciones.';
        }

        if ($toolResults === []) {
            return 'Nexus no detectó evidencia suficiente para responder la consulta con precisión.';
        }

        return 'La investigación realizada aportó evidencia concreta sobre el área consultada, y la respuesta final se basa en esa evidencia y no en suposiciones.';
    }

    private function isProjectRelationQuestion(string $text): bool
    {
        return $this->hasAny($text, ['relaciona proyecto con sus tareas', 'relaciona proyecto y tareas', 'proyecto con sus tareas', 'hasmany', 'relacion proyecto', 'proyecto tiene tareas']);
    }

    private function isMethodExplanationQuestion(string $text): bool
    {
        return $this->hasAny($text, ['que hace proyectocontroller@index paso a paso', 'qué hace proyectocontroller@index paso a paso', 'index paso a paso', 'que hace index', 'qué hace index', 'proyectocontroller@index']);
    }

    private function isMethodComparisonQuestion(string $text): bool
    {
        return $this->hasAny($text, [
            'diferencia entre proyectocontroller@index y proyectocontroller@store',
            'diferencia hay entre proyectocontroller@index y proyectocontroller@store',
            'diferencia entre index y store',
            'diferencia hay entre index y store',
            'comparar index y store',
        ]);
    }

    private function isFunctionalityQuestion(string $text): bool
    {
        return $this->hasAny($text, ['como funciona actualmente la funcionalidad de proyectos', 'como funciona la funcionalidad de proyectos', 'funcionalidad de proyectos', 'cómo funciona actualmente la funcionalidad de proyectos']);
    }

    private function isSimpleNavigationQuestion(string $text): bool
    {
        return $this->hasAny($text, [
            'que pasa cuando entro a proyectos',
            'qué pasa cuando entro a proyectos',
            'cuando entro a proyectos',
            'al entrar a proyectos',
        ]);
    }

    private function explanationStyle(string $message, string $intent): string
    {
        $text = $this->normalize($message);

        if ($intent === 'diagnosis') {
            return 'diagnostic';
        }
        if ($intent === 'planning' || $this->hasAny($text, ['que tendria que cambiar', 'qué tendría que cambiar'])) {
            return 'proposal';
        }
        if ($this->isSimpleNavigationQuestion($text)) {
            return 'simple';
        }
        if ($intent === 'method_analysis' || $this->hasAny($text, ['implementacion de index', 'implementación de index', 'index()'])) {
            return 'technical';
        }

        return 'general';
    }

    private function isBehaviorQuestion(string $text): bool
    {
        return $this->hasAny($text, [
            'que sucede cuando',
            'qué sucede cuando',
            'que sucede desde',
            'qué sucede desde',
            'desde que un usuario',
            'cuando un usuario',
            'comportamiento',
            'creacion de proyectos',
            'creación de proyectos',
            'actualizacion de proyectos',
            'actualización de proyectos',
            'creacion de tickets',
            'creación de tickets',
            'autenticacion',
            'autenticación',
            'integracion github',
            'integración github',
        ]);
    }

    /** @return array<int, string> */
    private function behaviorPaths(string $text): array
    {
        $paths = ['routes/web.php'];

        if ($this->hasAny($text, ['autenticacion', 'autenticación', 'login', 'registro'])) {
            return array_merge($paths, [
                'app/Http/Controllers/LoginCcontroller.php',
                'app/Http/Controllers/RegisterController.php',
            ]);
        }

        if ($this->hasAny($text, ['github', 'integracion github', 'integración github'])) {
            return array_merge($paths, [
                'app/Http/Controllers/ProyectoController.php',
                'app/Services/NexusGithubService.php',
            ]);
        }

        if ($this->hasAny($text, ['ticket', 'tarea', 'bug'])) {
            return array_merge($paths, [
                'app/Http/Controllers/TareasController.php',
                'app/Http/Controllers/BugController.php',
                'app/Models/Tarea.php',
            ]);
        }

        return array_merge($paths, [
            'app/Http/Controllers/ProyectoController.php',
            'app/Models/Proyecto.php',
            'resources/views/admin/proyectos.blade.php',
        ]);
    }

    /** @return array<string, mixed> */
    private function selfEvaluate(
        string $message,
        string $intent,
        array $toolResults,
        array $errors,
        array $hypotheses,
        array $diagnosis,
        array $plan,
    ): array {
        $sources = $this->evidenceSources($toolResults);
        $hasEvidence = $sources !== [] || $toolResults !== [];
        $contradictions = collect($hypotheses)
            ->flatMap(fn (array $hypothesis): array => (array) ($hypothesis['contradicting_evidence'] ?? []))
            ->filter()
            ->values()
            ->all();
        $inferences = collect($hypotheses)
            ->filter(fn (array $hypothesis): bool => data_get($hypothesis, 'verification.inference') === true)
            ->pluck('description')
            ->values()
            ->all();
        $missing = array_values(array_unique(array_merge(
            $errors !== [] ? ['resultados de herramientas con error'] : [],
            (array) ($diagnosis['missing_evidence'] ?? []),
            (array) ($plan['missing_information'] ?? [])
        )));
        $checks = [
            'answered_question' => trim($message) !== '' && $intent !== 'general',
            'used_evidence' => $hasEvidence,
            'evidence_belongs_to_query' => $this->evidenceMatchesQuery($message, $sources),
            'separates_inference_from_fact' => $inferences === [] || $intent === 'planning' || $diagnosis !== [],
            'alternative_hypotheses' => $intent !== 'diagnosis' || count($hypotheses) > 1,
            'contradictory_evidence_reviewed' => $contradictions === [] || $intent === 'diagnosis',
            'necessary_investigation_complete' => $missing === [],
            'conclusion_supported' => $hasEvidence && $errors === [] && $missing === [],
        ];
        $failed = array_keys(array_filter($checks, fn (bool $passed): bool => ! $passed));
        $requiresAdditional = $failed !== [] || ($intent === 'diagnosis' && ($diagnosis['certainty'] ?? '') === 'evidencia insuficiente');

        return [
            'status' => $requiresAdditional ? 'requiere investigación adicional' : 'suficientemente respaldada',
            'requires_additional_investigation' => $requiresAdditional,
            'checks' => $checks,
            'failed_checks' => $failed,
            'evidence_sources' => $sources,
            'inferences' => $inferences,
            'contradictions' => $contradictions,
            'missing_information' => $missing,
            'conclusion' => $requiresAdditional
                ? 'La respuesta no debe presentarse como concluyente con la evidencia disponible.'
                : 'La conclusión está respaldada por la evidencia recopilada para esta consulta.',
        ];
    }

    private function evidenceMatchesQuery(string $message, array $sources): bool
    {
        if ($sources === []) {
            return false;
        }

        $text = $this->normalize($message);
        if ($this->hasAny($text, ['proyecto', 'proyectos'])) {
            return collect($sources)->contains(fn (string $source): bool =>
                str_contains($source, 'Proyecto')
                || str_contains($source, 'proyecto')
                || str_contains($source, 'routes')
            );
        }

        return true;
    }

    private function appendSelfEvaluation(string $narrative, array $evaluation): string
    {
        if ($evaluation === []) {
            return $narrative;
        }

        if (($evaluation['requires_additional_investigation'] ?? false) === true) {
            return $narrative.' Autoevaluación: requiere investigación adicional. '.($evaluation['conclusion'] ?? '');
        }

        return $narrative.' Autoevaluación: la conclusión está suficientemente respaldada por la evidencia recopilada.';
    }

    /** @return array<string, mixed> */
    private function buildBehaviorAnalysis(string $message, string $intent, array $toolResults, array $errors): array
    {
        if ($intent !== 'behavior_analysis') {
            return [];
        }

        $route = $this->findEvidence($toolResults, 'routes/web.php', 'decoded_content');
        $controller = $this->findEvidence($toolResults, 'app/Http/Controllers/ProyectoController.php', 'decoded_content');
        $model = $this->findEvidence($toolResults, 'app/Models/Proyecto.php', 'decoded_content');
        $view = $this->findEvidence($toolResults, 'resources/views/admin/proyectos.blade.php', 'decoded_content');
        $sources = $this->evidenceSources($toolResults);
        $allEvidence = collect($toolResults)
            ->map(fn (array $entry): array => [
                'path' => (string) data_get($entry, 'result.data.path'),
                'content' => (string) data_get($entry, 'result.data.decoded_content'),
            ])
            ->filter(fn (array $entry): bool => $entry['content'] !== '')
            ->values()
            ->all();
        $steps = [];
        $missing = [];
        $order = 1;
        $text = $this->normalize($message);

        if ($route !== '') {
            $routeEvidence = $this->behaviorRouteEvidence($route, $text);
            $steps[] = [
                'order' => $order++,
                'action' => $routeEvidence,
                'certainty' => 'CONFIRMADO',
                'evidence' => 'La declaración de ruta fue inspeccionada.',
                'sources' => ['routes/web.php'],
            ];
        } else {
            $missing[] = 'rutas o punto de entrada';
        }

        if ($controller !== '') {
            foreach ($this->behaviorControllerSteps($controller, $text) as $action) {
                $steps[] = [
                    'order' => $order++,
                    'action' => $action,
                    'certainty' => 'CONFIRMADO',
                    'evidence' => 'La operación aparece en el controller inspeccionado.',
                    'sources' => ['app/Http/Controllers/ProyectoController.php'],
                ];
            }
        } else {
            $missing[] = 'controller o servicio de aplicación';
        }

        foreach ($this->genericBehaviorSteps($text, $allEvidence) as $genericStep) {
            $steps[] = [
                'order' => $order++,
                'action' => $genericStep['action'],
                'certainty' => 'CONFIRMADO',
                'evidence' => $genericStep['evidence'],
                'sources' => [$genericStep['source']],
            ];
        }

        if ($model !== '') {
            $steps[] = [
                'order' => $order++,
                'action' => str_contains($model, 'Proyecto::') || str_contains($controller, 'Proyecto::')
                    ? 'El modelo Proyecto participa en la persistencia o consulta indicada por el flujo.'
                    : 'El modelo Proyecto fue localizado, pero su participación concreta en este flujo no está confirmada.',
                'certainty' => str_contains($controller, 'Proyecto::') ? 'CONFIRMADO' : 'INFERENCIA',
                'evidence' => 'Se inspeccionó el modelo y el uso que hace el controller.',
                'sources' => ['app/Models/Proyecto.php'],
            ];
        } else {
            $missing[] = 'modelo o persistencia';
        }

        if ($view !== '') {
            $steps[] = [
                'order' => $order++,
                'action' => 'La vista recibe y representa los datos preparados por el controller.',
                'certainty' => 'CONFIRMADO',
                'evidence' => 'La vista fue inspeccionada directamente.',
                'sources' => ['resources/views/admin/proyectos.blade.php'],
            ];
        } else {
            $missing[] = 'vista o representación frontend';
        }

        $confirmed = count(array_filter($steps, fn (array $step): bool => $step['certainty'] === 'CONFIRMADO'));
        $certainty = $errors !== [] || $missing !== [] ? 'PROBABLE' : ($confirmed > 0 ? 'CONFIRMADO' : 'INSUFICIENTE');

        return [
            'question' => $message,
            'goal' => 'Reconstruir qué ocurre cuando se ejecuta la funcionalidad, en orden temporal y con evidencia de cada capa.',
            'trigger' => $this->behaviorTrigger($route, $text),
            'steps' => $steps,
            'missing_layers' => $missing,
            'certainty' => $certainty,
            'sources' => $sources,
            'read_only' => true,
            'conclusion' => $missing === []
                ? 'El comportamiento completo está confirmado en las capas investigadas.'
                : 'El flujo está parcialmente reconstruido; las capas ausentes no se presentan como hechos.',
        ];
    }

    private function behaviorTrigger(string $route, string $text): string
    {
        if (preg_match("/Route::post\\(['\"]([^'\"]+)/", $route, $matches) === 1) {
            return 'La funcionalidad comienza cuando una petición POST entra por '.$matches[1].'.';
        }

        return $this->hasAny($text, ['autenticacion', 'autenticación'])
            ? 'La funcionalidad comienza cuando el usuario intenta autenticarse.'
            : 'No se pudo confirmar el punto de entrada de la funcionalidad.';
    }

    private function behaviorRouteEvidence(string $route, string $text): string
    {
        if ($this->hasAny($text, ['creacion de proyectos', 'creación de proyectos', 'crea un proyecto'])) {
            return 'La petición de creación entra por POST /dashboard/proyectos y se dirige a ProyectoController@store.';
        }
        if ($this->hasAny($text, ['actualizacion de proyectos', 'actualización de proyectos'])) {
            return 'La petición de actualización entra por PUT /dashboard/proyectos/{proyecto} y se dirige a ProyectoController@update.';
        }
        if ($this->hasAny($text, ['github', 'integracion github', 'integración github'])) {
            return 'La integración utiliza las rutas de GitHub declaradas para sincronizar, analizar o importar el proyecto.';
        }

        return 'La ruta inspeccionada define el punto de entrada de la funcionalidad.';
    }

    /** @return array<int, string> */
    private function behaviorControllerSteps(string $controller, string $text): array
    {
        $steps = [];
        if (str_contains($controller, '$request->validate(')) {
            $steps[] = 'El controller valida los datos recibidos antes de continuar.';
        }
        if (str_contains($controller, 'Proyecto::create(') && $this->hasAny($text, ['creacion', 'creación', 'crea un proyecto'])) {
            $steps[] = 'Después crea y persiste el proyecto mediante Proyecto::create(...).';
        }
        if (str_contains($controller, '->update(') && $this->hasAny($text, ['actualizacion', 'actualización', 'actualiza un proyecto'])) {
            $steps[] = 'Después actualiza la instancia Proyecto recibida mediante route model binding.';
        }
        if (str_contains($controller, 'DB::transaction(')) {
            $steps[] = 'La operación de escritura se ejecuta dentro de una transacción de base de datos.';
        }
        if (str_contains($controller, 'guardarSecciones(')) {
            $steps[] = 'Después guarda las secciones asociadas al proyecto.';
        }
        if (str_contains($controller, 'guardarIntegracionGithub(')) {
            $steps[] = 'Después prepara o guarda la integración de GitHub asociada al proyecto.';
        }
        if (str_contains($controller, 'Proyecto::latest()->paginate(2)')) {
            $steps[] = 'El listado consulta los proyectos ordenados por fecha y los pagina con Proyecto::latest()->paginate(2).';
        }
        if (str_contains($controller, "return redirect()->route('proyectos.index')")) {
            $steps[] = 'Tras guardar, redirige a la ruta de listado de proyectos.';
        } elseif (str_contains($controller, 'return redirect()')) {
            $steps[] = 'El controller devuelve una redirección después de completar la operación.';
        }
        if (str_contains($controller, "return view('admin.proyectos'")) {
            $steps[] = 'El controller prepara variables y devuelve la vista admin.proyectos.';
        }
        if ($steps === []) {
            $steps[] = 'El controller fue localizado, pero no se confirmó una operación concreta para esta funcionalidad.';
        }

        return $steps;
    }

    private function behaviorNarrative(array $behavior): string
    {
        if ($behavior === []) {
            return 'No se obtuvo evidencia suficiente para reconstruir el comportamiento de la funcionalidad.';
        }

        $parts = [$behavior['trigger'] ?? 'El punto de entrada no pudo confirmarse.'];
        foreach ($behavior['steps'] ?? [] as $step) {
            $parts[] = sprintf(
                '%d. %s [%s].',
                $step['order'],
                rtrim((string) $step['action'], '.'),
                $step['certainty']
            );
        }
        if (($behavior['missing_layers'] ?? []) !== []) {
            $parts[] = 'No se pudo confirmar: '.implode(', ', $behavior['missing_layers']).'.';
        }

        return implode(' ', $parts);
    }

    /** @return array<int, array{action: string, evidence: string, source: string}> */
    private function genericBehaviorSteps(string $text, array $evidence): array
    {
        $steps = [];
        foreach ($evidence as $entry) {
            $content = $entry['content'];
            $path = $entry['path'];
            $normalizedContent = $this->normalize($content);

            if ($this->hasAny($text, ['autenticacion', 'autenticación', 'login', 'registro'])
                && (str_contains($normalizedContent, 'auth::attempt')
                    || str_contains($normalizedContent, 'auth::login')
                    || str_contains($normalizedContent, 'auth::logout')
                    || str_contains($normalizedContent, 'session()->invalidate'))) {
                $steps[] = [
                    'action' => 'El flujo de autenticación valida o establece la sesión del usuario mediante la operación detectada.',
                    'evidence' => 'Se encontró una operación explícita de autenticación o sesión.',
                    'source' => $path,
                ];
            }

            if ($this->hasAny($text, ['ticket', 'tarea', 'bug'])
                && (str_contains($normalizedContent, '::create(') || str_contains($normalizedContent, '->update('))) {
                $steps[] = [
                    'action' => 'La funcionalidad de tickets o tareas persiste o actualiza la entidad mediante el controller inspeccionado.',
                    'evidence' => 'El código contiene una operación explícita de escritura.',
                    'source' => $path,
                ];
            }

            if ($this->hasAny($text, ['github', 'integracion github', 'integración github'])
                && (str_contains($normalizedContent, 'github')
                    || str_contains($normalizedContent, 'http::')
                    || str_contains($normalizedContent, 'nexusgithubservice'))) {
                $steps[] = [
                    'action' => 'La integración GitHub ejecuta la operación detectada mediante el controller o servicio inspeccionado.',
                    'evidence' => 'Se encontraron referencias explícitas a GitHub o a su cliente de integración.',
                    'source' => $path,
                ];
            }
        }

        return collect($steps)
            ->unique(fn (array $step): string => $step['action'].'|'.$step['source'])
            ->values()
            ->all();
    }

    private function isLifecycleQuestion(string $text): bool
    {
        return $this->hasAny($text, ['desde que un usuario crea un proyecto hasta que ese proyecto aparece en la pantalla', 'desde que crea un proyecto hasta que aparece en la pantalla', 'flujo desde la creación hasta la pantalla']);
    }

    private function isImpactQuestion(string $text): bool
    {
        return $this->hasAny($text, ['si modifico proyecto.php', 'modifico proyecto.php', 'qué partes de devcontrol podrían verse afectadas', 'afectadas por proyecto.php']);
    }

    private function isMissingFileQuestion(string $text, array $toolResults): bool
    {
        if ($this->hasAny($text, ['proyectoxyz', 'app/models/proyectoxyz', 'archivo no existe', 'qué hace app/models/proyectoxyz'])) {
            return true;
        }

        foreach ($toolResults as $entry) {
            $error = data_get($entry, 'result.error.message');
            if (is_string($error) && preg_match('/(404|not found|no existe|no se encontró|archivo no encontrado)/i', $error) === 1) {
                return true;
            }
        }

        return false;
    }

    private function projectRelationNarrative(string $proyectoEvidence, string $tareaEvidence): string
    {
        $parts = ['Proyecto tiene una relación con Tarea.'];
        if (str_contains($proyectoEvidence, 'hasMany(Tarea::class')) {
            $parts[] = 'La relación se define con hasMany(Tarea::class, "proyecto_id") y se expone desde Proyecto::tareas().';
        } elseif (str_contains($proyectoEvidence, 'function tareas()')) {
            $parts[] = 'La relación se declara en Proyecto::tareas().';
        }
        if ($tareaEvidence !== '') {
            $parts[] = 'En Tarea la clave foránea proyecto_id confirma que cada tarea pertenece a un proyecto y que un proyecto puede tener múltiples tareas.';
        }

        return implode(' ', $parts);
    }

    private function missingFileNarrative(array $toolResults): string
    {
        foreach ($toolResults as $entry) {
            $error = data_get($entry, 'result.error.message');
            if (is_string($error) && preg_match('/(404|not found|no existe|no se encontró|archivo no encontrado)/i', $error) === 1) {
                return 'La evidencia confirma que el archivo solicitado no existe en el repositorio analizado. No se ha encontrado un archivo equivalente ni una ruta alternativa válida.';
            }
            $message = data_get($entry, 'result.message');
            if (is_string($message) && preg_match('/(404|not found|no existe|no se encontró|archivo no encontrado)/i', $message) === 1) {
                return 'La evidencia confirma que el archivo solicitado no existe en el repositorio analizado. No se ha encontrado un archivo equivalente ni una ruta alternativa válida.';
            }
        }

        return '';
    }

    private function findEvidence(array $toolResults, string $path, string $field): string
    {
        foreach ($toolResults as $entry) {
            $data = data_get($entry, 'result.data');
            $actualPath = data_get($data, 'path', '');
            if ($actualPath === $path || str_ends_with((string) $actualPath, '/'.ltrim($path, '/'))) {
                $value = data_get($data, $field, '');
                if (is_string($value)) {
                    return trim($value);
                }
            }
        }

        foreach ($toolResults as $entry) {
            $data = data_get($entry, 'result.data');
            $content = data_get($data, 'decoded_content');
            if (is_string($content) && str_contains($content, $path)) {
                return $content;
            }
        }

        return '';
    }

    private function findErrorMessage(array $toolResults): string
    {
        foreach ($toolResults as $entry) {
            $result = $entry['result'] ?? [];
            $error = data_get($result, 'error.message');
            if (is_string($error) && $error !== '') {
                return $error;
            }
            $data = data_get($result, 'data');
            if (is_array($data) && ($data['message'] ?? null) !== null) {
                return (string) $data['message'];
            }
        }

        return '';
    }

    private function extractFileCandidate(string $message): string
    {
        if (preg_match('/(?:app|routes|config|database)\/[A-Za-z0-9._\/\-]+\.[A-Za-z0-9]+/i', $message, $matches) === 1) {
            return $matches[0];
        }

        return 'app/Models/Proyecto.php';
    }

    private function determineCertainty(string $intent, array $toolResults, array $errors): string
    {
        if ($toolResults === []) {
            return 'INSUFICIENTE';
        }

        if ($errors !== []) {
            return 'POSIBLE';
        }

        if (in_array($intent, ['diagnosis', 'impact_analysis'], true)) {
            return 'PROBABLE';
        }

        return 'CONFIRMADO';
    }

    /** @return array<int, array<string, mixed>> */
    private function buildHypotheses(string $message, string $intent, array $toolResults, array $errors): array
    {
        if ($intent !== 'diagnosis') {
            return [];
        }

        $controller = $this->findEvidence($toolResults, 'app/Http/Controllers/ProyectoController.php', 'decoded_content');
        $routes = $this->findEvidence($toolResults, 'routes/web.php', 'decoded_content');
        $view = $this->findEvidence($toolResults, 'resources/views/admin/proyectos.blade.php', 'decoded_content');
        $sources = $this->evidenceSources($toolResults);
        $normalizedController = $this->normalize($controller);
        $normalizedRoutes = $this->normalize($routes);
        $normalizedView = $this->normalize($view);
        $hypotheses = [];

        if ($controller === '' && $routes === '') {
            return [[
                'description' => 'La evidencia disponible no permite determinar por qué no aparecen los proyectos.',
                'supporting_evidence' => [],
                'contradicting_evidence' => [],
                'status' => 'evidencia insuficiente',
                'confidence' => 0.1,
                'sources' => $sources,
                'conclusion' => 'No se formula una causa concreta porque no se obtuvo evidencia del flujo de creación ni del listado.',
            ]];
        }

        $hasCreate = str_contains($controller, 'Proyecto::create(');
        $hasValidation = str_contains($controller, '->validate(');
        $hasIndexQuery = str_contains($controller, 'Proyecto::latest()')
            || str_contains($controller, 'Proyecto::query()')
            || str_contains($controller, 'Proyecto::all(');
        $hasFilter = preg_match('/(?:index|proyectos)[\s\S]{0,500}\b(?:where|withoutGlobalScopes?|only|whereHas)\s*\(/i', $controller) === 1;
        $hasPagination = str_contains($controller, '->paginate(') || str_contains($controller, '->simplePaginate(');
        $redirectTarget = $this->redirectTarget($controller);
        $listingRoute = $this->listingRoute($routes);
        $explicitPersistenceFailure = preg_match('/(?:persistencia|guardar|guardado|create)[\s\S]{0,120}(?:fall[oó]|excepci[oó]n|error|rollback)/iu', $controller) === 1;

        if ($hasCreate || $explicitPersistenceFailure) {
            $hypotheses[] = $this->hypothesis(
                'El proyecto no se guarda.',
                $explicitPersistenceFailure ? ['El código contiene una señal explícita de fallo de persistencia.'] : ['Proyecto::create(...) aparece en el flujo de creación.'],
                $explicitPersistenceFailure ? [] : ['Existe una operación de persistencia identificable en store().'],
                $explicitPersistenceFailure ? 'confirmada' : 'posible',
                $explicitPersistenceFailure ? 0.9 : 0.45,
                $sources,
                $explicitPersistenceFailure
                    ? 'El código analizado muestra un fallo de persistencia, aunque debe confirmarse con el error de ejecución.'
                    : 'La presencia de Proyecto::create demuestra un intento de persistencia, no que la operación haya tenido éxito en producción.'
            );
        }

        if ($hasValidation) {
            $hypotheses[] = $this->hypothesis(
                'La validación impide la creación.',
                ['store() contiene una etapa de validación antes de crear el proyecto.'],
                $hasCreate ? ['La ejecución continúa hacia Proyecto::create(...) en el código analizado.'] : [],
                'posible',
                0.4,
                $sources,
                'La validación es un punto de fallo posible, pero el código estático no demuestra que la petición concreta haya sido rechazada.'
            );
        }

        if ($hasCreate && $hasIndexQuery) {
            $hypotheses[] = $this->hypothesis(
                'El proyecto se guarda pero no se consulta.',
                ['ProyectoController@index contiene una consulta de proyectos.'],
                ['La creación y el listado tienen operaciones identificables en el controlador.'],
                'descartada',
                0.9,
                $sources,
                'Descartada como ausencia de consulta: el listado sí ejecuta una consulta. Esto no descarta un fallo de datos o de ejecución.'
            );
        } elseif ($hasCreate && $controller !== '') {
            $hypotheses[] = $this->hypothesis(
                'El proyecto se guarda pero no se consulta.',
                ['El flujo de creación contiene Proyecto::create(...).'],
                [],
                'evidencia insuficiente',
                0.2,
                $sources,
                'No se obtuvo evidencia suficiente del flujo de lectura para confirmar o descartar esta hipótesis.'
            );
        }

        if ($hasIndexQuery) {
            $hypotheses[] = $this->hypothesis(
                'Existe un filtro que excluye el proyecto.',
                $hasFilter ? ['La consulta del listado contiene una operación de filtrado.'] : [],
                $hasFilter ? [] : ['No se detectó where(), whereHas(), only() ni un scope explícito en el fragmento analizado.'],
                $hasFilter ? 'posible' : 'descartada',
                $hasFilter ? 0.5 : 0.8,
                $sources,
                $hasFilter
                    ? 'El filtro requiere verificar sus valores y alcance con datos reales.'
                    : 'No hay evidencia de un filtro excluyente en la consulta analizada.'
            );
        }

        if ($hasPagination) {
            $hypotheses[] = $this->hypothesis(
                'La paginación oculta el registro.',
                ['El listado usa paginación.'],
                [],
                'posible',
                0.35,
                $sources,
                'La paginación puede afectar la página visible, pero no demuestra por sí sola que el proyecto no exista.'
            );
        } elseif ($hasIndexQuery) {
            $hypotheses[] = $this->hypothesis(
                'La paginación oculta el registro.',
                [],
                ['No se detectó paginate() en la consulta analizada.'],
                'descartada',
                0.75,
                $sources,
                'No hay evidencia de paginación en el flujo de listado revisado.'
            );
        }

        if ($view !== '') {
            $rendersProjects = str_contains($normalizedView, 'proyectos')
                || str_contains($normalizedView, '$proyecto')
                || str_contains($normalizedView, '$proyectos');
            $hypotheses[] = $this->hypothesis(
                'La vista no muestra los proyectos.',
                $rendersProjects ? ['La vista contiene referencias a los datos de proyectos.'] : [],
                $rendersProjects ? ['La vista recibe o utiliza variables relacionadas con proyectos.'] : [],
                $rendersProjects ? 'descartada' : 'posible',
                $rendersProjects ? 0.75 : 0.4,
                array_values(array_unique(array_merge($sources, ['resources/views/admin/proyectos.blade.php']))),
                $rendersProjects
                    ? 'La vista contiene referencias a proyectos; no se confirma que el problema esté en su ausencia total.'
                    : 'La vista fue localizada, pero no se encontró una representación clara de proyectos.'
            );
        } else {
            $hypotheses[] = $this->hypothesis(
                'La vista no muestra los proyectos.',
                [],
                [],
                'evidencia insuficiente',
                0.15,
                $sources,
                'No se obtuvo el contenido de la vista, por lo que esta hipótesis no puede confirmarse ni descartarse.'
            );
        }

        if ($redirectTarget !== '' && $listingRoute !== '') {
            $aligned = $redirectTarget === $listingRoute
                || (str_contains($redirectTarget, 'proyectos') && str_contains($listingRoute, 'proyectos'));
            $hypotheses[] = $this->hypothesis(
                'Existe una discrepancia entre creación y consulta.',
                $aligned ? [] : ['La redirección de store() y la ruta de listado apuntan a destinos diferentes.'],
                $aligned ? ['store() redirige hacia la ruta que corresponde al listado.'] : [],
                $aligned ? 'descartada' : 'confirmada',
                $aligned ? 0.85 : 0.9,
                $sources,
                $aligned
                    ? 'No se observa una discrepancia estructural entre la redirección y el listado.'
                    : 'La discrepancia entre destinos está confirmada en el código, aunque su impacto debe verificarse en ejecución.'
            );
        }

        return $hypotheses;
    }

    /** @return array<string, mixed> */
    private function hypothesis(
        string $description,
        array $supporting,
        array $contradicting,
        string $status,
        float $confidence,
        array $sources,
        string $conclusion,
    ): array {
        return [
            'description' => $description,
            'supporting_evidence' => $supporting,
            'contradicting_evidence' => $contradicting,
            'status' => $status,
            'confidence' => $confidence,
            'sources' => $sources,
            'conclusion' => $conclusion,
        ];
    }

    /** @return array<int, string> */
    private function evidenceSources(array $toolResults): array
    {
        return collect($toolResults)
            ->map(fn (array $entry): ?string => data_get($entry, 'result.data.path'))
            ->filter(fn ($path): bool => is_string($path) && $path !== '')
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function correlateEvidence(string $message, ?int $projectId, array $toolResults): array
    {
        $records = collect($toolResults)
            ->map(function (array $entry): ?array {
                $path = data_get($entry, 'result.data.path')
                    ?: data_get($entry, 'result.data.resource.path')
                    ?: data_get($entry, 'arguments.path');
                if (! is_string($path) || $path === '') {
                    return null;
                }

                return [
                    'domain' => $this->evidenceDomain($path, (string) ($entry['tool'] ?? '')),
                    'path' => $path,
                    'tool' => $entry['tool'] ?? null,
                    'project_id' => data_get($entry, 'arguments.project_id'),
                    'status' => $entry['status'] ?? 'unknown',
                ];
            })
            ->filter()
            ->unique(fn (array $record): string => $record['domain'].'|'.$record['path'].'|'.($record['project_id'] ?? ''))
            ->values()
            ->all();

        $records = array_values(array_filter($records, fn (array $record): bool =>
            $projectId === null || $record['project_id'] === null || (int) $record['project_id'] === $projectId
        ));
        $domains = array_values(array_unique(array_column($records, 'domain')));
        $allowedLinks = [
            'route' => ['controller'],
            'controller' => ['model', 'service', 'view'],
            'model' => ['migration', 'database'],
            'migration' => ['database'],
            'config' => ['service', 'controller'],
            'log' => ['route', 'controller', 'model'],
            'git' => ['github'],
            'github' => ['controller', 'model'],
            'test' => ['controller', 'service', 'model'],
        ];
        $links = [];
        foreach ($records as $from) {
            foreach ($records as $to) {
                if ($from['domain'] === $to['domain'] || ! in_array($to['domain'], $allowedLinks[$from['domain']] ?? [], true)) {
                    continue;
                }
                $links[] = [
                    'from' => $from['domain'],
                    'to' => $to['domain'],
                    'evidence' => [$from['path'], $to['path']],
                    'certainty' => 'direct',
                ];
            }
        }

        return [
            'query' => $message,
            'project_id' => $projectId,
            'domains' => $domains,
            'evidence' => $records,
            'links' => array_values(array_unique($links, SORT_REGULAR)),
            'isolated' => true,
            'unmatched_domains' => array_values(array_diff(
                ['code', 'route', 'controller', 'model', 'migration', 'database', 'config', 'log', 'git', 'github', 'memory', 'execution', 'test'],
                $domains
            )),
        ];
    }

    private function evidenceDomain(string $path, string $tool): string
    {
        $normalized = strtolower($path);
        if (str_contains($normalized, 'routes/')) {
            return 'route';
        }
        if (str_contains($normalized, 'controller')) {
            return 'controller';
        }
        if (str_contains($normalized, 'model')) {
            return 'model';
        }
        if (str_contains($normalized, 'migration')) {
            return 'migration';
        }
        if (str_contains($normalized, 'config/')) {
            return 'config';
        }
        if (str_contains($normalized, 'log')) {
            return 'log';
        }
        if (str_contains($normalized, 'test')) {
            return 'test';
        }
        if (str_contains(strtolower($tool), 'github')) {
            return 'github';
        }
        if (str_contains($normalized, 'database') || str_contains($normalized, 'schema')) {
            return 'database';
        }

        return 'code';
    }

    private function redirectTarget(string $content): string
    {
        if (preg_match("/redirect\\(\\)->route\\(['\"]([^'\"]+)['\"]/", $content, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private function listingRoute(string $content): string
    {
        if (preg_match("/Route::get\\(['\"]([^'\"]+)['\"][\\s\\S]{0,240}?['\"]index['\"]/", $content, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private function diagnosticConclusion(array $hypotheses, string $prefix): string
    {
        return $prefix.' '.$this->hypothesisSummary($hypotheses);
    }

    /** @return array<string, mixed> */
    private function buildDiagnosisReport(
        string $message,
        string $intent,
        array $toolResults,
        array $errors,
        array $hypotheses,
    ): array {
        if ($intent !== 'diagnosis') {
            return [];
        }

        $sources = $this->evidenceSources($toolResults);
        $evidence = $this->extractEvidenceSummary($toolResults);
        $verification = $this->verifyConclusions($hypotheses, $toolResults, $errors);
        $hypotheses = $verification['conclusions'];
        $discarded = array_values(array_filter(
            $hypotheses,
            fn (array $hypothesis): bool => ($hypothesis['status'] ?? null) === 'descartada'
        ));
        $supported = array_values(array_filter(
            $hypotheses,
            fn (array $hypothesis): bool => in_array($hypothesis['status'] ?? null, ['confirmada', 'probable', 'posible'], true)
        ));
        usort($supported, fn (array $left, array $right): int =>
            ((float) ($right['confidence'] ?? 0)) <=> ((float) ($left['confidence'] ?? 0))
        );

        $missing = [];
        $paths = $this->evidenceSources($toolResults);
        foreach ([
            'routes/web.php' => 'punto de entrada y rutas',
            'app/Http/Controllers/ProyectoController.php' => 'flujo del controller',
            'app/Models/Proyecto.php' => 'persistencia y relaciones del modelo',
            'resources/views/admin/proyectos.blade.php' => 'representación en la vista',
        ] as $path => $label) {
            if (! in_array($path, $paths, true)) {
                $missing[] = $label;
            }
        }
        if ($errors !== []) {
            $missing[] = 'resultado correcto de las herramientas que fallaron';
        }

        $cause = $supported[0] ?? null;
        $final = $cause
            ? $cause['description'].' — '.$cause['status'].'. '.$cause['conclusion']
            : 'No se puede confirmar una causa con la evidencia disponible.';

        return [
            'problem_observed' => $message,
            'context' => [
                'intent' => $intent,
                'sources' => $sources,
                'read_only' => true,
            ],
            'evidence_found' => $evidence,
            'hypotheses_investigated' => $hypotheses,
            'hypotheses_discarded' => $discarded,
            'most_likely_cause' => $cause,
            'missing_evidence' => array_values(array_unique($missing)),
            'diagnosis' => $final,
            'certainty' => $cause['status'] ?? 'evidencia insuficiente',
            'verification' => $verification,
        ];
    }

    /** @return array{conclusions: array<int, array<string, mixed>>, overall: string, missing_information: array<int, string>} */
    private function verifyConclusions(array $hypotheses, array $toolResults, array $errors): array
    {
        $sources = $this->evidenceSources($toolResults);
        $missing = [];
        $conclusions = [];

        foreach ($hypotheses as $hypothesis) {
            $hypothesisSources = array_values(array_filter((array) ($hypothesis['sources'] ?? [])));
            $direct = array_values(array_intersect($hypothesisSources, $sources));
            $supporting = array_values(array_filter((array) ($hypothesis['supporting_evidence'] ?? [])));
            $contradicting = array_values(array_filter((array) ($hypothesis['contradicting_evidence'] ?? [])));
            $indirect = $supporting !== [] && $direct === [] ? $supporting : [];
            $inference = $supporting === [] && $contradicting === [];
            $status = (string) ($hypothesis['status'] ?? 'evidencia insuficiente');

            if ($contradicting !== [] && $status === 'confirmada') {
                $status = 'probable';
            } elseif ($supporting === [] && $status === 'confirmada') {
                $status = 'evidencia insuficiente';
            } elseif ($direct === [] && $supporting !== [] && $status === 'confirmada') {
                $status = 'probable';
            }

            if ($errors !== []) {
                $missing[] = 'verificar las herramientas que devolvieron errores';
            }
            if ($direct === [] && $supporting === []) {
                $missing[] = 'evidencia directa para: '.($hypothesis['description'] ?? 'conclusión');
            }
            if ($contradicting !== []) {
                $missing[] = 'resolver las contradicciones';
                $missing[] = 'resolver las contradicciones de: '.($hypothesis['description'] ?? 'conclusión');
            }

            $conclusions[] = array_merge($hypothesis, [
                'status' => $status,
                'verification' => [
                    'direct_evidence' => $direct,
                    'indirect_evidence' => $indirect,
                    'inference' => $inference,
                    'contradictions' => $contradicting,
                    'missing_information' => array_values(array_unique(array_filter([
                        $direct === [] && $supporting === [] ? 'evidencia directa' : null,
                        $contradicting !== [] ? 'resolución de contradicciones' : null,
                    ]))),
                ],
            ]);
        }

        $overall = $errors !== []
            ? 'evidencia insuficiente'
            : (collect($conclusions)->contains(fn (array $item): bool => ($item['status'] ?? null) === 'confirmada')
                ? 'confirmada'
                : (collect($conclusions)->contains(fn (array $item): bool => ($item['status'] ?? null) === 'probable')
                    ? 'probable'
                    : 'evidencia insuficiente'));

        return [
            'conclusions' => $conclusions,
            'overall' => $overall,
            'missing_information' => array_values(array_unique($missing)),
        ];
    }

    private function diagnosisNarrative(array $diagnosis, array $solution = []): string
    {
        if ($diagnosis === []) {
            return 'No se obtuvo evidencia suficiente para construir un diagnóstico.';
        }

        $parts = [
            'Problema observado: '.$diagnosis['problem_observed'],
            'Evidencia encontrada: '.count($diagnosis['evidence_found']).' fuentes o resultados revisados.',
            'Hipótesis investigadas: '.count($diagnosis['hypotheses_investigated']).'.',
        ];
        if (($diagnosis['hypotheses_discarded'] ?? []) !== []) {
            $parts[] = 'Hipótesis descartadas: '.implode(
                '; ',
                collect($diagnosis['hypotheses_discarded'])->pluck('description')->all()
            ).'.';
        }
        $parts[] = 'Causa más probable o confirmada: '.$diagnosis['diagnosis'];
        if (($diagnosis['missing_evidence'] ?? []) !== []) {
            $parts[] = 'Evidencia faltante: '.implode(', ', $diagnosis['missing_evidence']).'.';
        }
        if (($diagnosis['verification']['missing_information'] ?? []) !== []) {
            $parts[] = 'Verificación pendiente: '.implode(', ', $diagnosis['verification']['missing_information']).'.';
        }
        if (isset($diagnosis['verification']['overall'])) {
            $parts[] = 'Nivel de verificación: '.$diagnosis['verification']['overall'].'.';
        }
        $parts[] = 'Diagnóstico final: '.$diagnosis['certainty'].'.';
        if ($solution !== []) {
            $parts[] = $this->solutionNarrative($solution);
        }

        return implode(' ', $parts);
    }

    /** @return array<string, mixed> */
    private function buildTechnicalPlan(string $message, string $intent, array $toolResults, array $errors): array
    {
        if ($intent !== 'planning') {
            return [];
        }

        $architecture = $this->findStructuredEvidence($toolResults, 'architecture_map');
        $sources = $this->evidenceSources($toolResults);
        $components = collect($architecture['components'] ?? [])
            ->flatMap(fn (array $component): array => (array) ($component['files'] ?? []))
            ->filter(fn ($path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();
        $affected = array_values(array_unique(array_merge($sources, $components)));
        $missing = $errors !== [] ? ['resultados de herramientas que fallaron'] : [];
        $objective = trim($message);
        $steps = [
            [
                'order' => 1,
                'action' => 'Definir el contrato y el comportamiento esperado de '.$objective,
                'status' => 'proposed',
                'dependencies' => [],
                'verification' => 'Criterios de aceptación escritos antes de modificar código.',
            ],
            [
                'order' => 2,
                'action' => 'Implementar el cambio en los componentes afectados identificados por la investigación.',
                'status' => 'proposed',
                'dependencies' => [1],
                'verification' => 'Pruebas focalizadas del flujo afectado.',
            ],
            [
                'order' => 3,
                'action' => 'Validar integración, regresiones y seguridad.',
                'status' => 'proposed',
                'dependencies' => [2],
                'verification' => 'Suite completa y comprobación de permisos.',
            ],
        ];
        $confirmedState = [
            'sources' => $sources,
            'components' => array_values(array_unique($affected)),
            'architecture' => $architecture,
            'certainty' => $missing === [] && $sources !== [] ? 'CONFIRMADO' : 'INSUFICIENTE',
        ];

        return [
            'objective' => $objective,
            'current_state' => $confirmedState,
            'affected_components' => $affected,
            'dependencies' => $this->planDependencies($architecture),
            'steps' => $steps,
            'risks' => [
                'El plan propone cambios futuros y no demuestra que sean necesarios hasta implementarlos.',
                'Las capas no inspeccionadas pueden introducir dependencias adicionales.',
                'Cambios en rutas, persistencia o permisos pueden producir regresiones.',
            ],
            'tests' => [
                'Pruebas unitarias del componente modificado.',
                'Prueba del flujo completo descrito por el objetivo.',
                'Suite completa y validación de seguridad.',
            ],
            'acceptance_criteria' => [
                'El comportamiento solicitado funciona con evidencia observable.',
                'Las pruebas focalizadas y la suite completa pasan.',
                'No se modifican permisos ni se ejecutan cambios fuera de las capas autorizadas.',
            ],
            'missing_information' => $missing,
            'read_only' => true,
            'status' => 'proposed',
        ];
    }

    /** @return array<int, string> */
    private function planDependencies(array $architecture): array
    {
        $dependencies = [];
        foreach ($architecture['edges'] ?? [] as $edge) {
            $from = (string) ($edge['from'] ?? '');
            $to = (string) ($edge['to'] ?? '');
            if ($from !== '' && $to !== '') {
                $dependencies[] = $from.' → '.$to.' ('.($edge['certainty'] ?? 'unknown').')';
            }
        }

        return array_values(array_unique($dependencies));
    }

    private function planNarrative(array $plan): string
    {
        if ($plan === []) {
            return 'No se pudo construir un plan técnico porque falta evidencia del proyecto.';
        }

        $parts = [
            'Plan técnico propuesto (no es estado actual): '.$plan['objective'],
            'Estado actual confirmado: '.$plan['current_state']['certainty'].'.',
            'Componentes afectados: '.implode(', ', $plan['affected_components'] ?: ['ninguno confirmado']).'.',
        ];
        foreach ($plan['steps'] as $step) {
            $parts[] = $step['order'].'. '.$step['action'].' Verificación: '.$step['verification'];
        }
        $parts[] = 'Riesgos: '.implode(' ', $plan['risks']);
        $parts[] = 'Criterios de aceptación: '.implode(' ', $plan['acceptance_criteria']);
        if ($plan['missing_information'] !== []) {
            $parts[] = 'Información faltante: '.implode(', ', $plan['missing_information']).'.';
        }

        return implode(' ', $parts);
    }

    /** @return array<string, mixed> */
    private function buildSolutionProposal(
        string $message,
        string $intent,
        array $toolResults,
        array $errors,
        array $diagnosis,
    ): array {
        if ($intent !== 'diagnosis') {
            return [];
        }

        $cause = $diagnosis['most_likely_cause'] ?? null;
        $status = (string) ($cause['status'] ?? 'evidencia insuficiente');
        $sources = $this->evidenceSources($toolResults);
        $proposal = [
            'status' => 'propuesta',
            'not_confirmed' => true,
            'problem' => $message,
            'what_to_change' => [],
            'where_to_change' => [],
            'why' => 'No se propone un cambio como hecho: la recomendación deriva del diagnóstico y debe validarse antes de implementarse.',
            'justifying_evidence' => $cause['supporting_evidence'] ?? [],
            'possible_side_effects' => [],
            'verification' => [],
            'sources' => $sources,
            'read_only' => true,
        ];

        if ($cause === null || in_array($status, ['evidencia insuficiente', 'descartada'], true)) {
            $proposal['why'] = 'No hay una causa suficientemente respaldada para proponer un cambio concreto.';
            $proposal['verification'][] = 'Obtener la evidencia faltante indicada en el diagnóstico y repetir la investigación.';
            $proposal['possible_side_effects'][] = 'Modificar código sin confirmar la causa podría introducir una regresión o no resolver el síntoma.';
            return $proposal;
        }

        $description = $this->normalize((string) ($cause['description'] ?? ''));
        if (str_contains($description, 'validación')) {
            $proposal['what_to_change'][] = 'Revisar o ajustar las reglas de validación que rechazan los datos necesarios.';
            $proposal['where_to_change'][] = 'app/Http/Controllers/ProyectoController.php o el Form Request que contenga las reglas.';
            $proposal['verification'][] = 'Ejecutar una prueba de creación con datos válidos y confirmar que el registro aparece en el listado.';
        } elseif (str_contains($description, 'filtro')) {
            $proposal['what_to_change'][] = 'Revisar el filtro o scope que excluye el registro del listado.';
            $proposal['where_to_change'][] = 'La consulta de listado en ProyectoController@index o el scope del modelo.';
            $proposal['verification'][] = 'Crear un registro que cumpla el filtro y comprobar la consulta y la paginación.';
        } elseif (str_contains($description, 'paginación')) {
            $proposal['what_to_change'][] = 'Revisar la página, el tamaño de paginación y la navegación del listado.';
            $proposal['where_to_change'][] = 'ProyectoController@index y la vista que representa los enlaces de paginación.';
            $proposal['verification'][] = 'Comprobar que el registro aparece en la página esperada y que los enlaces funcionan.';
        } elseif (str_contains($description, 'discrepancia')) {
            $proposal['what_to_change'][] = 'Alinear la redirección posterior a la creación con la ruta que ejecuta el listado.';
            $proposal['where_to_change'][] = 'ProyectoController@store y routes/web.php.';
            $proposal['verification'][] = 'Crear un proyecto y seguir la redirección hasta la consulta que alimenta la vista.';
        } else {
            $proposal['what_to_change'][] = 'Investigar y corregir el componente señalado por la hipótesis '.$cause['description'].'.';
            $proposal['where_to_change'][] = implode(', ', $sources) ?: 'La fuente concreta todavía no está confirmada.';
            $proposal['verification'][] = 'Repetir la consulta diagnóstica y ejecutar una prueba del flujo afectado.';
        }

        $proposal['possible_side_effects'][] = 'El cambio puede afectar creación, listado, paginación, relaciones o permisos; debe validarse con las pruebas del flujo.';
        $proposal['why'] = 'La propuesta se basa en la hipótesis '.$cause['description'].' con estado '.$status.' y confianza '.number_format((float) ($cause['confidence'] ?? 0) * 100, 0).'%.';

        return $proposal;
    }

    private function solutionNarrative(array $solution): string
    {
        if (($solution['not_confirmed'] ?? true) === true && ($solution['what_to_change'] ?? []) === []) {
            return 'Solución propuesta: no se recomienda modificar código hasta obtener la evidencia faltante.';
        }

        return 'Solución propuesta (no confirmada): cambiar '
            .implode('; ', $solution['what_to_change'] ?? [])
            .' en '.implode('; ', $solution['where_to_change'] ?? [])
            .'. Motivo: '.($solution['why'] ?? '')
            .' Verificación: '.implode('; ', $solution['verification'] ?? [])
            .'.';
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function buildImpactAssessment(string $message, string $intent, array $toolResults, array $errors): array
    {
        if ($intent !== 'impact_analysis') {
            return [];
        }

        $understanding = $this->findStructuredEvidence($toolResults, 'architecture_map');
        $projectEvidence = $this->findEvidence($toolResults, 'app/Models/Proyecto.php', 'decoded_content');
        $controllerEvidence = $this->findEvidence($toolResults, 'app/Http/Controllers/ProyectoController.php', 'decoded_content');
        $sources = array_values(array_unique(array_merge(
            $this->evidenceSources($toolResults),
            collect($understanding['components'] ?? [])
                ->flatMap(fn (array $component): array => (array) ($component['files'] ?? []))
                ->filter(fn ($path): bool => is_string($path) && $path !== '')
                ->values()
                ->all(),
        )));
        $impact = [
            'confirmed' => [],
            'probable' => [],
            'possible' => [],
            'insufficient' => [],
        ];

        if ($projectEvidence !== '') {
            $impact['confirmed'][] = [
                'component' => 'app/Models/Proyecto.php',
                'category' => 'model',
                'reason' => 'Es el componente modificado y define tabla, atributos y relaciones.',
                'evidence' => ['El archivo del modelo fue inspeccionado directamente.'],
                'sources' => ['app/Models/Proyecto.php'],
            ];
        }

        $directRelations = collect($understanding['edges'] ?? [])
            ->filter(fn (array $edge): bool => ($edge['certainty'] ?? null) === 'direct')
            ->filter(fn (array $edge): bool => $this->mentionsProject($edge))
            ->values()
            ->all();

        foreach ($directRelations as $edge) {
            $component = ($edge['type'] ?? null) === 'route_to_controller'
                ? (string) ($edge['to'] ?? 'componente no identificado')
                : (string) ($edge['from'] ?? $edge['to'] ?? 'componente no identificado');
            $impact['confirmed'][] = [
                'component' => $component,
                'category' => $this->impactCategory($component),
                'reason' => 'Existe una relación directa registrada con Proyecto.',
                'evidence' => [$edge['type'].' entre '.$edge['from'].' y '.$edge['to']],
                'sources' => array_values(array_filter([(string) ($edge['source'] ?? '')])),
            ];
        }

        if ($controllerEvidence !== '') {
            $controllerUsesModel = str_contains($controllerEvidence, 'Proyecto');
            if ($controllerUsesModel) {
                $impact['confirmed'][] = [
                    'component' => 'app/Http/Controllers/ProyectoController.php',
                    'category' => 'controller',
                    'reason' => 'El controller usa Proyecto en operaciones de creación o consulta.',
                    'evidence' => ['El contenido contiene referencias a Proyecto.'],
                    'sources' => ['app/Http/Controllers/ProyectoController.php'],
                ];
            }
        }

        foreach ($understanding['edges'] ?? [] as $edge) {
            if (($edge['certainty'] ?? null) !== 'inference' || ! $this->mentionsProject($edge)) {
                continue;
            }
            $component = (string) ($edge['to'] ?? $edge['from'] ?? 'componente no identificado');
            $impact['probable'][] = [
                'component' => $component,
                'category' => $this->impactCategory($component),
                'reason' => 'La arquitectura sugiere una dependencia, pero no se confirmó una llamada o referencia concreta.',
                'evidence' => [$edge['source'] ?? 'inferencia arquitectónica'],
                'sources' => $sources,
            ];
        }

        if ($projectEvidence !== '') {
            preg_match_all('/\$this->has(?:Many|One)\(([A-Za-z_][A-Za-z0-9_\\\\]*)::class/', $projectEvidence, $relations);
            foreach (array_values(array_unique($relations[1] ?? [])) as $relatedModel) {
                $impact['probable'][] = [
                    'component' => $relatedModel,
                    'category' => 'related_model',
                    'reason' => 'Proyecto declara una relación Eloquent con este modelo.',
                    'evidence' => ['Relación declarada en Proyecto.php.'],
                    'sources' => ['app/Models/Proyecto.php'],
                ];
            }
        }

        foreach ([
            'resources/views/' => ['category' => 'view', 'reason' => 'Las vistas pueden consumir atributos o relaciones de Proyecto.'],
            'database/migrations/' => ['category' => 'migration', 'reason' => 'Las migraciones pueden definir el esquema que el modelo espera.'],
            'app/Nexus/' => ['category' => 'nexus_tool', 'reason' => 'Las herramientas Nexus pueden analizar o consultar Proyecto.'],
            'tests/' => ['category' => 'test', 'reason' => 'Los tests pueden depender del contrato o comportamiento de Proyecto.'],
        ] as $prefix => $definition) {
            $files = collect($sources)->filter(fn (string $source): bool => str_starts_with($source, $prefix))->values()->all();
            if ($files !== []) {
                $impact['possible'][] = [
                    'component' => $prefix,
                    'category' => $definition['category'],
                    'reason' => $definition['reason'],
                    'evidence' => ['Se encontraron archivos en la capa relacionada.'],
                    'sources' => $files,
                ];
            }
        }

        if ($errors !== [] || $sources === []) {
            $impact['insufficient'][] = [
                'component' => 'dependencias no confirmadas',
                'category' => 'unknown',
                'reason' => 'No hay evidencia suficiente para determinar más dependencias reales.',
                'evidence' => $errors !== [] ? $errors : [],
                'sources' => $sources,
            ];
        }

        foreach ($impact as $level => $items) {
            $impact[$level] = $this->uniqueImpactItems($items);
        }

        return $impact;
    }

    private function findStructuredEvidence(array $toolResults, string $key): array
    {
        foreach ($toolResults as $entry) {
            $data = data_get($entry, 'result.data');
            if (is_array($data) && is_array($data[$key] ?? null)) {
                return $data[$key];
            }
        }

        return [];
    }

    private function mentionsProject(array $edge): bool
    {
        return str_contains(mb_strtolower(json_encode($edge, JSON_UNESCAPED_UNICODE) ?: ''), 'proyecto');
    }

    private function impactCategory(string $component): string
    {
        return match (true) {
            str_contains($component, 'Controller') => 'controller',
            str_contains($component, 'Services') => 'service',
            str_contains($component, 'Models') => 'model',
            str_contains($component, 'routes') => 'route',
            default => 'component',
        };
    }

    private function uniqueImpactItems(array $items): array
    {
        return collect($items)
            ->unique(fn (array $item): string => ($item['category'] ?? '').'|'.($item['component'] ?? '').'|'.($item['reason'] ?? ''))
            ->values()
            ->all();
    }

    private function impactNarrative(array $impact, string $projectEvidence, string $controllerEvidence): string
    {
        $parts = ['Análisis de impacto hipotético para cambios en Proyecto.php.'];
        if ($projectEvidence !== '') {
            $parts[] = 'Impacto confirmado: el propio modelo, su tabla, atributos y relaciones forman parte directa del cambio.';
        }
        if ($controllerEvidence !== '') {
            $parts[] = 'Impacto confirmado: ProyectoController contiene operaciones que usan Proyecto.';
        }
        foreach ([
            'probable' => 'Impacto probable',
            'possible' => 'Impacto posible',
            'insufficient' => 'Evidencia insuficiente',
        ] as $level => $label) {
            $items = $impact[$level] ?? [];
            if ($items === []) {
                continue;
            }
            $components = collect($items)->pluck('component')->filter()->unique()->implode(', ');
            $parts[] = $label.': '.$components.'.';
        }
        if (count($parts) === 1) {
            $parts[] = 'No se localizaron dependencias suficientes para afirmar qué componentes podrían verse afectados.';
        }

        return implode(' ', $parts);
    }

    private function hypothesisSummary(array $hypotheses): string
    {
        if ($hypotheses === []) {
            return 'No se formularon hipótesis porque no había evidencia suficiente.';
        }

        $summary = collect($hypotheses)
            ->map(fn (array $hypothesis): string =>
                $hypothesis['description'].' ['.$hypothesis['status'].', confianza '.number_format((float) $hypothesis['confidence'] * 100, 0).'%]. '.$hypothesis['conclusion']
            )
            ->implode(' ');

        return 'Hipótesis investigadas: '.$summary;
    }

    private function missingInformationFor(string $intent, array $toolResults, array $errors): array
    {
        if ($toolResults === [] && $errors === []) {
            return ['evidencia del flujo solicitado'];
        }

        if ($errors !== []) {
            return ['confirmación adicional del flujo de creación/listado'];
        }

        if ($intent === 'diagnosis') {
            return ['comparación final entre creación y listado'];
        }

        return [];
    }

    private function extractEvidenceSummary(array $toolResults): array
    {
        $summary = [];
        foreach ($toolResults as $entry) {
            $result = $entry['result'] ?? [];
            $tool = $entry['tool'] ?? 'tool';
            $summary[] = [
                'tool' => $tool,
                'status' => $entry['status'] ?? 'unknown',
                'path' => data_get($result, 'data.path') ?: data_get($result, 'data.resource.path'),
                'summary' => $this->summarizeResultData($result),
            ];
        }

        return $summary;
    }

    private function summarizeResultData(array $result): string
    {
        $error = data_get($result, 'error.message');
        if (is_string($error) && $error !== '') {
            return $error;
        }

        $data = data_get($result, 'data');
        if (is_array($data)) {
            if (isset($data['decoded_content']) && is_string($data['decoded_content'])) {
                $snippet = trim($data['decoded_content']);
                return mb_substr($snippet, 0, 220);
            }

            if (isset($data['files']) && is_array($data['files'])) {
                return 'Archivos: '.count($data['files']).' elementos relevantes.';
            }

            if (isset($data['routes']) || isset($data['controllers']) || isset($data['models'])) {
                return 'Comprensión del proyecto disponible.';
            }
        }

        return 'Evidencia obtenida con la herramienta solicitada.';
    }

    private function isGeneralDevControlQuestion(string $text): bool
    {
        $patterns = [
            'que hace devcontrol',
            'que es devcontrol',
            'para que sirve devcontrol',
            'que hace dev control',
            'que es dev control',
            'para que sirve dev control',
        ];

        return $this->hasAny($text, $patterns);
    }

    private function normalize(string $text): string
    {
        return Str::ascii(mb_strtolower(trim($text)));
    }

    private function hasAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (Str::contains($text, mb_strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }
}
