<?php

namespace Tests\Unit;

use App\Http\Controllers\AsistenteController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class AsistenteRepositoryEvidenceTest extends TestCase
{
    public function test_research_progress_records_real_stages_and_isolated_tokens(): void
    {
        $controller = new AsistenteController();
        $update = new ReflectionMethod($controller, 'updateResearchProgress');
        $update->setAccessible(true);
        $read = new ReflectionMethod($controller, 'getResearchProgress');
        $read->setAccessible(true);
        $request = Request::create('/dashboard/asistente/mensaje');
        $request->setLaravelSession(app('session')->driver());

        $update->invoke($controller, $request, 'query-a', 'analyzing', 'analysis', ['intent', 'evidence'], ['analysis', 'synthesis', 'verification'], 'Analizando evidencia.');
        $update->invoke($controller, $request, 'query-b', 'completed', null, ['intent', 'evidence', 'analysis', 'synthesis', 'verification'], [], 'Investigación completada.');

        $first = $read->invoke($controller, $request, 'query-a');
        $second = $read->invoke($controller, $request, 'query-b');

        $this->assertSame('analyzing', $first['status']);
        $this->assertSame('analysis', $first['current_step']);
        $this->assertSame(40, $first['progress']);
        $this->assertSame('completed', $second['status']);
        $this->assertSame(100, $second['progress']);
        $this->assertNotSame($first['completed_steps'], $second['completed_steps']);
    }

    public function test_missing_progress_token_returns_pending_state_without_fake_steps(): void
    {
        $controller = new AsistenteController();
        $method = new ReflectionMethod($controller, 'getResearchProgress');
        $method->setAccessible(true);
        $request = Request::create('/dashboard/asistente/progreso');
        $request->setLaravelSession(app('session')->driver());

        $progress = $method->invoke($controller, $request, null);

        $this->assertSame('pending', $progress['status']);
        $this->assertSame([], $progress['completed_steps']);
        $this->assertNull($progress['current_step']);
        $this->assertSame(0, $progress['progress']);
    }

    public function test_repository_evidence_path_is_taken_from_each_current_query(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'repositoryEvidencePath');
        $method->setAccessible(true);
        $controller = new AsistenteController();

        $composer = $method->invoke(
            $controller,
            'Busca en el repositorio actual el archivo composer.json.'
        );
        $missing = $method->invoke(
            $controller,
            'Busca en el repositorio actual el archivo prueba-inexistente-nexus.json.'
        );
        $composerAgain = $method->invoke(
            $controller,
            'Busca en el repositorio actual el archivo composer.json.'
        );
        $controllerPath = $method->invoke(
            $controller,
            'Analiza completamente app/Http/Controllers/ProyectoController.php del repositorio actual.'
        );
        $modelPath = $method->invoke(
            $controller,
            'Busca el modelo Proyecto utilizado por DevControl.'
        );
        $routesPath = $method->invoke(
            $controller,
            'Busca el archivo routes/web.php del repositorio actual.'
        );

        $this->assertSame('composer.json', $composer);
        $this->assertSame('prueba-inexistente-nexus.json', $missing);
        $this->assertSame('composer.json', $composerAgain);
        $this->assertNotSame($composer, $missing);
        $this->assertSame('app/Http/Controllers/ProyectoController.php', $controllerPath);
        $this->assertSame('app/Models/Proyecto.php', $modelPath);
        $this->assertSame('routes/web.php', $routesPath);
    }

    public function test_repository_evidence_extracts_requested_file_findings_from_current_content(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'extractRepositoryFindings');
        $method->setAccessible(true);
        $controller = new AsistenteController();

        $controllerEvidence = $method->invoke(
            $controller,
            'app/Http/Controllers/ProyectoController.php',
            "<?php class ProyectoController { public function index() {} private function importarGithub() {} protected function analizarGithub() {} }"
        );
        $routesEvidence = $method->invoke(
            $controller,
            'routes/web.php',
            "<?php Route::get('/dashboard', [DashboardController::class, 'index']); Route::post('/login', [LoginController::class, 'store']);"
        );
        $composerEvidence = $method->invoke(
            $controller,
            'composer.json',
            '{"require":{"laravel/framework":"^10.10","guzzlehttp/guzzle":"^7.0"},"require-dev":{"phpunit/phpunit":"^10.0"}}'
        );

        $this->assertStringContainsString('index', $controllerEvidence);
        $this->assertStringContainsString('importarGithub', $controllerEvidence);
        $this->assertStringContainsString('analizarGithub', $controllerEvidence);
        $this->assertStringContainsString('GET /dashboard', $routesEvidence);
        $this->assertStringContainsString('POST /login', $routesEvidence);
        $this->assertStringContainsString('Laravel ^10.10', $composerEvidence);
        $this->assertStringContainsString('guzzlehttp/guzzle ^7.0', $composerEvidence);
    }

    public function test_capability_query_is_classified_as_nexus_capabilities(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'isNexusCapabilitiesInstruction');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(
            new AsistenteController(),
            'Explícame qué puedes hacer actualmente dentro de DevControl y qué capacidades todavía requieren que Nexus AI local esté habilitado.'
        ));
    }

    public function test_capabilities_report_is_derived_from_current_nexus_configuration(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'nexusCapabilitiesMessage');
        $method->setAccessible(true);
        $message = $method->invoke(new AsistenteController());

        $this->assertStringContainsString('Capacidades actuales de Nexus', $message);
        $this->assertStringContainsString('Operativas ahora:', $message);
        $this->assertStringContainsString('Estado del runtime local:', $message);
        $this->assertStringContainsString('Tool calling autónomo', $message);
    }

    public function test_php_repository_analysis_extracts_classes_methods_and_relations(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'analyzeRepositoryContent');
        $method->setAccessible(true);
        $analysis = $method->invoke(
            new AsistenteController(),
            'app/Http/Controllers/ProyectoController.php',
            "<?php\nnamespace App\\Http\\Controllers;\nuse App\\Models\\Proyecto;\nclass ProyectoController { public function index() {} public function store() {} protected function importar() {} private function validar() {} public function destroy() {} }"
        );

        $this->assertSame('PHP', $analysis['language']);
        $this->assertSame([['kind' => 'class', 'name' => 'ProyectoController']], $analysis['classes']);
        $this->assertCount(5, $analysis['methods']);
        $this->assertStringContainsString('App\\Models\\Proyecto', implode(',', $analysis['imports']));
    }

    public function test_model_discovery_is_classified_as_code_analysis(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'requiresCodeAnalysis');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(
            new AsistenteController(),
            'Busca el modelo Proyecto utilizado por DevControl e indica sus relaciones.'
        ));
    }

    public function test_requested_model_symbol_is_resolved_before_other_controller_imports(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'requestedModelClass');
        $method->setAccessible(true);

        $this->assertSame(
            'Proyecto',
            $method->invoke(
                new AsistenteController(),
                'Analiza ProyectoController.php y determina qué modelo Proyecto utiliza.'
            )
        );
    }

    public function test_compound_query_returns_independent_requested_paths(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'repositoryEvidencePaths');
        $method->setAccessible(true);

        $paths = $method->invoke(
            new AsistenteController(),
            'Primero analiza composer.json. Después analiza app/Http/Controllers/ProyectoController.php.'
        );

        $this->assertSame(
            ['composer.json', 'app/Http/Controllers/ProyectoController.php'],
            $paths
        );
    }

    public function test_three_file_query_preserves_each_path_in_order(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'repositoryEvidencePaths');
        $method->setAccessible(true);

        $paths = $method->invoke(
            new AsistenteController(),
            'Analiza composer.json, app/Http/Controllers/ProyectoController.php y el archivo prueba-nexus-analysis-404.php.'
        );

        $this->assertSame(
            [
                'composer.json',
                'app/Http/Controllers/ProyectoController.php',
                'prueba-nexus-analysis-404.php',
            ],
            $paths
        );
    }

    public function test_specific_method_analysis_only_resolves_symbols_used_in_method(): void
    {
        $controller = new AsistenteController();
        $method = new ReflectionMethod($controller, 'analyzeRepositoryMethodContent');
        $method->setAccessible(true);
        $analysis = $method->invoke(
            $controller,
            'app/Http/Controllers/ProyectoController.php',
            "<?php\nuse App\\Models\\Actualizacion;\nuse App\\Models\\Proyecto;\nclass ProyectoController { public function index() { \$proyectos = Proyecto::latest()->paginate(2); return \$proyectos; } }",
            'index'
        );

        $this->assertSame('index', $analysis['requested_method']);
        $this->assertTrue($analysis['method_found']);
        $this->assertSame(['Proyecto'], $analysis['method_symbols']['static_calls']);
        $this->assertNotContains('Actualizacion', $analysis['method_symbols']['static_calls']);
    }

    public function test_specific_method_name_is_detected_from_spanish_query(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'requestedMethodName');
        $method->setAccessible(true);

        $this->assertSame(
            'index',
            $method->invoke(
                new AsistenteController(),
                'Analiza el método index() de app/Http/Controllers/ProyectoController.php.'
            )
        );
    }

    public function test_method_relations_distinguish_used_and_unused_model_relations(): void
    {
        $controller = new AsistenteController();
        $method = new ReflectionMethod($controller, 'methodRelations');
        $method->setAccessible(true);

        $relations = $method->invoke(
            $controller,
            "\$relaciones = ['secciones', 'integracionGithub']; \$proyecto->load(\$relaciones); \$proyecto->tareas; \$proyecto->bugs;"
        );

        $this->assertSame(
            ['secciones', 'integracionGithub', 'tareas', 'bugs'],
            $relations
        );

        $definitions = new ReflectionMethod($controller, 'relationDefinitions');
        $definitions->setAccessible(true);
        $modelRelations = $definitions->invoke(
            $controller,
            "<?php class Proyecto { public function tareas() { return \$this->hasMany(Tarea::class); } public function incidentes() { return \$this->hasMany(Incidente::class); } }"
        );
        $this->assertArrayHasKey('tareas', $modelRelations);
        $this->assertArrayHasKey('incidentes', $modelRelations);
        $this->assertSame(
            ['incidentes'],
            array_values(array_diff(array_keys($modelRelations), $relations))
        );
    }

    public function test_narrative_synthesis_reconstructs_project_flow_from_evidence(): void
    {
        $controller = new AsistenteController();
        $method = new ReflectionMethod($controller, 'synthesizeRepositoryExplanation');
        $method->setAccessible(true);
        $answer = $method->invoke($controller, 'Explica cómo funciona la funcionalidad de proyectos.', [
            [
                'path' => 'routes/web.php',
                'content' => "Route::middleware(['auth', 'role:admin'])->group(function () { Route::get('/dashboard/proyectos', [ProyectoController::class, 'index']); });",
            ],
            [
                'path' => 'app/Http/Controllers/ProyectoController.php',
                'content' => "<?php function index() { \$proyectos = Proyecto::latest()->paginate(2); \$proyecto = \$proyectos->first(); \$relaciones = ['secciones', 'integracionGithub']; \$proyecto->load(\$relaciones); if (Schema::hasTable('tareas')) {} \$estadisticas = \$this->obtenerEstadisticas(\$proyecto); return view('admin.proyectos', compact('proyectos', 'proyecto', 'estadisticas')); }",
            ],
        ]);

        $this->assertStringContainsString('GET /dashboard/proyectos', $answer);
        $this->assertStringContainsString('ProyectoController@index', $answer);
        $this->assertStringContainsString('Proyecto::latest()->paginate(2)', $answer);
        $this->assertStringContainsString('admin.proyectos', $answer);
        $this->assertStringContainsString('Hechos confirmados por código', $answer);
        $this->assertStringNotContainsString('use App\\Models\\', $answer);
    }

    public function test_narrative_synthesis_reports_insufficient_evidence(): void
    {
        $controller = new AsistenteController();
        $method = new ReflectionMethod($controller, 'synthesizeRepositoryExplanation');
        $method->setAccessible(true);

        $answer = $method->invoke(
            $controller,
            'Explica cómo funciona un flujo no documentado.',
            [['path' => 'README.md', 'content' => '# DevControl']]
        );

        $this->assertStringContainsString('No hay evidencia suficiente', $answer);
    }

    public function test_narrative_synthesis_explains_methods_and_keeps_source_traceability(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'synthesizeRepositoryExplanation');
        $method->setAccessible(true);
        $answer = $method->invoke(new AsistenteController(), 'Explica qué hace el método index().', [
            [
                'path' => 'app/Http/Controllers/ProyectoController.php',
                'content' => "<?php class ProyectoController { public function index() { \$proyectos = Proyecto::latest()->paginate(2); return view('admin.proyectos', compact('proyectos')); } }",
            ],
        ]);

        $this->assertStringContainsString('index()', $answer);
        $this->assertStringContainsString('Proyecto::latest()->paginate(2)', $answer);
        $this->assertStringContainsString('admin.proyectos', $answer);
        $this->assertStringContainsString('Fuentes utilizadas:', $answer);
        $this->assertStringNotContainsString('Imports:', $answer);
    }

    public function test_narrative_synthesis_explains_relationship_definitions_without_listing_raw_code(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'synthesizeRepositoryExplanation');
        $method->setAccessible(true);
        $answer = $method->invoke(new AsistenteController(), 'Explica cómo se relaciona Proyecto con sus tareas.', [
            [
                'path' => 'app/Models/Proyecto.php',
                'content' => "<?php\nclass Proyecto extends Model\n{\n    public function tareas()\n    {\n        return \$this->hasMany(Tarea::class);\n    }\n\n    public function incidentes()\n    {\n        return \$this->hasMany(Incidente::class);\n    }\n}",
            ],
        ]);

        $this->assertStringContainsString('tareas (hasMany hacia Tarea)', $answer);
        $this->assertStringContainsString('La declaración confirma cómo se modelan', $answer);
        $this->assertStringNotContainsString('public function tareas()', $answer);
    }

    public function test_narrative_queries_are_detected_for_flow_method_and_relationship_questions(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'isNarrativeEvidenceQuery');
        $method->setAccessible(true);
        $controller = new AsistenteController();

        foreach ([
            'Explica cómo funciona la funcionalidad de proyectos.',
            'Explica qué hace index() y por qué.',
            '¿Cómo se relacionan ProyectoController y Proyecto?',
            '¿Qué ocurre cuando se accede a /dashboard/proyectos?',
        ] as $query) {
            $this->assertTrue($method->invoke($controller, $query), $query);
        }
    }

    public function test_project_specific_questions_require_repository_evidence_but_general_question_does_not(): void
    {
        $controller = new AsistenteController();
        $method = new ReflectionMethod($controller, 'requiresRepositoryEvidence');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($controller, '¿Qué hace DevControl?'));
        $this->assertTrue($method->invoke($controller, '¿Cómo funciona la creación de proyectos?'));
        $this->assertTrue($method->invoke($controller, '¿Por qué podría fallar la creación de proyectos?'));
        $this->assertTrue($method->invoke($controller, 'Los proyectos no aparecen después de crearlos. Investiga por qué.'));
    }

    public function test_repository_query_intent_prioritizes_diagnostic_over_narrative(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'repositoryQueryIntent');
        $method->setAccessible(true);

        $this->assertSame(
            'diagnostic',
            $method->invoke(
                new AsistenteController(),
                'Los proyectos no aparecen después de crearlos. Investiga por qué.'
            )
        );
        $this->assertSame(
            'repository_explanation',
            $method->invoke(new AsistenteController(), '¿Cómo funciona la creación de proyectos?')
        );
    }

    public function test_repository_query_intent_selects_strategy_from_user_goal(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'repositoryQueryIntent');
        $method->setAccessible(true);
        $controller = new AsistenteController();

        $cases = [
            '¿Qué hace DevControl?' => 'general_conversation',
            '¿Cómo funciona la creación de proyectos?' => 'repository_explanation',
            '¿Qué hace ProyectoController@index?' => 'code_analysis',
            '¿Qué cambiaría si modifico Proyecto?' => 'impact_analysis',
            '¿Qué diferencia hay entre crear y actualizar un proyecto?' => 'comparison',
            '¿Cómo solucionarías que no aparezcan los proyectos?' => 'solution_proposal',
            'Planifica los pasos para revisar la creación de proyectos.' => 'planning',
            'Ejecuta la corrección propuesta para los proyectos.' => 'execution',
            'Lee exactamente el contenido de routes/web.php.' => 'evidence_extraction',
        ];

        foreach ($cases as $query => $intent) {
            $this->assertSame($intent, $method->invoke($controller, $query), $query);
        }
    }

    public function test_equivalent_method_questions_share_structured_intent_and_evidence_plan(): void
    {
            $intent = new ReflectionMethod(AsistenteController::class, 'repositoryIntent');
            $intent->setAccessible(true);
            $paths = new ReflectionMethod(AsistenteController::class, 'repositoryEvidencePaths');
            $paths->setAccessible(true);
            $controller = new AsistenteController();
            $queries = [
                'Explícame index de proyectos',
                '¿Para qué sirve ProyectoController@index?',
                '¿Qué hace ProyectoController@index?',
                'Explícame el método index()',
                '¿Cuál es la función de ProyectoController@index?',
            ];

            foreach ($queries as $query) {
                $representation = $intent->invoke($controller, $query);
                $this->assertSame('method_analysis', $representation['intent'], $query);
                $this->assertSame('method', $representation['target_type'], $query);
                $this->assertSame('ProyectoController@index', $representation['target'], $query);
                $this->assertSame('narrative', $representation['response_mode'], $query);
                $this->assertSame(['app/Http/Controllers/ProyectoController.php'], $paths->invoke($controller, $query), $query);
            }
    }

    public function test_relation_and_functionality_intents_plan_different_sources(): void
    {
            $intent = new ReflectionMethod(AsistenteController::class, 'repositoryIntent');
            $intent->setAccessible(true);
            $paths = new ReflectionMethod(AsistenteController::class, 'repositoryEvidencePaths');
            $paths->setAccessible(true);
            $controller = new AsistenteController();

            $relation = $intent->invoke($controller, '¿Cómo se relaciona Proyecto con sus tareas?');
            $this->assertSame('relation_analysis', $relation['intent']);
            $this->assertSame(['app/Models/Proyecto.php'], $paths->invoke($controller, '¿Cómo se relaciona Proyecto con sus tareas?'));

            $flow = $intent->invoke($controller, '¿Cómo funciona la funcionalidad de proyectos?');
            $this->assertSame('functionality_flow', $flow['intent']);
            $this->assertSame(['routes/web.php', 'app/Http/Controllers/ProyectoController.php'], $paths->invoke($controller, '¿Cómo funciona la funcionalidad de proyectos?'));
    }

    public function test_diagnostic_queries_do_not_enter_narrative_strategy(): void
    {
        $intent = new ReflectionMethod(AsistenteController::class, 'repositoryQueryIntent');
        $intent->setAccessible(true);
        $narrative = new ReflectionMethod(AsistenteController::class, 'isNarrativeEvidenceQuery');
        $narrative->setAccessible(true);
        $controller = new AsistenteController();

        $query = 'Los proyectos no aparecen después de crearlos. Investiga por qué.';

        $this->assertSame('diagnostic', $intent->invoke($controller, $query));
        $this->assertFalse($narrative->invoke($controller, $query));
    }

    public function test_creation_synthesis_uses_real_store_flow_and_not_generic_nexus_text(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'synthesizeRepositoryExplanation');
        $method->setAccessible(true);
        $answer = $method->invoke(new AsistenteController(), '¿Cómo funciona la creación de proyectos?', [
            [
                'path' => 'routes/web.php',
                'content' => "Route::post('/dashboard/proyectos', [ProyectoController::class, 'store'])->name('proyectos.store');",
            ],
            [
                'path' => 'app/Http/Controllers/ProyectoController.php',
                'content' => "<?php function store(Request \$request) { \$validado = \$request->validate(['nombre' => ['required']]); DB::transaction(function () use (\$validado) { \$proyecto = Proyecto::create(\$validado); }); return redirect()->route('proyectos.index')->with('success', 'ok'); }",
            ],
        ]);

        $this->assertStringContainsString('valida los datos', $answer);
        $this->assertStringContainsString('Proyecto::create($validado)', $answer);
        $this->assertStringContainsString('proyectos.index', $answer);
        $this->assertStringNotContainsString('Interpreto lo que escribes', $answer);
    }

    public function test_diagnostic_synthesis_separates_evidence_from_unconfirmed_hypotheses(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'synthesizeRepositoryExplanation');
        $method->setAccessible(true);
        $answer = $method->invoke(new AsistenteController(), 'Los proyectos no aparecen después de crearlos. Investiga por qué.', [
            [
                'path' => 'app/Http/Controllers/ProyectoController.php',
                'content' => "<?php function store(Request \$request) { \$validado = \$request->validate([]); \$proyecto = Proyecto::create(\$validado); return redirect()->route('proyectos.index'); }",
            ],
        ]);

        $this->assertStringContainsString('Diagnóstico:', $answer);
        $this->assertStringContainsString('Hipótesis no confirmada:', $answer);
        $this->assertStringContainsString('no demuestra por sí sola un fallo', $answer);
    }

    public function test_evidence_graph_links_multiple_files_with_support_and_confidence(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'buildEvidenceGraph');
        $method->setAccessible(true);
        $graph = $method->invoke(new AsistenteController(), 'Explica el flujo de proyectos.', [
            [
                'path' => 'routes/web.php',
                'content' => "Route::get('/dashboard/proyectos', [ProyectoController::class, 'index']);",
            ],
            [
                'path' => 'app/Http/Controllers/ProyectoController.php',
                'content' => "<?php function index() { \$proyectos = Proyecto::latest()->paginate(2); }",
            ],
        ]);

        $this->assertSame(['routes/web.php', 'app/Http/Controllers/ProyectoController.php'], $graph['files']);
        $this->assertNotEmpty($graph['facts']);
        $this->assertNotEmpty($graph['relationships']);
        $this->assertSame('routes/web.php', $graph['supporting_evidence']['ProyectoController@index'][0]);
        $this->assertGreaterThan(0.8, $graph['confidence']);
        $this->assertSame('Explica el flujo de proyectos.', $graph['query']);
    }

    public function test_evidence_graph_reports_contradictory_route_destinations(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'buildEvidenceGraph');
        $method->setAccessible(true);
        $graph = $method->invoke(new AsistenteController(), 'Analiza rutas conflictivas.', [
            [
                'path' => 'routes/web.php',
                'content' => "Route::get('/dashboard/proyectos', [ProyectoController::class, 'index']); Route::get('/dashboard/proyectos', [OtroController::class, 'index']);",
            ],
        ]);

        $this->assertCount(1, $graph['contradicting_evidence']);
        $this->assertSame(0.4, $graph['confidence']);
    }

    public function test_evidence_graph_marks_missing_evidence_without_inventing_relationships(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'buildEvidenceGraph');
        $method->setAccessible(true);
        $graph = $method->invoke(new AsistenteController(), 'Explica un flujo inexistente.', [
            ['path' => 'README.md', 'content' => '# DevControl'],
        ]);

        $this->assertSame([], $graph['facts']);
        $this->assertSame([], $graph['relationships']);
        $this->assertNotEmpty($graph['inferences']);
        $this->assertSame(0.1, $graph['confidence']);
    }

    public function test_evidence_graph_isolated_between_queries(): void
    {
        $method = new ReflectionMethod(AsistenteController::class, 'buildEvidenceGraph');
        $method->setAccessible(true);
        $controller = new AsistenteController();
        $first = $method->invoke($controller, 'Consulta uno.', [
            ['path' => 'routes/web.php', 'content' => "Route::get('/uno', [UnoController::class, 'index']);"],
        ]);
        $second = $method->invoke($controller, 'Consulta dos.', [
            ['path' => 'routes/web.php', 'content' => "Route::get('/dos', [DosController::class, 'index']);"],
        ]);

        $this->assertStringContainsString('/uno', json_encode($first, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('/uno', json_encode($second, JSON_THROW_ON_ERROR));
        $this->assertStringContainsString('/dos', json_encode($second, JSON_THROW_ON_ERROR));
    }
}
