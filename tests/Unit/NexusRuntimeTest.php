<?php

namespace Tests\Unit;

use App\Http\Controllers\AsistenteController;
use App\Nexus\NexusRuntime;
use App\Nexus\NexusRuntimeRequest;
use App\Nexus\NexusRuntimeResponse;
use App\Nexus\NexusToolRegistry;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class NexusRuntimeTest extends TestCase
{
    public function test_general_question_responds_without_tools(): void
    {
        $runtime = app(NexusRuntime::class);

        $response = $runtime->handle(new NexusRuntimeRequest(
            message: '¿Qué hace DevControl?',
        ));

        $this->assertSame('general', $response->intent);
        $this->assertSame([], $response->toolsUsed);
        $this->assertSame('runtime_general', $response->source);
        $this->assertStringContainsString('DevControl', $response->finalMessage);
    }

    public function test_diagnostic_query_is_classified_as_diagnosis_and_uses_registry_tools(): void
    {
        $runtime = app(NexusRuntime::class);

        $response = $runtime->handle(new NexusRuntimeRequest(
            message: 'Los proyectos no aparecen después de crearlos. Investiga por qué.',
            projectId: 1,
            context: ['project_id' => 1],
        ));

        $this->assertSame('diagnosis', $response->intent);
        $this->assertNotSame([], $response->toolsUsed);
        $this->assertContains('nexus.code.analyze', $response->toolsUsed);
        $this->assertStringContainsString('diagnóstico', strtolower($response->finalMessage));
        $this->assertStringNotContainsString('composer.json', strtolower($response->finalMessage));
    }

    public function test_diagnostic_investigation_progresses_beyond_generic_repository_synthesis(): void
    {
        $runtime = app(NexusRuntime::class);

        $response = $runtime->handle(new NexusRuntimeRequest(
            message: 'Los proyectos no aparecen después de crearlos. Investiga por qué.',
            projectId: 1,
        ));

        $this->assertSame('diagnosis', $response->intent);
        $this->assertSame('runtime_deterministic', $response->source);
        $this->assertSame('Localizar el flujo afectado, recopilar evidencia y separar hechos de hipótesis.', $response->investigation['goal']);
        $this->assertContains('additional_evidence', array_column($response->investigation['steps'], 'phase'));
        $this->assertNotSame([], $response->toolResults);
        $this->assertStringContainsString('Diagnóstico', $response->finalMessage);
        $this->assertStringNotContainsString('He revisado la comprensión del proyecto', $response->finalMessage);
    }

    public function test_diagnostic_response_contains_structured_hypotheses_with_evidence_and_status(): void
    {
        $response = app(NexusRuntime::class)->handle(new NexusRuntimeRequest(
            message: 'Los proyectos no aparecen después de crearlos. Investiga por qué.',
            projectId: 1,
        ));

        $this->assertNotSame([], $response->hypotheses);
        foreach ($response->hypotheses as $hypothesis) {
            $this->assertArrayHasKey('description', $hypothesis);
            $this->assertArrayHasKey('supporting_evidence', $hypothesis);
            $this->assertArrayHasKey('contradicting_evidence', $hypothesis);
            $this->assertArrayHasKey('status', $hypothesis);
            $this->assertArrayHasKey('confidence', $hypothesis);
            $this->assertArrayHasKey('sources', $hypothesis);
            $this->assertArrayHasKey('conclusion', $hypothesis);
            $this->assertContains($hypothesis['status'], [
                'confirmada',
                'descartada',
                'probable',
                'posible',
                'evidencia insuficiente',
            ]);
        }
    }

    public function test_diagnostic_response_exposes_formal_problem_evidence_and_conclusion(): void
    {
        $response = app(NexusRuntime::class)->handle(new NexusRuntimeRequest(
            message: 'Los proyectos no aparecen después de crearlos. Investiga por qué.',
            projectId: 1,
        ));

        $this->assertSame('Los proyectos no aparecen después de crearlos. Investiga por qué.', $response->diagnosis['problem_observed']);
        $this->assertArrayHasKey('evidence_found', $response->diagnosis);
        $this->assertArrayHasKey('hypotheses_investigated', $response->diagnosis);
        $this->assertArrayHasKey('hypotheses_discarded', $response->diagnosis);
        $this->assertArrayHasKey('most_likely_cause', $response->diagnosis);
        $this->assertArrayHasKey('missing_evidence', $response->diagnosis);
        $this->assertArrayHasKey('diagnosis', $response->diagnosis);
        $this->assertArrayHasKey('verification', $response->diagnosis);
        $this->assertArrayHasKey('overall', $response->diagnosis['verification']);
        $this->assertArrayHasKey('missing_information', $response->diagnosis['verification']);
        $this->assertArrayHasKey('overall', $response->verification);
        $this->assertStringContainsString('Problema observado:', $response->finalMessage);
        $this->assertStringContainsString('Evidencia encontrada:', $response->finalMessage);
        $this->assertStringContainsString('Diagnóstico final:', $response->finalMessage);
        $this->assertStringNotContainsString('causa confirmada', strtolower($response->finalMessage));
    }

    public function test_verification_downgrades_confirmed_conclusion_with_contradictory_evidence(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'verifyConclusions');
        $method->setAccessible(true);

        $verification = $method->invoke($runtime, [[
            'description' => 'El filtro excluye el proyecto.',
            'supporting_evidence' => ['La consulta contiene where().'],
            'contradicting_evidence' => ['La consulta también obtiene el registro sin filtro.'],
            'status' => 'confirmada',
            'confidence' => 0.9,
            'sources' => ['app/Http/Controllers/ProyectoController.php'],
            'conclusion' => 'La causa está confirmada.',
        ]], [[
            'result' => [
                'data' => [
                    'path' => 'app/Http/Controllers/ProyectoController.php',
                    'decoded_content' => 'where(active = 1)',
                ],
            ],
        ]], []);

        $conclusion = $verification['conclusions'][0];
        $this->assertSame('probable', $conclusion['status']);
        $this->assertNotEmpty($conclusion['verification']['direct_evidence']);
        $this->assertNotEmpty($conclusion['verification']['contradictions']);
        $this->assertContains('resolver las contradicciones', $verification['missing_information']);
    }

    public function test_verification_marks_textual_match_without_direct_evidence_as_insufficient(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'verifyConclusions');
        $method->setAccessible(true);

        $verification = $method->invoke($runtime, [[
            'description' => 'La vista no muestra los proyectos.',
            'supporting_evidence' => [],
            'contradicting_evidence' => [],
            'status' => 'confirmada',
            'confidence' => 0.95,
            'sources' => ['resources/views/admin/proyectos.blade.php'],
            'conclusion' => 'Coincide el nombre de la vista.',
        ]], [], []);

        $conclusion = $verification['conclusions'][0];
        $this->assertSame('evidencia insuficiente', $conclusion['status']);
        $this->assertTrue($conclusion['verification']['inference']);
        $this->assertTrue(collect($verification['missing_information'])
            ->contains(fn (string $item): bool => str_contains($item, 'evidencia directa')));
    }

    public function test_diagnostic_report_does_not_promote_possible_hypothesis_to_confirmed_cause(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildDiagnosisReport');
        $method->setAccessible(true);

        $report = $method->invoke($runtime, 'Los datos no se muestran.', 'diagnosis', [
            [
                'tool' => 'nexus.github.inspect',
                'result' => [
                    'data' => [
                        'path' => 'app/Http/Controllers/ProyectoController.php',
                        'decoded_content' => 'function store() { Proyecto::create([]); }',
                    ],
                ],
            ],
        ], [], [[
            'description' => 'La validación impide la creación.',
            'supporting_evidence' => ['Existe una validación.'],
            'contradicting_evidence' => [],
            'status' => 'posible',
            'confidence' => 0.4,
            'sources' => ['app/Http/Controllers/ProyectoController.php'],
            'conclusion' => 'Debe verificarse con una petición real.',
        ]]);

        $this->assertSame('posible', $report['certainty']);
        $this->assertSame('posible', $report['most_likely_cause']['status']);
        $this->assertStringContainsString('Debe verificarse', $report['diagnosis']);
        $this->assertContains('punto de entrada y rutas', $report['missing_evidence']);
    }

    public function test_diagnostic_response_separates_solution_proposal_from_confirmed_facts(): void
    {
        $response = app(NexusRuntime::class)->handle(new NexusRuntimeRequest(
            message: 'Los proyectos no aparecen después de crearlos. Investiga por qué.',
            projectId: 1,
        ));

        $this->assertNotEmpty($response->solution);
        $this->assertTrue($response->solution['not_confirmed']);
        $this->assertSame('propuesta', $response->solution['status']);
        $this->assertArrayHasKey('what_to_change', $response->solution);
        $this->assertArrayHasKey('where_to_change', $response->solution);
        $this->assertArrayHasKey('why', $response->solution);
        $this->assertArrayHasKey('justifying_evidence', $response->solution);
        $this->assertArrayHasKey('possible_side_effects', $response->solution);
        $this->assertArrayHasKey('verification', $response->solution);
        $this->assertStringContainsString('Solución propuesta', $response->finalMessage);
        $this->assertStringContainsString('no confirmada', strtolower($response->finalMessage));
    }

    public function test_no_concrete_solution_is_proposed_when_diagnosis_is_insufficient(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildSolutionProposal');
        $method->setAccessible(true);

        $solution = $method->invoke($runtime, 'Los datos no se muestran.', 'diagnosis', [], [], [
            'most_likely_cause' => null,
            'missing_evidence' => ['flujo de lectura'],
        ]);

        $this->assertTrue($solution['not_confirmed']);
        $this->assertSame([], $solution['what_to_change']);
        $this->assertStringContainsString('No hay una causa', $solution['why']);
        $this->assertNotEmpty($solution['verification']);
    }

    public function test_diagnostic_hypotheses_can_be_confirmed_from_structural_contradiction(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildHypotheses');
        $method->setAccessible(true);

        $hypotheses = $method->invoke($runtime, 'investiga el problema', 'diagnosis', [
            [
                'result' => [
                    'data' => [
                        'path' => 'routes/web.php',
                        'decoded_content' => "Route::get('/dashboard/proyectos', [ProyectoController::class, 'index']);",
                    ],
                ],
            ],
            [
                'result' => [
                    'data' => [
                        'path' => 'app/Http/Controllers/ProyectoController.php',
                        'decoded_content' => "function store() { \$proyecto = Proyecto::create(\$validado); return redirect()->route('dashboard'); } function index() { return Proyecto::latest()->paginate(2); }",
                    ],
                ],
            ],
        ], []);

        $discrepancy = collect($hypotheses)->firstWhere('description', 'Existe una discrepancia entre creación y consulta.');
        $this->assertSame('confirmada', $discrepancy['status']);
        $this->assertNotSame([], $discrepancy['supporting_evidence']);
        $this->assertGreaterThan(0, $discrepancy['confidence']);
    }

    public function test_diagnostic_hypotheses_can_be_discarded_with_contradicting_evidence(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildHypotheses');
        $method->setAccessible(true);

        $hypotheses = $method->invoke($runtime, 'investiga el problema', 'diagnosis', [
            [
                'result' => [
                    'data' => [
                        'path' => 'app/Http/Controllers/ProyectoController.php',
                        'decoded_content' => "function store() { \$proyecto = Proyecto::create(\$validado); return redirect()->route('proyectos.index'); } function index() { return Proyecto::latest()->get(); }",
                    ],
                ],
            ],
            [
                'result' => [
                    'data' => [
                        'path' => 'routes/web.php',
                        'decoded_content' => "Route::get('/dashboard/proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');",
                    ],
                ],
            ],
        ], []);

        $queryHypothesis = collect($hypotheses)->firstWhere('description', 'El proyecto se guarda pero no se consulta.');
        $this->assertSame('descartada', $queryHypothesis['status']);
        $this->assertNotSame([], $queryHypothesis['contradicting_evidence']);
    }

    public function test_diagnostic_hypotheses_remain_insufficient_when_required_evidence_is_missing(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildHypotheses');
        $method->setAccessible(true);

        $hypotheses = $method->invoke($runtime, 'investiga el problema', 'diagnosis', [
            [
                'result' => [
                    'data' => [
                        'path' => 'app/Http/Controllers/ProyectoController.php',
                        'decoded_content' => "function store() { \$validado = request()->validate([]); \$proyecto = Proyecto::create(\$validado); }",
                    ],
                ],
            ],
        ], []);

        $viewHypothesis = collect($hypotheses)->firstWhere('description', 'La vista no muestra los proyectos.');
        $this->assertSame('evidencia insuficiente', $viewHypothesis['status']);
        $this->assertSame([], $viewHypothesis['supporting_evidence']);
        $this->assertSame([], $viewHypothesis['contradicting_evidence']);
        $this->assertStringContainsString('No se obtuvo el contenido de la vista', $viewHypothesis['conclusion']);
    }

    public function test_relation_question_uses_specific_project_task_evidence(): void
    {
        $runtime = app(NexusRuntime::class);

        $response = $runtime->handle(new NexusRuntimeRequest(
            message: '¿Cómo se relaciona Proyecto con sus tareas?',
            projectId: 1,
        ));

        $message = strtolower($response->finalMessage);
        $this->assertSame('relation_analysis', $response->intent);
        $this->assertStringContainsString('proyecto', $message);
        $this->assertStringContainsString('tarea', $message);
        $this->assertStringContainsString('hasmany', $message);
        $this->assertStringContainsString('proyecto_id', $message);
    }

    public function test_functionality_question_explains_project_flow(): void
    {
        $runtime = app(NexusRuntime::class);

        $response = $runtime->handle(new NexusRuntimeRequest(
            message: '¿Cómo funciona actualmente la funcionalidad de proyectos?',
            projectId: 1,
        ));

        $this->assertSame('functionality_flow', $response->intent);
        $this->assertStringContainsString('GET /dashboard/proyectos', $response->finalMessage);
        $this->assertStringContainsString('POST /dashboard/proyectos', $response->finalMessage);
        $this->assertStringContainsString('Proyecto::latest()->paginate(2)', $response->finalMessage);
    }

    public function test_behavior_analysis_reconstructs_project_creation_in_temporal_order(): void
    {
        $response = app(NexusRuntime::class)->handle(new NexusRuntimeRequest(
            message: '¿Qué sucede desde que un usuario crea un proyecto hasta que ese proyecto aparece en la pantalla?',
            projectId: 1,
        ));

        $this->assertSame('behavior_analysis', $response->intent);
        $this->assertNotEmpty($response->behavior['steps']);
        $this->assertTrue($response->behavior['read_only']);
        $this->assertContains('routes/web.php', $response->behavior['sources']);
        $this->assertContains('app/Http/Controllers/ProyectoController.php', $response->behavior['sources']);
        $this->assertStringContainsString('valida', strtolower($response->finalMessage));
        $this->assertStringContainsString('persiste', strtolower($response->finalMessage));
        $this->assertStringContainsString('vista', strtolower($response->finalMessage));
        $this->assertStringNotContainsString('No se pudo confirmar: modelo', $response->finalMessage);
    }

    public function test_behavior_analysis_reports_missing_layers_instead_of_inventing_frontend_behavior(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildBehaviorAnalysis');
        $method->setAccessible(true);

        $behavior = $method->invoke($runtime, '¿Qué sucede cuando se crea un proyecto?', 'behavior_analysis', [
            [
                'result' => [
                    'data' => [
                        'path' => 'routes/web.php',
                        'decoded_content' => "Route::post('/dashboard/proyectos', [ProyectoController::class, 'store']);",
                    ],
                ],
            ],
            [
                'result' => [
                    'data' => [
                        'path' => 'app/Http/Controllers/ProyectoController.php',
                        'decoded_content' => "public function store(Request \$request) { \$data = \$request->validate([]); \$proyecto = Proyecto::create(\$data); return redirect()->route('proyectos.index'); }",
                    ],
                ],
            ],
        ], []);

        $this->assertContains('modelo o persistencia', $behavior['missing_layers']);
        $this->assertContains('vista o representación frontend', $behavior['missing_layers']);
        $this->assertStringContainsString('capas ausentes', $behavior['conclusion']);
        $this->assertNotContains('La vista recibe y representa los datos preparados por el controller.', array_column($behavior['steps'], 'action'));
    }

    public function test_behavior_analysis_can_reconstruct_non_project_integrations_from_explicit_evidence(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildBehaviorAnalysis');
        $method->setAccessible(true);

        $behavior = $method->invoke($runtime, '¿Cómo funciona la integración GitHub?', 'behavior_analysis', [
            [
                'result' => [
                    'data' => [
                        'path' => 'app/Services/NexusGithubService.php',
                        'decoded_content' => 'class NexusGithubService { public function sync() { return Http::get($url); } }',
                    ],
                ],
            ],
        ], []);

        $actions = array_column($behavior['steps'], 'action');
        $this->assertContains('La integración GitHub ejecuta la operación detectada mediante el controller o servicio inspeccionado.', $actions);
        $this->assertContains('rutas o punto de entrada', $behavior['missing_layers']);
        $this->assertTrue($behavior['read_only']);
    }

    public function test_index_method_question_describes_the_method_steps(): void
    {
        $runtime = app(NexusRuntime::class);

        $response = $runtime->handle(new NexusRuntimeRequest(
            message: '¿Qué hace ProyectoController@index paso a paso?',
            projectId: 1,
        ));

        $this->assertSame('method_analysis', $response->intent);
        $this->assertStringContainsString('Proyecto::latest()->paginate(2)', $response->finalMessage);
        $this->assertStringContainsString('admin.proyectos', $response->finalMessage);
    }

    public function test_comparison_question_compares_index_and_store(): void
    {
        $runtime = app(NexusRuntime::class);

        $response = $runtime->handle(new NexusRuntimeRequest(
            message: '¿Qué diferencia hay entre ProyectoController@index y ProyectoController@store?',
            projectId: 1,
        ));

        $this->assertSame('method_analysis', $response->intent);
        $this->assertStringContainsString('Proyecto::latest()->paginate(2)', $response->finalMessage);
        $this->assertStringContainsString('Proyecto::create', $response->finalMessage);
    }

    public function test_missing_file_question_reports_missing_file(): void
    {
        $runtime = app(NexusRuntime::class);

        $response = $runtime->handle(new NexusRuntimeRequest(
            message: '¿Qué hace app/Models/ProyectoXYZ.php?',
            projectId: 1,
        ));

        $message = strtolower($response->finalMessage);
        $this->assertSame('file_lookup', $response->intent);
        $this->assertStringContainsString('no existe', $message);
        $this->assertStringContainsString('archivo', $message);
    }

    public function test_impact_analysis_classifies_confirmed_probable_and_possible_dependencies(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildImpactAssessment');
        $method->setAccessible(true);

        $impact = $method->invoke($runtime, '¿Qué puede romperse si modifico Proyecto?', 'impact_analysis', [
            [
                'result' => [
                    'data' => [
                        'path' => 'app/Models/Proyecto.php',
                        'decoded_content' => "class Proyecto { public function tareas() { return \$this->hasMany(Tarea::class); } }",
                    ],
                ],
            ],
            [
                'result' => [
                    'data' => [
                        'architecture_map' => [
                            'components' => [
                                ['type' => 'routes', 'files' => ['routes/web.php']],
                                ['type' => 'controllers', 'files' => ['app/Http/Controllers/ProyectoController.php']],
                                ['type' => 'services', 'files' => ['app/Services/ProyectoService.php']],
                                ['type' => 'views', 'files' => ['resources/views/admin/proyectos.blade.php']],
                                ['type' => 'migrations', 'files' => ['database/migrations/2026_create_proyectos_table.php']],
                                ['type' => 'jobs', 'files' => []],
                                ['type' => 'events', 'files' => []],
                            ],
                            'edges' => [
                                [
                                    'from' => '/dashboard/proyectos',
                                    'to' => 'App\Http\Controllers\ProyectoController',
                                    'type' => 'route_to_controller',
                                    'certainty' => 'direct',
                                    'source' => 'routes/web.php',
                                ],
                                [
                                    'from' => 'app/Http/Controllers/ProyectoController.php',
                                    'to' => 'app/Services/',
                                    'type' => 'may_delegate_to',
                                    'certainty' => 'inference',
                                    'source' => 'architecture convention',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], []);

        $this->assertNotSame([], $impact['confirmed']);
        $this->assertNotSame([], $impact['probable']);
        $this->assertNotSame([], $impact['possible']);
        $this->assertContains('controller', collect($impact['confirmed'])->pluck('category')->all());
        $this->assertContains('service', collect($impact['probable'])->pluck('category')->all());
        $this->assertContains('view', collect($impact['possible'])->pluck('category')->all());
    }

    public function test_impact_analysis_does_not_claim_unverified_dependencies_as_confirmed(): void
    {
        $runtime = app(NexusRuntime::class);
        $method = new ReflectionMethod($runtime, 'buildImpactAssessment');
        $method->setAccessible(true);

        $impact = $method->invoke($runtime, '¿Qué puede romperse si modifico Proyecto?', 'impact_analysis', [
            [
                'result' => [
                    'data' => [
                        'path' => 'app/Models/Proyecto.php',
                        'decoded_content' => 'class Proyecto {}',
                    ],
                ],
            ],
        ], []);

        $confirmedComponents = collect($impact['confirmed'])->pluck('component')->all();
        $this->assertContains('app/Models/Proyecto.php', $confirmedComponents);
        $this->assertNotContains('app/Services/', $confirmedComponents);
        $this->assertNotContains('resources/views/', $confirmedComponents);
    }

    public function test_asistente_controller_routes_requests_through_runtime_gateway(): void
    {
        $controller = new AsistenteController();
        $method = new ReflectionMethod($controller, 'executeNexusChat');
        $method->setAccessible(true);
        $request = Request::create('/dashboard/asistente/mensaje', 'POST', ['message' => 'Los proyectos no aparecen después de crearlos. Investiga por qué.']);
        $request->setLaravelSession(app('session')->driver());

        $result = $method->invoke(
            $controller,
            $request,
            'Los proyectos no aparecen después de crearlos. Investiga por qué.',
            [],
            ['project_id' => null],
            app(\App\Services\NexusExecutionService::class),
            app(NexusToolRegistry::class),
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nexus', $result);
        $this->assertSame('runtime_deterministic', $result['nexus']['source']);
        $this->assertNotSame([], $result['nexus']['tools_used']);
    }
}
