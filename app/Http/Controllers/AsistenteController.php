<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\Incidente;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Models\User;
use App\Http\Controllers\ProyectoController;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use App\Services\NexusAuditService;
use App\Services\NexusReasoningService;
use App\Services\NexusMemoryService;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;

class AsistenteController extends Controller
{
    private const EXPECTED_SECTIONS = [
        'index' => 'Dashboard / resumen general',
        'proyectos' => 'Gestión y configuración de proyectos',
        'tareas' => 'Pendientes, progreso y completadas',
        'bugs' => 'Errores e incidentes',
        'actualizaciones' => 'Commits, cambios y despliegues',
        'monitoreo' => 'Estado de servidores, aplicaciones, servicios, CPU, RAM y disco',
        'incidentes' => 'Agrupar errores relacionados y darles seguimiento',
        'notificaciones' => 'Alertas de bugs, caídas, despliegues y tareas',
        'ia analisis' => 'Análisis de código, recomendaciones y diagnósticos',
        'archivos' => 'Proyectos terminados, respaldos, PDFs y documentación',
        'configuracion' => 'GitHub, conexiones, notificaciones, IA y preferencias',
        'usuarios' => 'Administración de usuarios y actividad general',
        'actividad' => 'Registro de acciones de usuarios y DevControl',
    ];

    private const MODULES = [
        'dashboard' => [
            'label' => 'Dashboard',
            'route' => 'dashboard',
            'ready' => true,
        ],
        'proyectos' => [
            'label' => 'Proyectos',
            'route' => 'proyectos.index',
            'ready' => true,
        ],
        'tareas' => [
            'label' => 'Tareas',
            'route' => 'tareas.index',
            'ready' => true,
        ],
        'bugs' => [
            'label' => 'Bugs',
            'route' => 'bugs.index',
            'ready' => true,
        ],
        'actualizaciones' => [
            'label' => 'Actualizaciones',
            'route' => 'actualizaciones',
            'ready' => true,
        ],
        'ia analisis' => [
            'label' => 'IA / Análisis',
            'route' => 'asistente.index',
            'ready' => true,
        ],
        'usuarios' => [
            'label' => 'Usuarios',
            'route' => 'usuarios',
            'ready' => true,
        ],
        'archivos' => [
            'label' => 'Archivos',
            'route' => 'archivos',
            'ready' => false,
            'reason' => 'La sección todavía no tiene almacenamiento ni operaciones de archivos conectadas.',
        ],
        'monitoreo' => [
            'label' => 'Monitoreo',
            'route' => 'monitoreo',
            'ready' => true,
        ],
        'incidentes' => [
            'label' => 'Incidentes',
            'route' => 'incidentes',
            'ready' => true,
        ],
        'notificaciones' => [
            'label' => 'Notificaciones',
            'route' => 'notificaciones',
            'ready' => false,
            'reason' => 'La sección todavía no tiene alertas persistentes conectadas.',
        ],
        'configuracion' => [
            'label' => 'Configuración',
            'route' => 'configuracion',
            'ready' => true,
        ],
        'actividad' => [
            'label' => 'Actividad',
            'route' => 'actividad',
            'ready' => true,
        ],
        'seguimiento' => [
            'label' => 'Seguimiento',
            'route' => 'seguimiento',
            'ready' => false,
            'reason' => 'La sección todavía no tiene un flujo funcional implementado.',
        ],
        'notas' => [
            'label' => 'Notas',
            'route' => 'notas',
            'ready' => false,
            'reason' => 'La sección todavía no tiene persistencia ni operaciones de notas conectadas.',
        ],
    ];

    public function index(Request $request)
    {
        $messages = $request->session()->get('assistant_messages', []);

        if (count($messages) === 0) {
            $messages[] = [
                'role' => 'assistant',
                'content' => 'Soy Nexus, el asistente inteligente de DevControl. Puedo conversar contigo, explicarte qué es la plataforma, llevarte a sus módulos, revisar el estado del proyecto y ayudarte a detectar problemas. Puedes decirme lo que necesitas con tus propias palabras.',
            ];
        }

        return view('admin.asistente', [
            'messages' => $messages,
            'modules' => $this->moduleStatus(),
        ]);
    }

    public function message(Request $request, NexusReasoningService $reasoning, NexusMemoryService $memory)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:20000'],
        ]);

        $messages = $request->session()->get('assistant_messages', []);
        $messages[] = ['role' => 'user', 'content' => $validated['message']];
        $conversation = $memory->conversation($request->session()->getId(), $request->user()?->id);
        $memory->recordMessage($conversation, 'user', $validated['message'], ['source' => 'legacy_chat']);
        $reasoningResult = $reasoning->reason(
            $validated['message'],
            $memory->relevantContext($conversation, $validated['message'], [
                'route' => $request->route()?->getName(),
                'user_role' => $request->user()?->rol,
                'pending_action' => $request->session()->get('assistant_pending_action'),
                'modules' => $this->moduleStatus(),
            ])
        );

        $response = $this->processInstruction(
            $validated['message'],
            $messages,
            $request->session()->get('assistant_pending_action')
        );
        $messages[] = $response['message'];
        $memory->recordMessage($conversation, 'assistant', $response['message']['content'], ['source' => 'legacy_chat']);
        $request->session()->put('assistant_messages', array_slice($messages, -20));

        if (array_key_exists('pending_action', $response)) {
            if ($response['pending_action'] === null) {
                $request->session()->forget('assistant_pending_action');
            } else {
                $request->session()->put('assistant_pending_action', $response['pending_action']);
            }
        }

        return response()->json([
            'message' => $response['message'],
            'navigation' => $response['navigation'],
            'reasoning' => $reasoningResult->toArray(),
        ]);
    }

    public function clear(Request $request, \App\Services\NexusMemoryService $memory)
    {
        $request->session()->forget('assistant_messages');
        $conversation = $memory->conversation($request->session()->getId(), $request->user()?->id);
        $memory->clearConversation($conversation);

        return response()->json(['ok' => true]);
    }

    private function processInstruction(string $input, array $history = [], ?array $pendingAction = null): array
    {
        $text = $this->normalizeInstruction($input);

        if ($pendingAction) {
            $confirmation = $this->resolvePendingAction($text, $pendingAction);

            if ($confirmation) {
                return $confirmation;
            }
        }

        if ($this->isTaskSynchronizationInstruction($text)) {
            return $this->synchronizeProjectTasks();
        }

        if ($this->isGithubCommitInstruction($text)) {
            return $this->requestGithubCommitAuthorization($input);
        }

        if ($this->isProjectStructureUpdateInstruction($text)) {
            return $this->requestProjectStructureUpdateAuthorization($input);
        }

        if ($this->isProjectCreationInstruction($text)) {
            return $this->requestProjectCreationAuthorization($input);
        }

        if ($followUp = $this->resolveFollowUpInstruction($text, $history)) {
            return $followUp;
        }

        if ($this->isTaskUpdateInstruction($text)) {
            return $this->synchronizeProjectTasks();
        }

        if ($this->isCasualConversation($text)) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => $this->conversationResponse($text, $history),
                ],
                'navigation' => null,
            ];
        }

        if ($this->isHelpInstruction($text)) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => $this->assistantCapabilitiesMessage(),
                ],
                'navigation' => null,
            ];
        }

        if ($this->isMutationInstruction($text)) {
            return $this->requestMutationAuthorization($text);
        }

        if ($this->isIncidentCreationInstruction($text)) {
            return $this->requestIncidentAuthorization($text);
        }

        if ($this->isCodeProposalApplyInstruction($text)) {
            return $this->requestCodeProposalAuthorization($text);
        }

        if ($this->isPendingTaskInstruction($text)) {
            return $this->pendingTasksMessage($text);
        }

        if ($this->isImprovementAnalysisInstruction($text)) {
            return $this->improvementAnalysisMessage($text);
        }

        if ($this->isSecurityAnalysisInstruction($text)) {
            return $this->securityAnalysisMessage($text);
        }

        if ($this->isNexusQuery($text)) {
            return $this->nexusQueryMessage($text);
        }

        if ($this->isDeepProjectAuditInstruction($text)) {
            return $this->deepProjectAuditMessage();
        }

        if ($this->isAutomatedProjectTaskInstruction($text)) {
            return $this->scanProjectForBugs();
        }

        if ($this->isTaskCreationInstruction($text)) {
            return $this->createTaskFromInstruction($input);
        }

        if ($this->isSectionAuditInstruction($text)) {
            return $this->auditProjectSections();
        }

        if ($this->isProjectScanInstruction($text)) {
            return $this->scanProjectForBugs();
        }

        if ($this->isBugDetailInstruction($text)) {
            return $this->detailGeneratedBugs();
        }

        if ($this->isDataSummaryInstruction($text)) {
            return $this->dataSummaryMessage($text);
        }

        if ($module = $this->requestedModule($text)) {
            return $this->navigateTo($module);
        }

        if ($this->isDiagnosticInstruction($text)) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => $this->diagnosticMessage(),
                ],
                'navigation' => null,
            ];
        }

        return [
            'message' => [
                'role' => 'assistant',
                'content' => $this->unrecognizedInstructionMessage($input),
            ],
            'navigation' => null,
        ];
    }

    private function isCasualConversation(string $text): bool
    {
        return $this->isGreetingInstruction($text)
            || $this->hasApproximatePhrase($text, [
                'gracias', 'muchas gracias', 'perfecto', 'excelente', 'genial',
                'ok', 'okay', 'va', 'sale', 'entendido', 'de acuerdo',
                'que opinas', 'como funciona', 'por que', 'quien eres', 'eres una ia',
                'que es devcontrol', 'que es dev control', 'para que sirve devcontrol',
                'para que sirve dev control', 'que hace devcontrol',
            ]);
    }

    private function conversationResponse(string $text, array $history): string
    {
        if ($this->hasApproximateTerm($text, ['gracias'])) {
            return $this->naturalResponse([
                '¡De nada! Aquí estoy para ayudarte a mantener DevControl organizado. ¿Qué revisamos ahora?',
                'Con gusto. Seguimos cuando quieras; puedo ayudarte con proyectos, tareas, bugs o cualquier otro módulo.',
                'No hay de qué. Dime qué te gustaría revisar y lo vemos juntos.',
            ], $history);
        }

        if ($this->hasApproximateTerm($text, ['perfecto', 'excelente', 'genial', 'entendido', 'acuerdo', 'ok', 'okay', 'va', 'sale'])) {
            return $this->naturalResponse([
                'Perfecto, seguimos avanzando. Puedo revisar el sistema, navegar a un módulo o analizar lo que falte.',
                'Va, entendido. Estoy listo para el siguiente paso. ¿Qué quieres que revisemos?',
                'Excelente. Ya tenemos eso claro; podemos continuar con bugs, tareas, usuarios o cualquier sección.',
            ], $history);
        }

        if ($this->hasApproximatePhrase($text, ['quien eres', 'eres una ia'])) {
            return $this->naturalResponse([
                'Soy Nexus, el asistente inteligente de DevControl. Estoy aquí para entender lo que necesitas, consultar la información disponible y ayudarte a organizar tu proyecto.',
                'Me llamo Nexus. Soy el asistente de DevControl y puedo conversar contigo, revisar el estado del proyecto y guiarte por sus módulos.',
                'Soy Nexus, una IA integrada en DevControl. Mi trabajo es ayudarte a entender el sistema y actuar de forma segura sobre la información disponible.',
            ], $history);
        }

        if ($this->hasApproximatePhrase($text, ['que es devcontrol', 'que es dev control', 'para que sirve devcontrol', 'para que sirve dev control', 'que hace devcontrol'])) {
            return $this->naturalResponse([
                'DevControl es una plataforma para mantener bajo control tus proyectos de software: aquí puedes organizar tareas, bugs, actualizaciones, usuarios, incidentes y actividad.',
                'DevControl funciona como un centro de control para tus proyectos. Reúne la información importante y permite darle seguimiento a lo que está pendiente o necesita atención.',
                'Es la plataforma donde centralizas el seguimiento de tus proyectos. Yo, como Nexus, te ayudo a consultar esa información y encontrar rápidamente lo que buscas.',
            ], $history);
        }

        if ($this->hasApproximatePhrase($text, ['que puedes hacer', 'que sabes hacer', 'como me ayudas', 'para que estas'])) {
            return $this->naturalResponse([
                'Puedo revisar el estado de DevControl, consultar usuarios, proyectos, tareas y bugs, analizar problemas y llevarte al módulo que necesites.',
                'Puedo ayudarte a navegar, revisar pendientes, detectar bugs, auditar secciones y explicar lo que encuentre. También puedo continuar una conversación si dices “esos” o “detállalos”.',
                'Estoy para ayudarte a entender y organizar el proyecto: puedo consultar datos, revisar problemas y acompañarte paso a paso sin que tengas que usar comandos exactos.',
            ], $history);
        }

        if ($this->hasApproximatePhrase($text, ['como funciona', 'por que'])) {
            return $this->naturalResponse([
                'Interpreto lo que escribes, reviso el contexto de DevControl y ejecuto solamente las acciones permitidas. Si algo cambia información, primero te explico qué haré.',
                'Trabajo por intenciones: no necesitas memorizar comandos. Entiendo tu mensaje, consulto los datos disponibles y te respondo o te llevo al módulo correspondiente.',
                'Combino lenguaje natural con reglas de seguridad. Así puedo conversar contigo sin inventar resultados ni modificar información delicada sin avisarte.',
            ], $history);
        }

        if ($this->isGreetingInstruction($text)) {
            return $this->naturalResponse([
                '¡Hola! Qué gusto verte por aquí. Soy Nexus y estoy listo para ayudarte con DevControl. ¿Qué revisamos primero?',
                '¡Hola! Aquí está Nexus. Podemos revisar el sistema, bugs, tareas o cualquier módulo que necesites.',
                '¡Qué tal! Dime qué tienes en mente y trataré de ayudarte de la forma más directa posible.',
            ], $history);
        }

        return $this->naturalResponse([
            'Claro, estoy contigo. Dime qué quieres revisar o hacer y lo interpretamos juntos.',
            'Te escucho. Puedes explicármelo con tus propias palabras; intentaré entender la intención.',
            'Entendido. Cuéntame un poco más y buscaré la manera más cercana de ayudarte.',
        ], $history);
    }

    private function naturalResponse(array $responses, array $history): string
    {
        $lastAssistantMessage = collect($history)
            ->reverse()
            ->first(fn (array $message) => ($message['role'] ?? null) === 'assistant');
        $lastAssistantResponse = $lastAssistantMessage['content'] ?? null;
        $available = collect($responses)
            ->reject(fn (string $response) => $response === $lastAssistantResponse)
            ->values()
            ->all();

        return $available[random_int(0, count($available) - 1)];
    }

    private function resolveFollowUpInstruction(string $text, array $history): ?array
    {
        $lastAssistantMessage = collect($history)
            ->reverse()
            ->first(fn (array $message) => ($message['role'] ?? null) === 'assistant');

        if (
            $lastAssistantMessage
            && (
                str_contains($lastAssistantMessage['content'] ?? '', '¿De qué proyecto')
                || str_contains($lastAssistantMessage['content'] ?? '', 'áreas de mejora')
            )
        ) {
            $isGeneralChoice = $this->hasApproximatePhrase($text, [
                'todos',
                'todas',
                'general',
                'en general',
                'todos los proyectos',
                'todos mis proyectos',
            ]);
            $isProjectChoice = Schema::hasTable('proyectos') && Proyecto::query()
                ->pluck('nombre')
                ->contains(function (string $name) use ($text) {
                    $normalizedName = $this->normalizeInstruction($name);

                    return $normalizedName !== ''
                        && ($normalizedName === $text || str_contains($text, $normalizedName));
                });

            if ($isGeneralChoice || $isProjectChoice) {
                if (str_contains($lastAssistantMessage['content'] ?? '', 'áreas de mejora')) {
                    return $this->improvementAnalysisMessage($text);
                }

                return $this->pendingTasksMessage($text, true);
            }
        }

        $previousUserMessage = collect($history)
            ->reverse()
            ->first(fn (array $message) => ($message['role'] ?? null) === 'user');

        if (! $previousUserMessage) {
            return null;
        }

        $previousText = $this->normalizeInstruction($previousUserMessage['content'] ?? '');
        $refersToPreviousResult = $this->hasApproximatePhrase($text, [
            'esos', 'esos bugs', 'los anteriores', 'lo anterior', 'ahora',
            'tambien', 'y esos', 'continua', 'siguiente',
        ]);

        if (! $refersToPreviousResult) {
            return null;
        }

        if ($this->isBugDetailInstruction($text) || $this->hasApproximateTerm($text, ['detalla', 'explica', 'descripcion'])) {
            if ($this->hasApproximateTerm($previousText, ['bug', 'error', 'falla', 'problema'])) {
                return $this->detailGeneratedBugs();
            }
        }

        if ($this->hasApproximateTerm($previousText, ['bug', 'error', 'falla', 'problema'])) {
            return $this->scanProjectForBugs();
        }

        if ($this->hasApproximateTerm($previousText, ['seccion', 'modulo', 'apartado', 'estructura'])) {
            return $this->auditProjectSections();
        }

        return null;
    }

    private function isDataSummaryInstruction(string $text): bool
    {
        return $this->hasApproximatePhrase($text, [
            'cuantos usuarios',
            'lista de usuarios',
            'que usuarios hay',
            'cuantos proyectos',
            'que proyectos hay',
            'cuantas tareas',
            'cuantos bugs',
            'resumen de registros',
            'resumen del sistema',
            'datos del sistema',
        ]);
    }

    private function isNexusQuery(string $text): bool
    {
        return $this->hasApproximatePhrase($text, [
            'hallazgos', 'auditoria completa', 'salud de nexus', 'estado de salud',
            'resumen por proyecto', 'resumen de hallazgos', 'problemas detectados',
            'propuestas de cambio', 'propuestas de solucion', 'como lo arreglo',
        ]);
    }

    private function nexusQueryMessage(string $text): array
    {
        try {
            $service = app(NexusAuditService::class);
            if ($this->hasApproximatePhrase($text, ['salud', 'estado de salud'])) {
                $health = $service->health();
                $content = "Salud de Nexus: {$health['status']}\n\nHallazgos activos: {$health['hallazgos_activos']}";
            } elseif ($this->hasApproximatePhrase($text, ['propuestas', 'propuesta', 'como lo arreglo'])) {
                $proposals = $service->proposals();

                if ($proposals->isEmpty()) {
                    $content = 'No hay hallazgos activos para preparar propuestas.';
                } else {
                    $content = "Propuestas locales de Nexus (sin aplicar cambios)\n\n".$proposals
                        ->map(function (array $proposal, int $index): string {
                            $files = $proposal['archivos'] === []
                                ? 'Sin archivos de código definidos'
                                : implode(', ', $proposal['archivos']);
                            $diff = collect($proposal['diff'])->map(function (array $change): string {
                                if (($change['estado'] ?? null) === 'sin cambios') {
                                    return "Diff: {$change['archivo']} ya cumple la recomendación.";
                                }

                                return "Diff en {$change['archivo']}:\n".
                                    "--- actual ---\n".($change['actual'] ?? '(vacío)')."\n".
                                    "+++ propuesto ---\n".($change['nuevo'] ?? '(vacío)');
                            })->implode("\n\n");

                            return ($index + 1).". [{$proposal['severidad']}] {$proposal['titulo']}\n".
                                "Acción: {$proposal['accion']}\n".
                                "Archivos: {$files}\n".
                                "Propuesta: {$proposal['cambios']}\n".
                                ($diff !== '' ? "{$diff}\n" : 'Diff: no hay un cambio de código determinista para este hallazgo todavía.'."\n").
                                'Estado: requiere confirmación antes de modificar algo.';
                        })->implode("\n\n");
                }
            } else {
                $findings = $service->findings();
                if ($findings->isEmpty()) {
                    $content = 'No hay hallazgos activos persistidos. Ejecuta `php artisan nexus:scan` para actualizar la auditoría local.';
                } else {
                    $grouped = $findings->groupBy(fn ($finding) => $finding->proyecto?->nombre ?? 'Sistema');
                    $content = "Hallazgos activos: {$findings->count()}\n\n".$grouped->map(
                        fn ($items, $project) => "{$project}: {$items->count()} (". $items->pluck('severidad')->countBy()->map(fn ($n, $s) => "{$s}: {$n}")->implode(', ').')'
                    )->implode("\n");
                }
            }
            return $this->assistantResponse($content);
        } catch (\Throwable $exception) {
            report($exception);
            return $this->assistantResponse('No puedo consultar Nexus ahora. Verifica que la migración de hallazgos esté instalada y que la base de datos esté disponible.');
        }
    }

    private function dataSummaryMessage(string $text): array
    {
        $lines = [];

        if (Schema::hasTable('users') && $this->hasApproximateTerm($text, ['usuario', 'usuarios', 'sistema', 'registro'])) {
            $lines[] = 'Usuarios registrados: '.User::count();
        }
        if (Schema::hasTable('proyectos') && $this->hasApproximateTerm($text, ['proyecto', 'proyectos', 'sistema', 'registro'])) {
            $lines[] = 'Proyectos: '.Proyecto::count();
        }
        if (Schema::hasTable('tareas') && $this->hasApproximateTerm($text, ['tarea', 'tareas', 'sistema', 'registro'])) {
            $lines[] = 'Tareas: '.Tarea::count();
        }
        if (Schema::hasTable('bugs') && $this->hasApproximateTerm($text, ['bug', 'bugs', 'error', 'sistema', 'registro'])) {
            $lines[] = 'Bugs: '.Bug::count();
        }

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Esto es lo que encontré en la información disponible:\n\n".implode("\n", $lines),
            ],
            'navigation' => $this->hasApproximateTerm($text, ['usuario', 'usuarios']) ? route('usuarios') : null,
        ];
    }

    private function isPendingTaskInstruction(string $text): bool
    {
        $hasPendingReference = $this->hasApproximatePhrase($text, [
            'que queda pendiente',
            'que queda por hacer',
            'que falta por hacer',
            'pendientes quedan',
            'tareas pendientes',
            'tareas abiertas',
            'pendientes',
            'por hacer',
        ]);

        $hasTaskReference = $this->hasApproximateTerm($text, [
            'tarea',
            'tareas',
            'pendiente',
            'pendientes',
            'falta',
        ]);

        $hasCreationIntent = $this->hasApproximateTerm($text, [
            'crea',
            'crear',
            'genera',
            'generar',
            'registra',
            'registrar',
            'agrega',
            'anade',
        ]);

        $hasUpdateIntent = $this->hasApproximateTerm($text, [
            'actualiza',
            'actualizar',
            'sincroniza',
            'sincronizar',
        ]);

        return ($hasPendingReference || $hasTaskReference)
            && ! $hasCreationIntent
            && ! $hasUpdateIntent;
    }

    private function pendingTasksMessage(string $text, bool $allowAllProjects = false): array
    {
        if (! Schema::hasTable('proyectos') || ! Schema::hasTable('tareas')) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No puedo consultar los pendientes porque todavía no están disponibles las tablas de proyectos o tareas.',
                ],
                'navigation' => null,
            ];
        }

        $projects = Proyecto::with(['tareas' => function ($query) {
            $query->whereIn('estado', ['Pendiente', 'En progreso', 'En revisión'])
                ->orderByRaw("CASE prioridad WHEN 'Alta' THEN 1 WHEN 'Media' THEN 2 ELSE 3 END")
                ->orderBy('fecha_limite')
                ->orderBy('titulo');
        }])->orderBy('nombre')->get();

        $project = $projects->first(function (Proyecto $project) use ($text) {
            $name = $this->normalizeInstruction($project->nombre);

            return $name !== '' && (str_contains($text, $name) || $this->hasApproximateTerm($text, [$name]));
        });

        $requestAllProjects = $allowAllProjects || $this->hasApproximatePhrase($text, [
            'todos',
            'todas',
            'general',
            'en general',
            'todos los proyectos',
            'todos mis proyectos',
        ]);

        if (! $project && ! $requestAllProjects && $projects->count() > 1) {
            $projectNames = $projects->pluck('nombre')
                ->map(fn (string $name) => "- {$name}")
                ->implode("\n");

            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => "Claro. Veo {$projects->count()} proyectos y no quiero mezclar sus tareas.\n\n".
                        "¿De qué proyecto quieres que revise los pendientes?\n\n{$projectNames}\n\n".
                        'También puedes responder “todos” o “en general” para mostrarte los pendientes de todos los proyectos.',
                ],
                'navigation' => null,
            ];
        }

        $selectedProjects = $project ? collect([$project]) : $projects;
        $openProjects = $selectedProjects->filter(fn (Proyecto $item) => $item->tareas->isNotEmpty());

        if ($project && $project->tareas->isEmpty()) {
            $content = "Revisé {$project->nombre} y no tiene tareas pendientes. Todas sus tareas están completadas o canceladas.";
        } elseif ($openProjects->isEmpty()) {
            $content = $project
                ? "Revisé {$project->nombre} y no tiene tareas pendientes. Todas sus tareas están completadas o canceladas."
                : 'No encontré tareas pendientes en ningún proyecto. Todas las tareas están completadas o canceladas.';
        } else {
            $lines = [];

            foreach ($openProjects as $item) {
                $lines[] = "En {$item->nombre} quedan {$item->tareas->count()} tarea(s):";

                foreach ($item->tareas as $task) {
                    $details = "{$task->titulo} — {$task->estado}, prioridad {$task->prioridad}";

                    if ($task->fecha_limite) {
                        $details .= ', límite '.$task->fecha_limite->format('d/m/Y');
                    }

                    $lines[] = "- {$details}";
                }

                $lines[] = '';
            }

            $scope = $project ? "Esto es lo pendiente de {$project->nombre}" : 'Esto es lo que queda pendiente en tus proyectos';
            $content = $scope.":\n\n".implode("\n", $lines).
                "\nLas tareas en estado Pendiente, En progreso o En revisión se consideran abiertas.";
        }

        return [
            'message' => [
                'role' => 'assistant',
                'content' => $content,
            ],
            'navigation' => null,
        ];
    }

    private function isImprovementAnalysisInstruction(string $text): bool
    {
        return $this->hasApproximatePhrase($text, [
            'areas de mejora',
            'area de mejora',
            'oportunidades de mejora',
            'recomendaciones para mejorar',
            'dame recomendaciones',
            'recomiendame mejoras',
            'recomiendame algo',
            'como mejorar el proyecto',
            'diagnostico del proyecto',
            'diagnostico general',
            'analisis general',
        ]);
    }

    private function improvementAnalysisMessage(string $text): array
    {
        if (! Schema::hasTable('proyectos') || ! Schema::hasTable('tareas') || ! Schema::hasTable('bugs')) {
            return $this->assistantResponse(
                'No puedo preparar el diagnóstico porque todavía no están disponibles todas las tablas necesarias.'
            );
        }

        $projects = Proyecto::with(['tareas', 'bugs', 'secciones.funcionalidades'])
            ->orderBy('nombre')
            ->get();
        $project = $this->projectMentionedInText($text);
        $requestAllProjects = $this->hasApproximatePhrase($text, [
            'todos',
            'todas',
            'general',
            'en general',
            'todos los proyectos',
        ]);

        if (! $project && ! $requestAllProjects && $projects->count() > 1) {
            $projectNames = $projects->pluck('nombre')
                ->map(fn (string $name) => "- {$name}")
                ->implode("\n");

            return $this->assistantResponse(
                "Puedo analizar las áreas de mejora, pero veo {$projects->count()} proyectos.\n\n".
                "¿Cuál quieres que revise?\n\n{$projectNames}\n\n".
                'También puedes responder “todos” o “en general” para comparar los proyectos.'
            );
        }

        $selectedProjects = $project ? collect([$project]) : $projects;
        $reports = $selectedProjects->map(fn (Proyecto $item) => $this->buildImprovementReport($item));
        $content = $reports->implode("\n\n".str_repeat('-', 48)."\n\n");

        return $this->assistantResponse(
            "Diagnóstico de áreas de mejora\n\n{$content}\n\n".
            'Este diagnóstico es de solo lectura: no modifica tareas, bugs ni funcionalidades. '.
            'Las recomendaciones se basan en los registros de DevControl y en la evidencia local disponible.'
        );
    }

    private function isSecurityAnalysisInstruction(string $text): bool
    {
        return $this->hasApproximatePhrase($text, [
            'analiza la seguridad',
            'analisis de seguridad',
            'revisa la seguridad',
            'seguridad del proyecto',
            'vulnerabilidades',
            'vulnerabilidad',
            'riesgos de seguridad',
            'auditoria de seguridad',
            'mfa',
            'autenticacion segura',
        ]);
    }

    private function securityAnalysisMessage(string $text): array
    {
        $project = $this->projectMentionedInText($text);
        $findings = $this->detectSecurityFindings();

        if ($findings === []) {
            return $this->assistantResponse(
                'No encontré riesgos de alta confianza con las comprobaciones actuales. Esto no sustituye una prueba de penetración ni una revisión manual completa; todavía conviene validar dependencias, configuración del servidor y controles de acceso.'
            );
        }

        $grouped = collect($findings)->groupBy('severity');
        $order = ['Crítica', 'Alta', 'Media', 'Baja'];
        $lines = [];

        foreach ($order as $severity) {
            foreach ($grouped->get($severity, []) as $index => $finding) {
                $lines[] = ($index + 1).". [{$finding['severity']}] {$finding['title']}\n".
                    "Qué puede pasar: {$finding['impact']}\n".
                    "Por qué lo detecté: {$finding['evidence']}\n".
                    "Recomendación: {$finding['recommendation']}";
            }
        }

        $scope = $project ? " para {$project->nombre}" : '';

        return $this->assistantResponse(
            "Análisis de seguridad{$scope}\n\n".
            implode("\n\n", $lines).
            "\n\nPrioridad sugerida: corrige primero los hallazgos críticos y altos. ".
            'Este análisis es de solo lectura y no cambia archivos ni usuarios.'
        );
    }

    private function detectSecurityFindings(): array
    {
        $files = [];
        foreach ([base_path('app'), base_path('routes'), base_path('config'), base_path('database')] as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                $files[$file->getRelativePathname()] = $file->getContents();
            }
        }

        $allContents = implode("\n", $files);
        $findings = [];
        $hasPublicRegistration = str_contains($files['routes/web.php'] ?? '', "Route::post('/register'");
        $registersAdmin = str_contains($files['app/Http/Controllers/RegisterController.php'] ?? '', "'rol' => 'admin'");
        $adminDefault = str_contains($allContents, "default('admin')");

        if ($hasPublicRegistration && ($registersAdmin || $adminDefault)) {
            $findings[] = [
                'severity' => 'Crítica',
                'title' => 'El registro público puede crear administradores',
                'impact' => 'Una persona no autorizada podría registrarse y obtener acceso a proyectos, tareas, bugs, usuarios y otras operaciones administrativas.',
                'evidence' => 'La ruta de registro es pública y el alta asigna el rol admin o la base de datos lo usa como valor predeterminado.',
                'recommendation' => 'Asignar un rol no privilegiado por defecto, restringir el registro o exigir aprobación de un administrador antes de otorgar permisos elevados.',
            ];
        }

        if (preg_match("/'password'\\s*=>\\s*\\$datosValidados\\['password'\\]/", $files['app/Http/Controllers/RegisterController.php'] ?? '') === 1) {
            $findings[] = [
                'severity' => 'Alta',
                'title' => 'La contraseña se asigna sin un hash explícito',
                'impact' => 'Si el modelo no aplica hashing automáticamente, las contraseñas podrían almacenarse en texto plano y quedar expuestas ante una filtración de la base de datos.',
                'evidence' => 'El controlador asigna directamente el valor recibido al atributo password.',
                'recommendation' => 'Usar Hash::make() o confirmar mediante el cast de contraseña del modelo que siempre se aplica hashing antes de persistir.',
            ];
        }

        $loginContents = $files['app/Http/Controllers/LoginCcontroller.php'] ?? '';
        if ($loginContents !== ''
            && ! str_contains($allContents, 'RateLimiter')
            && ! str_contains($allContents, 'throttle:')) {
            $findings[] = [
                'severity' => 'Media',
                'title' => 'No se detectó limitación de intentos de inicio de sesión',
                'impact' => 'Un atacante podría intentar muchas contraseñas y aumentar el riesgo de fuerza bruta o saturación del formulario de acceso.',
                'evidence' => 'Existe autenticación mediante Auth::attempt, pero no se encontró RateLimiter ni middleware throttle.',
                'recommendation' => 'Aplicar rate limiting por IP y correo, registrar intentos fallidos y añadir bloqueo progresivo o CAPTCHA después de varios intentos.',
            ];
        }

        if (! str_contains($allContents, 'TwoFactor')
            && ! str_contains(strtolower($allContents), 'mfa')
            && ! str_contains(strtolower($allContents), 'two-factor')) {
            $findings[] = [
                'severity' => 'Media',
                'title' => 'No se detectó MFA para cuentas administrativas',
                'impact' => 'Si una contraseña administrativa es robada, el atacante podría entrar sin un segundo factor que limite el acceso.',
                'evidence' => 'No aparecen controles de autenticación multifactor en el código revisado.',
                'recommendation' => 'Añadir MFA, preferiblemente obligatorio para administradores, con códigos de recuperación y protección contra intentos repetidos.',
            ];
        }

        if (str_contains($allContents, 'APP_DEBUG=true')) {
            $findings[] = [
                'severity' => 'Alta',
                'title' => 'El modo debug parece estar habilitado',
                'impact' => 'Los errores podrían revelar rutas, consultas, variables de entorno y detalles internos de la aplicación.',
                'evidence' => 'Se encontró APP_DEBUG=true en los archivos inspeccionados.',
                'recommendation' => 'Desactivar debug en producción, revisar APP_ENV y evitar mostrar excepciones detalladas a usuarios finales.',
            ];
        }

        return $findings;
    }

    private function buildImprovementReport(Proyecto $project): string
    {
        $openTasks = $project->tareas->whereIn('estado', ['Pendiente', 'En progreso', 'En revisión']);
        $highPriorityTasks = $openTasks->where('prioridad', 'Alta');
        $overdueTasks = $openTasks->filter(fn (Tarea $task) => $task->fecha_limite
            && $task->fecha_limite->startOfDay()->isPast());
        $activeBugs = $project->bugs->whereNotIn('estado', ['Solucionado', 'Cerrado']);
        $highPriorityBugs = $activeBugs->where('prioridad', 'Alta');
        $functionalities = $project->secciones->flatMap(fn ($section) => $section->funcionalidades);
        $unfinishedFunctionalities = $functionalities->whereIn('estado', ['Pendiente', 'En progreso', 'Requiere revisión']);
        $recommendations = [];

        if ($openTasks->isNotEmpty()) {
            $recommendations[] = "Priorizar las {$openTasks->count()} tareas abiertas y definir responsables o fechas límite.";
        }
        if ($highPriorityTasks->isNotEmpty()) {
            $recommendations[] = "Atender primero las {$highPriorityTasks->count()} tareas de prioridad alta.";
        }
        if ($overdueTasks->isNotEmpty()) {
            $recommendations[] = "Revisar {$overdueTasks->count()} tareas vencidas y actualizar su avance o fecha límite.";
        }
        if ($activeBugs->isNotEmpty()) {
            $recommendations[] = "Resolver los {$activeBugs->count()} bugs activos antes de cerrar el proyecto.";
        }
        if ($highPriorityBugs->isNotEmpty()) {
            $recommendations[] = "Dar prioridad a los {$highPriorityBugs->count()} bugs de alta prioridad.";
        }
        if ($unfinishedFunctionalities->isNotEmpty()) {
            $recommendations[] = "Completar o verificar las {$unfinishedFunctionalities->count()} funcionalidades que aún no están implementadas.";
        }
        if ($project->secciones->isEmpty()) {
            $recommendations[] = 'Registrar secciones y funcionalidades para poder medir mejor la cobertura del proyecto.';
        }
        if ($recommendations === []) {
            $recommendations[] = 'Mantener seguimiento periódico, documentar cambios y validar el proyecto antes de marcarlo como terminado.';
        }

        $taskSummary = "{$openTasks->count()} abiertas / {$project->tareas->count()} totales";
        $bugSummary = "{$activeBugs->count()} activos / {$project->bugs->count()} totales";
        $functionalitySummary = $functionalities->isEmpty()
            ? 'No hay funcionalidades registradas'
            : "{$unfinishedFunctionalities->count()} por completar / {$functionalities->count()} registradas";

        return "Proyecto: {$project->nombre}\n\n".
            "Resumen:\n".
            "- Tareas: {$taskSummary}\n".
            "- Bugs: {$bugSummary}\n".
            "- Funcionalidades: {$functionalitySummary}\n".
            "- Progreso registrado: ".($project->progreso ?? 'no definido')."%\n\n".
            "Áreas de mejora:\n".
            collect($recommendations)->values()->map(fn (string $recommendation, int $index) => ($index + 1).". {$recommendation}")->implode("\n");
    }

    private function isProjectCreationInstruction(string $text): bool
    {
        return $this->hasApproximateTerm($text, ['crea', 'crear', 'registra', 'registrar'])
            && $this->hasApproximateTerm($text, ['proyecto'])
            && (
                str_contains($text, 'nombre del proyecto')
                || str_contains($text, 'secciones y funcionalidades')
                || str_contains($text, 'objetivo principal')
            );
    }

    private function isProjectStructureUpdateInstruction(string $text): bool
    {
        $hasStructure = (
                str_contains($text, 'nombre del proyecto')
                || str_contains($text, 'nombre proyecto')
                || preg_match('/actualiza(?:r)?\s+el\s+proyecto\s+\**[^*\n]+\**/u', $text) === 1
                || (str_contains($text, 'actualiza el proyecto') && str_contains($text, 'agregando las funcionalidades'))
            )
            && (
                str_contains($text, 'funcionalidades')
                || str_contains($text, 'funcionalidad')
            )
            && (
                str_contains($text, 'que abarca')
                || str_contains($text, 'estado')
                || $this->hasApproximateTerm($text, ['implementada', 'parcial', 'en desarrollo'])
            );

        $hasNumberedSections = preg_match('/(?:^|\s)\d+\s+\p{L}/u', $text) === 1;
        $hasCompactSections = $this->hasApproximateTerm($text, ['implementada', 'parcial', 'en desarrollo']);

        return $hasStructure
            && $this->hasApproximateTerm($text, ['proyecto'])
            && ($hasNumberedSections || $hasCompactSections || strlen($text) > 2000);
    }

    private function requestProjectStructureUpdateAuthorization(string $input): array
    {
        if (! Schema::hasTable('proyectos') || ! Schema::hasTable('secciones') || ! Schema::hasTable('funcionalidades')) {
            return $this->assistantResponse('No puedo actualizar la estructura porque faltan las tablas de proyectos, secciones o funcionalidades.');
        }

        $parsed = $this->parseProjectPrompt($input);
        $project = $parsed
            ? Proyecto::query()
                ->where(function ($query) use ($parsed) {
                    $query
                        ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($parsed['project']['nombre'], 'UTF-8')])
                        ->orWhereRaw('LOWER(nombre) = ?', ['devcontrol']);
                })
                ->first()
            : null;

        if (! $parsed || ! $project) {
            return $this->assistantResponse(
                'No encontré el proyecto indicado o no pude leer el prompt. Usa el nombre exacto y conserva los encabezados de secciones, funcionalidades y estado.'
            );
        }

        $sectionCount = count($parsed['sections']);
        $featureCount = collect($parsed['sections'])->sum(fn (array $section) => count($section['features']));

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Encontré el proyecto \"{$project->nombre}\".\n\n".
                    "Voy a agregar o actualizar {$featureCount} funcionalidades dentro de {$sectionCount} secciones, ".
                    "respetando los nombres, estados y evitando duplicados. No eliminaré funcionalidades existentes.\n\n".
                    '¿Confirmas? Responde "sí, confirmar" o "cancelar".',
            ],
            'navigation' => null,
            'pending_action' => [
                'type' => 'update_project_structure',
                'project_id' => $project->id,
                'sections' => $parsed['sections'],
            ],
        ];
    }

    private function requestProjectCreationAuthorization(string $input): array
    {
        if (! Schema::hasTable('proyectos') || ! Schema::hasTable('secciones') || ! Schema::hasTable('funcionalidades')) {
            return $this->assistantResponse(
                'No puedo crear el proyecto porque todavía no están disponibles las tablas de proyectos, secciones y funcionalidades.'
            );
        }

        $project = $this->parseProjectPrompt($input);

        if (! $project) {
            return $this->assistantResponse(
                'Puedo crear proyectos mediante prompts, pero necesito el formato con “Nombre del proyecto”, “Descripción” y, si aplica, secciones con funcionalidades y estado.'
            );
        }

        $existing = Proyecto::whereRaw('LOWER(nombre) = ?', [mb_strtolower($project['project']['nombre'], 'UTF-8')])->first();

        if ($existing) {
            return $this->requestProjectStructureUpdateAuthorization($input);
        }

        $sectionCount = count($project['sections']);
        $featureCount = collect($project['sections'])->sum(fn (array $section) => count($section['features']));

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Entendí que quieres crear este proyecto:\n\n".
                    "Nombre: {$project['project']['nombre']}\n".
                    "Estado: {$project['project']['estado']}\n".
                    "Secciones: {$sectionCount}\n".
                    "Funcionalidades: {$featureCount}\n\n".
                    "La creación guardará el proyecto y toda su estructura. ¿Confirmas? Responde \"sí, confirmar\" o \"cancelar\".",
            ],
            'navigation' => null,
            'pending_action' => [
                'type' => 'create_project',
                'project' => $project['project'],
                'sections' => $project['sections'],
            ],
        ];
    }

    private function parseProjectPrompt(string $input): ?array
    {
        $name = $this->extractPromptField($input, 'Nombre del proyecto');
        if (! $name && preg_match('/actualiza(?:r)?\s+el\s+proyecto\s+(?:\*\*)?([^*\n]+?)(?:\*\*)?(?:\s+dentro de|\s+agregando|\s*:)/iu', $input, $nameMatch)) {
            $name = trim($nameMatch[1]);
        }
        $name = $name
            ? trim(preg_split('/\R/u', $name, 2)[0], " \t\n\r\0\x0B*`\"'")
            : null;

        if (! $name) {
            return null;
        }

        $sections = [];
        preg_match_all('/\*\*(?:Secci.n\s+)?["\']?([^*"\']+?)["\']?\s+[^\p{L}\p{N}\s]\s+(Implementada|Parcial|En desarrollo)\*\*/iu', $input, $inlineHeadings, PREG_OFFSET_CAPTURE);

        foreach ($inlineHeadings[1] ?? [] as $index => [$rawSectionName, $offset]) {
            $start = $inlineHeadings[0][$index][1] + strlen($inlineHeadings[0][$index][0]);
            $end = isset($inlineHeadings[0][$index + 1])
                ? $inlineHeadings[0][$index + 1][1]
                : strlen($input);
            $block = substr($input, $start, $end - $start);
            preg_match_all('/["\']([^"\']+)["\']/u', $block, $featureLines);
            $sectionName = trim($rawSectionName);

            if (in_array($this->normalizeInstruction($sectionName), ['inicio se sesion', 'inicio de sesion'], true)) {
                $sectionName = 'Acceso';
            }

            $sections[] = [
                'nombre' => $sectionName,
                'descripcion' => null,
                'features' => array_map(fn (string $featureName) => [
                    'nombre' => trim($featureName),
                    'descripcion' => null,
                    'estado' => $this->normalizeFeatureStatus($inlineHeadings[2][$index][0]),
                ], $featureLines[1] ?? []),
            ];
        }

        if ($sections !== []) {
            return [
                'project' => [
                    'nombre' => trim($name),
                    'descripcion' => $this->extractPromptField($input, 'Descripción'),
                    'contexto' => $this->extractPromptField($input, 'Contexto del proyecto'),
                    'objetivo' => $this->extractPromptField($input, 'Objetivo principal'),
                    'tecnologias' => $this->extractPromptField($input, 'Tecnologías'),
                    'reglas' => $this->extractPromptList($input, 'Reglas importantes'),
                    'repositorio_url' => null,
                    'fecha_inicio' => now()->toDateString(),
                    'fecha_meta' => null,
                    'estado' => 'Activo',
                    'progreso' => 0,
                ],
                'sections' => $sections,
            ];
        }

        preg_match_all('/^\s*\*\*.+?\*\*\s*$/mu', $input, $headingLines, PREG_OFFSET_CAPTURE);

        foreach ($headingLines[0] ?? [] as $index => [$heading, $offset]) {
            if (preg_match('/^\s*\*\*(.+?)\s+[^\p{L}\p{N}\s]\s+(Implementada|Parcial|En desarrollo)\*\*\s*$/iu', $heading, $headingMatch) !== 1) {
                continue;
            }

            $start = $offset + strlen($heading);
            $end = isset($headingLines[0][$index + 1])
                ? $headingLines[0][$index + 1][1]
                : strlen($input);
            $block = substr($input, $start, $end - $start);
            preg_match_all('/^\s*-\s+(.+?)\s*$/mu', $block, $featureLines);
            $sectionName = preg_replace('/^\s*secci.n\s+/iu', '', $headingMatch[1]) ?? $headingMatch[1];
            $sectionName = trim($sectionName, " \t\n\r\0\x0B\"'");
            if (in_array($this->normalizeInstruction($sectionName), ['inicio se sesion', 'inicio de sesion'], true)) {
                $sectionName = 'Acceso';
            }

            $sections[] = [
                'nombre' => $sectionName,
                'descripcion' => null,
                'features' => array_map(fn (string $featureName) => [
                    'nombre' => trim($featureName, " \t\n\r\0\x0B\"'"),
                    'descripcion' => null,
                    'estado' => $this->normalizeFeatureStatus($headingMatch[2]),
                ], $featureLines[1] ?? []),
            ];
        }

        if ($sections !== []) {
            return [
                'project' => [
                    'nombre' => trim($name),
                    'descripcion' => $this->extractPromptField($input, 'Descripción'),
                    'contexto' => $this->extractPromptField($input, 'Contexto del proyecto'),
                    'objetivo' => $this->extractPromptField($input, 'Objetivo principal'),
                    'tecnologias' => $this->extractPromptField($input, 'Tecnologías'),
                    'reglas' => $this->extractPromptList($input, 'Reglas importantes'),
                    'repositorio_url' => null,
                    'fecha_inicio' => now()->toDateString(),
                    'fecha_meta' => null,
                    'estado' => 'Activo',
                    'progreso' => 0,
                ],
                'sections' => $sections,
            ];
        }

        preg_match_all('/^\s*###\s+\d+\.\s+(.+?)\s*$/mu', $input, $headings, PREG_OFFSET_CAPTURE);
        $matches = $headings[1] ?? [];

        foreach ($matches as $index => [$sectionName, $offset]) {
            $start = $offset + strlen($sectionName);
            $end = isset($matches[$index + 1]) ? $matches[$index + 1][1] : strlen($input);
            $block = substr($input, $start, $end - $start);
            $scope = $this->extractPromptField($block, 'Qué abarca') ?? '';
            $status = $this->normalizeFeatureStatus($this->extractPromptField($block, 'Estado') ?? 'Desconocida');
            $features = [];

            if (preg_match('/\*\*Funcionalidades:\*\*(.*?)(?:\*\*Estado:\*\*|$)/su', $block, $featureMatch)) {
                preg_match_all('/^\s*-\s+(.+?)\s*$/mu', $featureMatch[1], $featureLines);
                foreach ($featureLines[1] as $featureName) {
                    $features[] = [
                        'nombre' => trim($featureName),
                        'descripcion' => null,
                        'estado' => $status,
                    ];
                }
            }

            $sections[] = [
                'nombre' => trim($sectionName),
                'descripcion' => trim($scope),
                'features' => $features,
            ];
        }

        return [
            'project' => [
                'nombre' => trim($name),
                'descripcion' => $this->extractPromptField($input, 'Descripción'),
                'contexto' => $this->extractPromptField($input, 'Contexto del proyecto'),
                'objetivo' => $this->extractPromptField($input, 'Objetivo principal'),
                'tecnologias' => $this->extractPromptField($input, 'Tecnologías'),
                'reglas' => $this->extractPromptList($input, 'Reglas importantes'),
                'repositorio_url' => null,
                'fecha_inicio' => now()->toDateString(),
                'fecha_meta' => null,
                'estado' => 'Activo',
                'progreso' => 0,
            ],
            'sections' => $sections,
        ];
    }

    private function extractPromptField(string $input, string $label): ?string
    {
        $pattern = '/(?:\*\*)?'.preg_quote($label, '/').':(?:\*\*)?\s*(.*?)(?=\n\s*(?:\*\*|###)|\z)/su';

        if (preg_match($pattern, $input, $match) !== 1) {
            return null;
        }

        $value = trim(preg_replace('/\r\n?/', "\n", $match[1]) ?? $match[1]);
        $value = trim($value, " \t\n\r\0\x0B*`");
        return $value !== '' ? $value : null;
    }

    private function extractPromptList(string $input, string $label): ?string
    {
        $value = $this->extractPromptField($input, $label);

        if (! $value) {
            return null;
        }

        preg_match_all('/^\s*-\s+(.+?)\s*$/mu', $value, $items);
        return $items[1] ? implode("\n", array_map('trim', $items[1])) : $value;
    }

    private function normalizeFeatureStatus(string $status): string
    {
        return match ($this->normalizeInstruction($status)) {
            'implementada' => 'Implementada',
            'parcial' => 'Parcial',
            'en desarrollo' => 'En desarrollo',
            'pendiente' => 'Pendiente',
            'requiere revision' => 'Requiere revisión',
            default => 'Desconocida',
        };
    }

    private function isMutationInstruction(string $text): bool
    {
        $hasAction = $this->hasApproximateTerm($text, [
            'elimina', 'eliminar', 'borra', 'borrar', 'edita', 'editar',
            'modifica', 'modificar', 'cambia', 'cambiar',
        ]);
        $hasEntity = $this->hasApproximateTerm($text, [
            'tarea', 'tareas', 'bug', 'bugs', 'error', 'errores',
        ]);

        return $hasAction && $hasEntity;
    }

    private function isIncidentCreationInstruction(string $text): bool
    {
        return $this->hasApproximateTerm($text, ['incidente', 'incidentes'])
            && $this->hasApproximateTerm($text, ['crea', 'crear', 'registra', 'registrar', 'agrega', 'anade']);
    }

    private function requestIncidentAuthorization(string $text): array
    {
        $title = $this->quotedValue($text);
        $project = $this->projectMentionedInText($text);

        if (! $project) {
            return $this->assistantResponse(
                '¿En qué proyecto quieres registrar la incidencia? Indícame el nombre exacto del proyecto.'
            );
        }

        if (! $title) {
            return $this->assistantResponse(
                'Indícame el título de la incidencia entre comillas. Ejemplo: crea una incidencia "API no responde".'
            );
        }

        $priority = $this->extractIncidentValue($text, [
            'alta' => 'Alta',
            'media' => 'Media',
            'baja' => 'Baja',
        ], 'Media');
        $status = $this->extractIncidentValue($text, [
            'abierto' => 'Abierto',
            'en investigacion' => 'En investigación',
            'en resolucion' => 'En resolución',
            'resuelto' => 'Resuelto',
        ], 'Abierto');
        $description = $this->extractAfterLabel($text, ['descripcion', 'detalle', 'porque', 'porque ocurre']);

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Voy a registrar esta incidencia:\n\n".
                    "Título: {$title}\nProyecto: {$project->nombre}\nPrioridad: {$priority}\n".
                    "Estado: {$status}\nDescripción: ".($description ?: 'Sin descripción').
                    "\n\n¿Confirmas? Responde \"sí, confirmar\" o \"cancelar\".",
            ],
            'navigation' => null,
            'pending_action' => [
                'type' => 'create_incident',
                'project_id' => $project->id,
                'title' => $title,
                'description' => $description ?: null,
                'priority' => $priority,
                'status' => $status,
            ],
        ];
    }

    private function extractIncidentValue(string $text, array $values, string $default): string
    {
        foreach ($values as $needle => $value) {
            if (str_contains($text, $needle)) {
                return $value;
            }
        }

        return $default;
    }

    private function extractAfterLabel(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            $position = mb_strpos($text, $label);

            if ($position !== false) {
                $value = trim(mb_substr($text, $position + mb_strlen($label)));
                $value = ltrim($value, ":,.- ");

                return $value !== '' ? ucfirst($value) : null;
            }
        }

        return null;
    }

    private function requestMutationAuthorization(string $text): array
    {
        if (! Schema::hasTable('proyectos') || ! Schema::hasTable('tareas') || ! Schema::hasTable('bugs')) {
            return $this->assistantResponse(
                'No puedo modificar esos registros porque todavía no están disponibles todas las tablas necesarias.'
            );
        }

        $entity = $this->hasApproximateTerm($text, ['bug', 'bugs', 'error', 'errores']) ? 'bugs' : 'tareas';
        $isDelete = $this->hasApproximateTerm($text, ['elimina', 'eliminar', 'borra', 'borrar']);
        $isBulk = $this->hasApproximateTerm($text, ['todos', 'todas', 'todo', 'toda']);
        $project = $this->projectMentionedInText($text);

        if ($isBulk && ! $project) {
            return $this->assistantResponse(
                '¿De qué proyecto quieres hacer esa operación? Indícame el nombre del proyecto. No ejecutaré un borrado masivo sin ese alcance explícito.'
            );
        }

        if ($isBulk && ! $isDelete) {
            return $this->assistantResponse(
                'Puedo editar registros individuales, pero no editaré todas las tareas o bugs de un proyecto de forma ambigua. Indícame cuál registro quieres cambiar.'
            );
        }

        if ($isBulk) {
            $count = $entity === 'tareas'
                ? $project->tareas()->count()
                : $project->bugs()->count();

            if ($count === 0) {
                return $this->assistantResponse(
                    "No encontré {$entity} para eliminar en el proyecto {$project->nombre}."
                );
            }

            $action = [
                'type' => 'delete_all',
                'entity' => $entity,
                'project_id' => $project->id,
            ];
            $description = "eliminar {$count} {$entity} del proyecto {$project->nombre}";
        } else {
            $title = $this->quotedValue($text);
            $folio = $entity === 'bugs' ? $this->bugFolioFromInstruction($text) : null;

            if (! $title && ! $folio) {
                return $this->assistantResponse(
                    $entity === 'bugs'
                        ? 'Para evitar modificar el registro equivocado, indícame el folio exacto (por ejemplo BUG-001) o el título entre comillas.'
                        : 'Para evitar modificar el registro equivocado, indícame el título exacto entre comillas. Ejemplo: elimina la tarea "Configurar GitHub".'
                );
            }

            $query = $entity === 'tareas'
                ? Tarea::query()->where('titulo', $title)
                : ($folio
                    ? Bug::query()->whereRaw('UPPER(folio) = ?', [strtoupper($folio)])
                    : Bug::query()->where('titulo', $title));

            if ($project) {
                $query->where('proyecto_id', $project->id);
            }

            $record = $query->first();

            if (! $record) {
                return $this->assistantResponse(
                    $folio
                        ? "No encontré un bug con el folio exacto \"{$folio}\"".($project ? " en {$project->nombre}." : '.')
                        : "No encontré un {$entity} con el título exacto \"{$title}\"".($project ? " en {$project->nombre}." : '.')
                );
            }

            if ($isDelete) {
                $action = [
                    'type' => 'delete',
                    'entity' => $entity,
                    'id' => $record->id,
                ];
                $description = "eliminar el {$entity} \"{$record->titulo}\" del proyecto {$record->proyecto->nombre}";
            } else {
                $changes = $this->extractMutationChanges($text, $entity);

                if ($changes === []) {
                    return $this->assistantResponse(
                        'Encontré el registro, pero necesito saber qué campo cambiar. Puedes indicar el estado o la prioridad, por ejemplo: edita la tarea "Configurar GitHub" y cambia el estado a Completado.'
                    );
                }

                $action = [
                    'type' => 'update',
                    'entity' => $entity,
                    'id' => $record->id,
                    'changes' => $changes,
                ];
                $changeText = collect($changes)->map(fn ($value, $key) => "{$key}: {$value}")->implode(', ');
                $identifier = $folio ? "folio {$record->folio}" : "\"{$record->titulo}\"";
                $description = "editar el {$entity} {$identifier} ({$changeText})";
            }
        }

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Esta operación cambiará datos y requiere tu autorización:\n\n".
                    "Voy a {$description}.\n\n¿Confirmas? Responde \"sí, confirmar\" para continuar o \"cancelar\" para detenerla.",
            ],
            'navigation' => null,
            'pending_action' => $action,
        ];
    }

    private function resolvePendingAction(string $text, array $action): ?array
    {
        if ($this->hasApproximateTerm($text, ['cancela', 'cancelar', 'no', 'detener'])) {
            return $this->assistantResponse('Operación cancelada. No se modificó ningún registro.', null);
        }

        if (! $this->hasApproximateTerm($text, ['si', 'confirmo', 'confirmar', 'autorizo', 'adelante'])) {
            return $this->assistantResponse(
                'La operación sigue pendiente de autorización. Responde "sí, confirmar" para ejecutarla o "cancelar" para no hacer cambios.'
            );
        }

        if ($action['type'] === 'github_commit') {
            $project = Proyecto::findOrFail($action['project_id']);

            try {
                app(ProyectoController::class)->crearCommitGithubConDatos($project, $action['data']);
            } catch (ConnectionException $exception) {
                return $this->assistantResponse(
                    'No se pudo conectar con GitHub para crear el commit. Revisa la conexión y vuelve a intentarlo.'
                );
            } catch (RequestException $exception) {
                return $this->assistantResponse(
                    $exception->response?->json('message')
                        ? "GitHub rechazó el commit: {$exception->response->json('message')}"
                        : 'GitHub rechazó el commit. Revisa el token y sus permisos de escritura.'
                );
            }

            return $this->assistantResponse(
                "Listo. Creé el commit en GitHub para \"{$project->nombre}\" en la ruta \"{$action['data']['ruta']}\"."
            );
        }

        if ($action['type'] === 'github_local_commit') {
            $project = Proyecto::findOrFail($action['project_id']);

            try {
                $result = app(ProyectoController::class)
                    ->crearCommitGithubDesdeCambiosLocales($project, $action['data']);
            } catch (ConnectionException $exception) {
                return $this->assistantResponse(
                    'No se pudo conectar con GitHub para publicar los cambios locales.'
                );
            } catch (RequestException $exception) {
                $status = $exception->response?->status();
                $detail = $exception->response?->json('message');

                return $this->assistantResponse(
                    $detail
                        ? "GitHub rechazó la publicación".($status ? " (HTTP {$status})" : '').": {$detail}"
                        : 'GitHub rechazó la publicación. Revisa el token y sus permisos de escritura.'
                );
            } catch (\RuntimeException $exception) {
                return $this->assistantResponse("No se pudo preparar la publicación: {$exception->getMessage()}");
            }

            return $this->assistantResponse(
                "Listo. Publiqué ".count($result['files'])." archivos de \"{$project->nombre}\" ".
                "en un commit de la rama {$result['branch']}."
            );
        }

        if ($action['type'] === 'update_project_structure') {
            $project = Proyecto::with('secciones.funcionalidades')->findOrFail($action['project_id']);

            DB::transaction(function () use ($project, $action): void {
                foreach ($action['sections'] as $sectionIndex => $sectionData) {
                    $section = $project->secciones()
                        ->where('nombre', $sectionData['nombre'])
                        ->first();

                    if (! $section) {
                        $section = $project->secciones()->create([
                            'nombre' => $sectionData['nombre'],
                            'descripcion' => $sectionData['descripcion'] ?: null,
                            'orden' => $sectionIndex,
                        ]);
                    } else {
                        $section->update([
                            'descripcion' => $sectionData['descripcion'] ?: $section->descripcion,
                            'orden' => $sectionIndex,
                        ]);
                    }

                    foreach ($sectionData['features'] as $featureIndex => $featureData) {
                        $feature = $section->funcionalidades()
                            ->where('nombre', $featureData['nombre'])
                            ->first();

                        if (! $feature) {
                            $section->funcionalidades()->create([
                                'nombre' => $featureData['nombre'],
                                'descripcion' => null,
                                'estado' => $featureData['estado'],
                                'orden' => $featureIndex,
                            ]);
                        } else {
                            $feature->update([
                                'estado' => $featureData['estado'],
                                'orden' => $featureIndex,
                            ]);
                        }
                    }
                }
            });

            $project->load('secciones.funcionalidades');
            $featureCount = $project->secciones->sum(fn ($section) => $section->funcionalidades->count());

            return $this->assistantResponse(
                "Listo. Actualicé {$project->secciones->count()} secciones del proyecto \"{$project->nombre}\" sin duplicar funcionalidades. Ahora tiene {$featureCount} funcionalidades.",
                route('proyectos.show', $project)
            );
        }

        if ($action['type'] === 'delete_all') {
            $project = Proyecto::findOrFail($action['project_id']);
            $deleted = $action['entity'] === 'tareas'
                ? $project->tareas()->delete()
                : $project->bugs()->delete();

            return $this->assistantResponse(
                "Listo. Eliminé {$deleted} {$action['entity']} del proyecto {$project->nombre}.",
                null
            );
        }

        if ($action['type'] === 'code_apply') {
            try {
                $toolResult = app(NexusToolRegistry::class)->execute(
                    'nexus.audit.apply_proposal',
                    ['finding_id' => $action['finding_id']],
                    new NexusToolContext(auth()->user(), 'assistant', true)
                );

                if (! $toolResult->successful) {
                    return $this->assistantResponse(
                        'No apliqué la propuesta: '.($toolResult->error ?? 'la herramienta devolvió un error').'.',
                        null
                    );
                }

                $result = $toolResult->data;

                return $this->assistantResponse(
                    'Listo. Apliqué la propuesta autorizada en: '.implode(', ', $result['files']).
                    "\n\nValidaciones posteriores:\n".$this->formatValidationResults($result['validation']).
                    "\n\nEl hallazgo quedó marcado como resuelto.",
                    null
                );
            } catch (\Throwable $exception) {
                report($exception);

                return $this->assistantResponse(
                    'No apliqué la propuesta porque la validación de seguridad falló: '.$exception->getMessage(),
                    null
                );
            }
        }

        if ($action['type'] === 'create_incident') {
            $incident = Incidente::create([
                'proyecto_id' => $action['project_id'],
                'titulo' => $action['title'],
                'descripcion' => $action['description'],
                'prioridad' => $action['priority'],
                'estado' => $action['status'],
                'fecha_detectado' => now()->toDateString(),
            ]);

            return $this->assistantResponse(
                "Listo. Registré la incidencia \"{$incident->titulo}\" en el proyecto asociado.",
                null
            );
        }

        if ($action['type'] === 'create_project') {
            $project = DB::transaction(function () use ($action) {
                $project = Proyecto::create($action['project']);

                foreach ($action['sections'] as $sectionIndex => $sectionData) {
                    $section = $project->secciones()->create([
                        'nombre' => $sectionData['nombre'],
                        'descripcion' => $sectionData['descripcion'] ?: null,
                        'orden' => $sectionIndex,
                    ]);

                    foreach ($sectionData['features'] as $featureIndex => $featureData) {
                        $section->funcionalidades()->create([
                            'nombre' => $featureData['nombre'],
                            'descripcion' => $featureData['descripcion'] ?: null,
                            'estado' => $featureData['estado'],
                            'orden' => $featureIndex,
                        ]);
                    }
                }

                return $project;
            });

            return $this->assistantResponse(
                "Listo. Creé el proyecto \"{$project->nombre}\" con {$project->secciones()->count()} secciones y ".
                "{$project->secciones()->withCount('funcionalidades')->get()->sum('funcionalidades_count')} funcionalidades.",
                route('proyectos.show', $project)
            );
        }

        $model = $action['entity'] === 'tareas'
            ? Tarea::findOrFail($action['id'])
            : Bug::findOrFail($action['id']);

        if ($action['type'] === 'delete') {
            $title = $model->titulo;
            $model->delete();

            return $this->assistantResponse("Listo. Eliminé el registro \"{$title}\".", null);
        }

        $model->update($action['changes']);

        return $this->assistantResponse(
            "Listo. Actualicé \"{$model->titulo}\" correctamente.",
            null
        );
    }

    private function formatValidationResults(array $validation): string
    {
        return collect($validation['checks'] ?? [])
            ->map(fn (array $check) => ($check['passed'] ? '✅ ' : '❌ ').
                "{$check['command']}: ".($check['passed'] ? 'correcto' : ($check['output'] ?: 'falló')))
            ->implode("\n");
    }

    private function isCodeProposalApplyInstruction(string $text): bool
    {
        return $this->hasApproximatePhrase($text, [
            'aplica la propuesta',
            'aplicar la propuesta',
            'aplica el cambio propuesto',
            'aplicar el cambio propuesto',
        ]);
    }

    private function requestCodeProposalAuthorization(string $text): array
    {
        if (! preg_match('/(?:propuesta|cambio propuesto)\s*(?:numero|número|#)?\s*(\d+)/u', $text, $matches)) {
            return $this->assistantResponse(
                'Indícame el número de la propuesta que quieres aplicar. Ejemplo: "aplica la propuesta 1".'
            );
        }

        $index = (int) $matches[1];
        $proposal = app(NexusAuditService::class)->proposal($index);

        if (! $proposal) {
            return $this->assistantResponse("No encontré la propuesta {$index}. Pide primero \"muéstrame propuestas de solución\".");
        }

        if ($proposal['diff'] === []) {
            return $this->assistantResponse(
                "La propuesta {$index} no tiene un diff determinista aplicable. Nexus no modificará archivos con cambios ambiguos."
            );
        }

        $diffText = collect($proposal['diff'])->map(function (array $change): string {
            return "Archivo: {$change['archivo']}\n--- actual ---\n".
                ($change['actual'] ?? '(vacío)')."\n+++ propuesto +++\n".
                ($change['nuevo'] ?? '(vacío)');
        })->implode("\n\n");

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Voy a aplicar la propuesta {$index} para {$proposal['titulo']}.\n\n".
                    "{$diffText}\n\n".
                    'Esta acción escribirá archivos del proyecto. ¿Confirmas? Responde "sí, confirmar cambio" o "cancelar".',
            ],
            'navigation' => null,
            'pending_action' => [
                'type' => 'code_apply',
                'finding_id' => $proposal['hallazgo_id'],
            ],
        ];
    }

    private function isGithubCommitInstruction(string $text): bool
    {
        return $this->hasApproximateTerm($text, ['commit'])
            && $this->projectMentionedInText($text) !== null
            && $this->hasApproximatePhrase($text, [
                'haz un commit',
                'hacer un commit',
                'crea commit',
                'haz commit',
                'crear un commit',
                'realiza un commit',
                'sube un commit',
                'commit del proyecto',
                'commit en el proyecto',
                'actualiza el archivo',
            ]);
    }

    private function requestGithubCommitAuthorization(string $input): array
    {
        $project = $this->projectMentionedInText($this->normalizeInstruction($input));
        $projectName = $this->extractLabeledQuotedValue($input, ['proyecto', 'project']);

        if ($projectName !== null) {
            $project = Proyecto::query()->get()->first(
                fn (Proyecto $candidate) => $this->normalizeInstruction($candidate->nombre)
                    === $this->normalizeInstruction($projectName)
            );
        }

        if (! $project) {
            return $this->assistantResponse(
                'No encontré ese proyecto. Indica el nombre exacto, por ejemplo: proyecto "DevControl".'
            );
        }

        $integracion = $project->integracionGithub;
        $mensaje = $this->extractLabeledQuotedValue($input, ['mensaje del commit', 'mensaje']);
        $ruta = $this->extractLabeledQuotedValue($input, ['ruta', 'archivo']);
        $contenido = $this->extractLabeledQuotedValue($input, ['contenido']);

        if (! $integracion || ! $integracion->repositorio_url) {
            return $this->assistantResponse(
                "El proyecto \"{$project->nombre}\" no tiene una URL de GitHub configurada."
            );
        }

        if (! config('services.github.token')) {
            return $this->assistantResponse(
                'No puedo autorizar commits porque GITHUB_TOKEN no está configurado en .env.'
            );
        }

        if (! $mensaje || ! $ruta || $contenido === null) {
            $cambios = app(ProyectoController::class)->cambiosLocalesPublicables();

            if ($cambios['error']) {
                return $this->assistantResponse("No pude revisar los cambios locales: {$cambios['error']}");
            }

            if ($cambios['files'] === []) {
                return $this->assistantResponse(
                    "No encontré cambios locales publicables en \"{$project->nombre}\". ".
                    'Los archivos .env, vendor, node_modules, storage y cache se excluyen por seguridad.'
                );
            }

            $mensaje = $mensaje ?: $this->commitMessageFromInstruction($input, $project);
            $rama = $integracion->rama_principal ?: 'main';
            $repositorio = $integracion->repositorio_propietario && $integracion->repositorio_nombre
                ? "{$integracion->repositorio_propietario}/{$integracion->repositorio_nombre}"
                : $integracion->repositorio_url;
            $lista = collect($cambios['files'])
                ->map(fn (array $file) => "- {$file['path']} ({$file['status']})")
                ->implode("\n");

            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => "Detecté cambios locales en \"{$project->nombre}\".\n\n".
                        "Repositorio: {$repositorio}\n".
                        "Rama: {$rama}\n".
                        "Mensaje: {$mensaje}\n".
                        "Archivos que se publicarán:\n{$lista}\n\n".
                        'Se creará un único commit con estos archivos. No se subirán secretos ni dependencias generadas. '.
                        '¿Confirmas? Responde "sí, confirmar" o "cancelar".',
                ],
                'navigation' => null,
                'pending_action' => [
                    'type' => 'github_local_commit',
                    'project_id' => $project->id,
                    'data' => [
                        'mensaje' => $mensaje,
                        'rama' => $rama,
                    ],
                ],
            ];
        }

        if (str_starts_with($ruta, '/') || str_contains($ruta, '..')) {
            return $this->assistantResponse(
                'La ruta del archivo no es válida. Usa una ruta relativa al repositorio, por ejemplo app/README.md.'
            );
        }

        $rama = $integracion->rama_principal ?: 'main';
        $repositorio = $integracion->repositorio_propietario && $integracion->repositorio_nombre
            ? "{$integracion->repositorio_propietario}/{$integracion->repositorio_nombre}"
            : $integracion->repositorio_url;

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Preparé un commit para \"{$project->nombre}\".\n\n".
                    "Repositorio: {$repositorio}\n".
                    "Rama: {$rama}\n".
                    "Archivo: {$ruta}\n".
                    "Mensaje: {$mensaje}\n\n".
                    'La acción creará o actualizará ese archivo en GitHub y registrará la actualización en DevControl. '.
                    '¿Confirmas? Responde "sí, confirmar" o "cancelar".',
            ],
            'navigation' => null,
            'pending_action' => [
                'type' => 'github_commit',
                'project_id' => $project->id,
                'data' => [
                    'mensaje' => $mensaje,
                    'ruta' => $ruta,
                    'contenido' => $contenido,
                    'rama' => $rama,
                ],
            ],
        ];
    }

    private function extractLabeledQuotedValue(string $input, array $labels): ?string
    {
        $labelPattern = implode('|', array_map(
            fn (string $label) => preg_quote($label, '/'),
            $labels
        ));

        return preg_match(
            '/(?:'.$labelPattern.')\s*[:=]?\s*["“](.*?)["”]/isu',
            $input,
            $matches
        ) === 1
            ? trim($matches[1])
            : null;
    }

    private function commitMessageFromInstruction(string $input, Proyecto $project): string
    {
        $quotedMessage = $this->extractLabeledQuotedValue($input, ['mensaje del commit', 'mensaje']);
        if ($quotedMessage) {
            return $quotedMessage;
        }

        $position = mb_stripos($input, $project->nombre, 0, 'UTF-8');
        $suffix = $position === false
            ? ''
            : trim(mb_substr($input, $position + mb_strlen($project->nombre, 'UTF-8'), null, 'UTF-8'));
        $suffix = preg_replace('/^[\s:,\-]+/u', '', $suffix) ?? $suffix;
        $suffix = preg_replace('/^(del proyecto|en el proyecto)\b/iu', '', $suffix) ?? $suffix;
        $suffix = trim($suffix, " \t\n\r\0\x0B.,:;-");

        return $suffix !== ''
            ? ucfirst($suffix)
            : "Sincroniza cambios locales de {$project->nombre}";
    }

    private function projectMentionedInText(string $text): ?Proyecto
    {
        return Proyecto::query()->get()->first(function (Proyecto $project) use ($text) {
            $name = $this->normalizeInstruction($project->nombre);

            return $name !== '' && str_contains($text, $name);
        });
    }

    private function quotedValue(string $text): ?string
    {
        return preg_match('/["“](.+?)["”]/u', $text, $matches) === 1
            ? trim($matches[1])
            : null;
    }

    private function bugFolioFromInstruction(string $text): ?string
    {
        if (preg_match('/\bBUG[\s-]*(\d+)\b/i', $text, $matches) !== 1) {
            return null;
        }

        return 'BUG-'.str_pad($matches[1], 3, '0', STR_PAD_LEFT);
    }

    private function extractMutationChanges(string $text, string $entity): array
    {
        $changes = [];
        $statuses = $entity === 'tareas'
            ? ['pendiente', 'en progreso', 'en revision', 'completado', 'cancelado']
            : ['reportado', 'investigando', 'en desarrollo', 'en pruebas', 'solucionado', 'cerrado'];

        foreach ($statuses as $status) {
            if (str_contains($text, $status)) {
                $changes['estado'] = collect([
                    'en revision' => 'En revisión',
                    'en progreso' => 'En progreso',
                    'en desarrollo' => 'En desarrollo',
                    'en pruebas' => 'En pruebas',
                    'pendiente' => 'Pendiente',
                    'completado' => 'Completado',
                    'cancelado' => 'Cancelado',
                    'reportado' => 'Reportado',
                    'investigando' => 'Investigando',
                    'solucionado' => 'Solucionado',
                    'cerrado' => 'Cerrado',
                ])->get($status);
                break;
            }
        }

        foreach (['alta' => 'Alta', 'media' => 'Media', 'baja' => 'Baja'] as $textPriority => $priority) {
            if (str_contains($text, "prioridad {$textPriority}") || str_contains($text, "prioridad a {$textPriority}")) {
                $changes['prioridad'] = $priority;
                break;
            }
        }

        return $changes;
    }

    private function assistantResponse(string $content, ?string $navigation = null): array
    {
        return [
            'message' => [
                'role' => 'assistant',
                'content' => $content,
            ],
            'navigation' => $navigation,
            'pending_action' => null,
        ];
    }

    private function isGreetingInstruction(string $text): bool
    {
        return $this->hasApproximatePhrase($text, ['hola', 'buenas', 'buenos dias', 'buenas tardes', 'hey', 'que tal', 'gracias']);
    }

    private function isHelpInstruction(string $text): bool
    {
        return $this->hasApproximatePhrase($text, [
            'ayuda',
            'que puedes hacer',
            'que sabes hacer',
            'como me puedes ayudar',
            'comandos',
            'opciones',
            'capacidades',
            'instrucciones',
        ]);
    }

    private function assistantCapabilitiesMessage(): string
    {
        return "Claro, no necesitas hablarme como si fuera un comando. Puedes escribirme de forma normal y yo intento entender la intención:\n\n".
            "• Navegar: \"llévame a usuarios\", \"quiero ver mis tareas\", \"abre la pantalla de proyectos\".\n".
            "• Revisar el sistema: \"cómo está DevControl\", \"qué módulos funcionan\", \"hay algo pendiente\".\n".
            "• Analizar problemas: \"busca bugs\", \"checa qué está fallando\", \"inspecciona el proyecto\".\n".
            "• Detectar áreas de mejora: \"analiza las áreas de mejora de DevControl\" o \"dame recomendaciones del proyecto\".\n".
            "• Mejorar bugs automáticos: \"detalla los bugs\", \"explica mejor los errores encontrados\".\n".
            "• Revisar secciones: \"qué módulos faltan\", \"compara las secciones con lo implementado\".\n".
            "• Consultar Nexus: \"muéstrame los hallazgos\", \"auditoría completa\", \"estado de salud\" o \"resumen por proyecto\".\n".
            "• Ver propuestas sin aplicar cambios: \"muéstrame propuestas de solución\" o \"cómo arreglo los hallazgos\".\n".
            "• Crear proyectos desde un prompt estructurado: incluye nombre, descripción, contexto, objetivo, reglas y secciones; Nexus mostrará un resumen y pedirá confirmación antes de guardar.\n".
            "• Crear commits en GitHub: indica proyecto, mensaje, ruta y contenido completo; Nexus detectará el repositorio configurado y pedirá confirmación antes de publicar.\n".
            "• Registrar incidencias: \"crea una incidencia \\\"API no responde\\\" en DevControl, prioridad alta, descripción: timeout intermitente\".\n".
            "• Vigilar el código local: ejecuta \"php artisan nexus:watch\" para revisar cambios en app, routes, config, database y resources sin modificar archivos.\n".
            "• Crear la tarea de integración de IA cuando me lo pidas.\n\n".
            "• Conversar: puedes preguntarme quién soy, cómo funciona algo o continuar una revisión anterior.\n\n".
            'Si una acción puede cambiar información, te indicaré exactamente qué puedo hacer antes de ejecutarla.';
    }

    private function unrecognizedInstructionMessage(string $input): string
    {
        $hint = $this->closestModuleHint($this->normalizeInstruction($input));

        return 'Entendí que quieres trabajar con DevControl, pero necesito una acción más clara para hacerlo de forma segura.'.
            ($hint ? "\n\n¿Quieres que abra {$hint}?" : '').
            "\n\nPuedes decirlo de forma natural, por ejemplo: \"muéstrame usuarios\", \"revisa los bugs\", \"qué falta por implementar\", \"cómo está el sistema\" o \"llévame a proyectos\".";
    }

    private function closestModuleHint(string $text): ?string
    {
        foreach (self::MODULES as $key => $module) {
            if ($this->hasApproximateTerm($text, [$key, $module['label']])) {
                return $module['label'];
            }
        }

        return null;
    }

    private function isTaskCreationInstruction(string $text): bool
    {
        $creationVerbs = [
            'agrega',
            'anade',
            'anadir',
            'crea',
            'crear',
            'registra',
            'registrar',
            'registr',
            'guarda',
            'guardar',
            'necesito una',
            'quiero una',
            'pon una',
            'haz una',
            'apunta',
        ];

        $hasCreationIntent = $this->hasApproximatePhrase($text, $creationVerbs);
        $hasTaskReference = $this->hasApproximateTerm($text, ['tarea', 'pendiente']);
        $hasAiReference = $this->hasApproximatePhrase($text, [
            'ia conectada',
            'ia al proyecto',
            'asistente conectado',
            'integracion de ia',
            'integracion del asistente',
        ]);

        return $hasCreationIntent && $hasTaskReference && $hasAiReference;
    }

    private function isProjectScanInstruction(string $text): bool
    {
        $scanVerbs = [
            'analiza',
            'analizar',
            'revisa',
            'revisar',
            'inspecciona',
            'inspeccionar',
            'escanea',
            'escanear',
            'detecta',
            'detectar',
            'busca',
            'buscar',
            'verifica',
            'verificar',
            'checa',
            'checar',
        ];

        $hasScanIntent = $this->hasApproximateTerm($text, $scanVerbs);
        $hasProjectScope = $this->hasApproximateTerm($text, [
            'bug', 'error', 'problema', 'falla', 'proyecto', 'devcontrol',
        ]);

        return $hasScanIntent && $hasProjectScope;
    }

    private function isDeepProjectAuditInstruction(string $text): bool
    {
        $hasAuditIntent = $this->hasApproximateTerm($text, [
            'analiza', 'analizar', 'revisa', 'revisar', 'audita', 'auditar',
            'inspecciona', 'inspeccionar', 'compara', 'verifica', 'detalla',
            'profundiza', 'profundo', 'detecta', 'actualiza',
        ]);
        $hasProjectScope = $this->hasApproximateTerm($text, [
            'devcontrol', 'proyecto', 'sistema',
        ]);
        $hasStructureReference = $this->hasApproximateTerm($text, [
            'seccion', 'secciones', 'funcionalidad', 'funcionalidades',
            'codigo', 'archivos', 'implementado', 'implementacion',
            'evidencia', 'pendientes', 'tareas',
        ]);

        return $hasAuditIntent && $hasProjectScope && $hasStructureReference;
    }

    private function deepProjectAuditMessage(): array
    {
        $reconciliation = $this->reconcileProjectContext();
        $response = $this->scanProjectForBugs();
        $response['message']['content'] = "Auditoría profunda y reconciliación de DevControl completadas.\n\n".
            $reconciliation."\n\n".
            $response['message']['content'].
            "\n\nNexus revisó rutas, controladores, modelos, servicios, vistas, configuración, migraciones, seeders y pruebas disponibles. ".
            'Las funcionalidades ya comprobadas se marcaron como implementadas; las que no coinciden con el código quedaron para revisión y no se eliminaron automáticamente.';
        $response['navigation'] = route('proyectos.index');

        return $response;
    }

    private function reconcileProjectContext(): string
    {
        $project = Proyecto::with('secciones.funcionalidades')
            ->whereRaw('LOWER(nombre) = ?', ['devcontrol'])
            ->first();

        if (! $project) {
            return 'No se encontró el proyecto DevControl para reconciliar su contexto.';
        }

        $updatedSections = 0;
        $updatedDescriptions = 0;

        foreach ($project->secciones as $section) {
            $normalized = $this->normalizeInstruction($section->nombre);
            $expected = collect(self::EXPECTED_SECTIONS)
                ->first(fn (string $description, string $name) => $normalized === $name
                    || str_contains($normalized, $name)
                    || str_contains($name, $normalized));

            if ($expected && $section->descripcion !== $expected) {
                $section->update(['descripcion' => $expected]);
                $updatedSections++;
            }

            foreach ($section->funcionalidades as $functionality) {
                if (filled($functionality->descripcion)) {
                    continue;
                }

                $functionality->update([
                    'descripcion' => "Funcionalidad de {$section->nombre}. Su estado se determina mediante evidencia en rutas, controladores, modelos, servicios, vistas, configuración y base de datos.",
                ]);
                $updatedDescriptions++;
            }
        }

        return "Contexto reconciliado: {$updatedSections} descripciones de secciones y {$updatedDescriptions} descripciones de funcionalidades actualizadas. ".
            'No se agregaron ni eliminaron secciones o funcionalidades automáticamente.';
    }

    private function isAutomatedProjectTaskInstruction(string $text): bool
    {
        $hasAutomationIntent = $this->hasApproximateTerm($text, [
            'registra',
            'registrar',
            'crea',
            'crear',
            'genera',
            'generar',
            'asigna',
            'asignar',
        ]);
        $hasReviewIntent = $this->hasApproximateTerm($text, [
            'analiza',
            'analizar',
            'revisa',
            'revisar',
            'inspecciona',
            'inspeccionar',
            'detecta',
            'detectar',
        ]);
        $hasPendingReference = $this->hasApproximateTerm($text, [
            'pendiente',
            'pendientes',
            'tarea',
            'tareas',
            'falta',
            'faltan',
        ]);
        $hasProjectReference = $this->hasApproximateTerm($text, [
            'devcontrol',
            'proyecto',
            'sistema',
        ]);
        $hasFunctionalityReference = $this->hasApproximateTerm($text, [
            'funcionalidad',
            'funcionalidades',
            'feature',
        ]);
        $hasTaskGenerationReference = $this->hasApproximateTerm($text, [
            'tarea',
            'tareas',
            'pendiente',
            'pendientes',
            'falta',
            'faltan',
        ]);

        return ($hasAutomationIntent && $hasPendingReference && $hasProjectReference)
            || ($hasReviewIntent && $hasTaskGenerationReference && $hasFunctionalityReference);
    }

    private function isTaskUpdateInstruction(string $text): bool
    {
        $hasUpdateIntent = $this->hasApproximateTerm($text, [
            'actualiza',
            'actualizar',
            'sincroniza',
            'sincronizar',
            'verifica',
            'verificar',
            'revisa',
            'revisar',
        ]);
        $hasTaskReference = $this->hasApproximateTerm($text, [
            'tarea',
            'tareas',
            'pendiente',
            'pendientes',
        ]);
        $hasProjectReference = $this->hasApproximateTerm($text, [
            'devcontrol',
            'proyecto',
            'sistema',
        ]);

        return $hasUpdateIntent && $hasTaskReference && $hasProjectReference;
    }

    private function isTaskSynchronizationInstruction(string $text): bool
    {
        $hasSyncIntent = $this->hasApproximateTerm($text, [
            'sincroniza',
            'sincronizar',
            'depura',
            'depurar',
            'limpia',
            'limpiar',
        ]);
        $hasTaskReference = $this->hasApproximateTerm($text, [
            'tarea',
            'tareas',
            'pendiente',
            'pendientes',
        ]);
        $hasProjectReference = $this->hasApproximateTerm($text, [
            'devcontrol',
            'proyecto',
            'sistema',
        ]);

        return $hasSyncIntent && $hasTaskReference && $hasProjectReference;
    }

    private function updateProjectTasks(): array
    {
        $response = $this->scanProjectForBugs();
        $response['message']['content'] = "Actualización de tareas de DevControl completada.\n\n".
            $response['message']['content'].
            "\n\nLas tareas automáticas relacionadas con funcionalidades implementadas se marcaron como completadas. ".
            'Las tareas que todavía requieren trabajo permanecen abiertas y el análisis solo crea nuevas cuando detecta un pendiente distinto.';
        $response['navigation'] = route('tareas.index');

        return $response;
    }

    private function synchronizeProjectTasks(): array
    {
        $project = Proyecto::with('secciones.funcionalidades', 'tareas')
            ->whereRaw('LOWER(nombre) = ?', ['devcontrol'])
            ->first();

        if (! $project) {
            return $this->assistantResponse('No encontré el proyecto DevControl para sincronizar sus tareas.');
        }

        $completed = $progress = $review = $duplicates = $linked = 0;

        Tarea::withoutEvents(function () use ($project, &$completed, &$progress, &$review, &$duplicates, &$linked): void {
            DB::transaction(function () use ($project, &$completed, &$progress, &$review, &$duplicates, &$linked): void {
                $functionalities = $project->secciones->flatMap->funcionalidades;

                foreach ($project->tareas as $task) {
                    $functionality = $functionalities->firstWhere('id', $task->funcionalidad_id);

                    if (! $functionality) {
                        $functionality = $this->findTaskFunctionality($task, $functionalities);
                        if ($functionality) {
                            $task->funcionalidad_id = $functionality->id;
                            $task->seccion_id = $functionality->seccion_id;
                            $task->save();
                            $linked++;
                        }
                    }

                    if ($functionality) {
                        if ($functionality->estado === 'Implementada' && $task->estado !== 'Completado') {
                            $task->update(['estado' => 'Completado', 'fecha_completada' => now()->toDateString()]);
                            $completed++;
                        } elseif (in_array($functionality->estado, ['Parcial', 'En desarrollo'], true)
                            && $task->estado !== 'Cancelado'
                            && $task->estado !== 'En progreso') {
                            $task->update(['estado' => 'En progreso']);
                            $progress++;
                        }
                    }
                }
            });
        });

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Sincronización y depuración de tareas completada para DevControl.\n\n".
                    "✅ Completadas por funcionalidades implementadas: {$completed}\n".
                    "🔄 Actualizadas a En progreso: {$progress}\n".
                    "🔗 Relacionadas con su funcionalidad por coincidencia segura: {$linked}\n".
                    "⚠️ Marcadas como En revisión (requieren revisión): {$review}\n".
                    "♻️ Duplicados modificados automáticamente: {$duplicates}\n".
                    "📌 Las tareas sin coincidencia segura se conservaron sin cambiar.\n".
                    "🛑 No se eliminaron tareas ni se crearon tareas nuevas.",
            ],
            'navigation' => route('tareas.index'),
        ];
    }

    private function findTaskFunctionality(Tarea $task, $functionalities)
    {
        $title = $this->normalizeInstruction((string) $task->titulo);
        $description = $this->normalizeInstruction((string) $task->descripcion);
        $title = str_replace([
            'verificar funcionalidad ',
            'completar funcionalidad ',
            'atender hallazgo ',
        ], '', $title);

        return $functionalities->first(function ($functionality) use ($title, $description): bool {
            $name = $this->normalizeInstruction((string) $functionality->nombre);
            if ($name === '' || $title === '') {
                return false;
            }

            return $title === $name
                || str_contains($title, $name)
                || ($description !== '' && str_contains($description, $name));
        });
    }

    private function isBugDetailInstruction(string $text): bool
    {
        $hasEditIntent = $this->hasApproximateTerm($text, [
            'edita',
            'editar',
            'actualiza',
            'actualizar',
            'mejora',
            'mejorar',
            'detalla',
            'detallar',
            'explica',
            'explicar',
        ]);

        $hasBugReference = $this->hasApproximateTerm($text, ['bug', 'error', 'fallo', 'problema']);

        $hasDetailReference = $this->hasApproximateTerm($text, [
            'detall', 'descripcion', 'explicacion', 'informacion',
        ]);

        return $hasEditIntent && $hasBugReference && $hasDetailReference;
    }

    private function isSectionAuditInstruction(string $text): bool
    {
        $hasSectionReference = $this->hasApproximateTerm($text, [
            'seccion', 'modulo', 'apartado', 'estructura',
        ]);
        $hasAuditReference = $this->hasApproximatePhrase($text, [
            'que falta', 'falta', 'revisa', 'verifica', 'checa', 'compara',
        ]);

        return $hasSectionReference && $hasAuditReference;
    }

    private function auditProjectSections(): array
    {
        if (! Schema::hasTable('proyectos') || ! Schema::hasTable('secciones')) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No puedo comparar las secciones porque faltan las tablas de proyectos o secciones.',
                ],
                'navigation' => null,
            ];
        }

        $proyecto = Proyecto::with('secciones')
            ->whereRaw('LOWER(nombre) = ?', ['devcontrol'])
            ->first();

        if (! $proyecto) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No encontré el proyecto DevControl para revisar sus secciones.',
                ],
                'navigation' => null,
            ];
        }

        $registered = $proyecto->secciones
            ->mapWithKeys(fn ($seccion) => [$this->normalizeInstruction($seccion->nombre) => $seccion->nombre]);
        $missing = collect(self::EXPECTED_SECTIONS)
            ->reject(fn ($description, $name) => $registered->keys()->contains(
                fn ($registeredName) => str_contains($registeredName, $name) || str_contains($name, $registeredName)
            ));

        $moduleStatus = [
            'index' => ['route' => 'dashboard', 'ready' => true],
            'proyectos' => ['route' => 'proyectos.index', 'ready' => true],
            'tareas' => ['route' => 'tareas.index', 'ready' => true],
            'bugs' => ['route' => 'bugs.index', 'ready' => true],
            'actualizaciones' => ['route' => 'actualizaciones', 'ready' => true],
            'monitoreo' => ['route' => 'monitoreo', 'ready' => true],
            'incidentes' => ['route' => 'incidentes', 'ready' => true],
            'notificaciones' => ['route' => null, 'ready' => false],
            'ia analisis' => ['route' => 'asistente.index', 'ready' => true],
            'archivos' => ['route' => 'archivos', 'ready' => false],
            'configuracion' => ['route' => 'configuracion', 'ready' => true],
            'usuarios' => ['route' => 'usuarios', 'ready' => true],
            'actividad' => ['route' => 'actividad', 'ready' => true],
        ];

        $registeredText = $registered->isEmpty()
            ? '- No hay secciones registradas para DevControl.'
            : $registered->values()->map(fn ($name) => "- {$name}")->implode("\n");
        $missingText = $missing->isEmpty()
            ? '- No faltan secciones del mapa esperado.'
            : $missing->map(fn ($description, $name) => "- {$name}: {$description}")->implode("\n");
        $pendingModules = collect($moduleStatus)
            ->filter(fn ($module) => ! $module['ready'] || ! $module['route'] || ! Route::has($module['route']))
            ->keys();
        $pendingText = $pendingModules->map(fn ($name) => '- '.(self::EXPECTED_SECTIONS[$name] ?? $name))->implode("\n");

        $message = "Auditoría de secciones de DevControl\n\n"
            ."Secciones registradas en la base de datos ({$registered->count()}):\n{$registeredText}\n\n"
            ."Secciones del mapa esperado que faltan ({$missing->count()}):\n{$missingText}\n\n"
            ."Módulos que todavía no están operativos:\n{$pendingText}\n\n"
            .'Conclusión: una sección registrada en la base de datos representa contexto, no confirma que el módulo exista o esté implementado en el código. '
            .'Para confirmarlo hacen falta su ruta, vista, controlador y operaciones funcionales.';

        return [
            'message' => [
                'role' => 'assistant',
                'content' => $message,
            ],
            'navigation' => route('proyectos.index'),
        ];
    }

    private function detailGeneratedBugs(): array
    {
        if (! Schema::hasTable('proyectos') || ! Schema::hasTable('bugs')) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No puedo detallar los bugs porque faltan las tablas necesarias.',
                ],
                'navigation' => null,
            ];
        }

        $proyecto = Proyecto::with([
            'secciones.funcionalidades',
            'tareas',
            'bugs',
        ])->whereRaw('LOWER(nombre) = ?', ['devcontrol'])->first();

        if (! $proyecto) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No encontré el proyecto DevControl para actualizar sus bugs.',
                ],
                'navigation' => null,
            ];
        }

        $findings = collect($this->detectProjectFindings($proyecto))
            ->keyBy('title');
        $updated = 0;
        $generatedBugs = $proyecto->bugs
            ->filter(fn (Bug $bug) => str_contains($bug->descripcion, '[DevControl::scan]'));

        DB::transaction(function () use ($generatedBugs, $findings, &$updated) {
            foreach ($generatedBugs as $bug) {
                $finding = $findings->get($bug->titulo);

                if ($finding && $bug->descripcion !== $finding['description']) {
                    $bug->update(['descripcion' => $finding['description']]);
                    $updated++;
                }
            }
        });

        $total = $generatedBugs->count();
        $message = $total === 0
            ? 'No encontré bugs automáticos para detallar. Los bugs manuales no se modifican automáticamente.'
            : "Actualicé {$updated} de {$total} bug(s) creados por el análisis automático con contexto, evidencia, impacto y recomendación.\n\nLos bugs manuales no se modificaron.";

        return [
            'message' => [
                'role' => 'assistant',
                'content' => $message,
            ],
            'navigation' => route('bugs.index'),
        ];
    }

    private function isDiagnosticInstruction(string $text): bool
    {
        $diagnosticTerms = [
            'error',
            'problema',
            'falla',
            'fallo',
            'estado',
            'funciona',
            'operativo',
            'disponible',
            'salud',
            'diagnostico',
            'diagnosticar',
            'que esta pasando',
            'que pasa',
            'pendiente',
            'pendientes',
            'falta',
            'faltan',
            'completo',
            'completamente',
        ];

        return $this->hasApproximatePhrase($text, $diagnosticTerms);
    }

    private function normalizeInstruction(string $input): string
    {
        $text = mb_strtolower(trim($input), 'UTF-8');
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = str_replace(['/', '-', '_'], ' ', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    private function hasApproximatePhrase(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            $normalizedPhrase = $this->normalizeInstruction($phrase);

            if (str_contains($text, $normalizedPhrase)) {
                return true;
            }

            $words = explode(' ', $normalizedPhrase);
            if (count($words) > 1 && collect($words)->every(fn (string $word) => $this->hasApproximateTerm($text, [$word]))) {
                return true;
            }
        }

        return false;
    }

    private function hasApproximateTerm(string $text, array $terms): bool
    {
        $tokens = explode(' ', $text);

        foreach ($terms as $term) {
            $normalizedTerm = $this->normalizeInstruction($term);

            if (str_contains($text, $normalizedTerm)) {
                return true;
            }

            foreach (explode(' ', $normalizedTerm) as $word) {
                foreach ($tokens as $token) {
                    if ($this->wordsAreClose($token, $word)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function wordsAreClose(string $actual, string $expected): bool
    {
        if ($actual === $expected || strlen($expected) < 4) {
            return $actual === $expected;
        }

        $distance = levenshtein($actual, $expected);
        $allowedDistance = strlen($expected) >= 8 ? 2 : 1;

        return $distance <= $allowedDistance;
    }

    private function scanProjectForBugs(): array
    {
        if (! Schema::hasTable('proyectos') || ! Schema::hasTable('bugs')) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No puedo analizar el proyecto porque faltan las tablas de proyectos o bugs.',
                ],
                'navigation' => null,
            ];
        }

        $proyecto = Proyecto::with([
            'secciones.funcionalidades',
            'tareas',
            'bugs',
        ])->whereRaw('LOWER(nombre) = ?', ['devcontrol'])->first();

        if (! $proyecto) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No encontré el proyecto DevControl para analizarlo.',
                ],
                'navigation' => null,
            ];
        }

        $functionalities = $this->analyzeProjectFunctionalities($proyecto);
        $findings = array_merge($functionalities['findings'], $this->detectProjectFindings($proyecto));
        $created = 0;
        $resolved = 0;
        $tasksCreated = 0;

        DB::transaction(function () use ($proyecto, $findings, $functionalities, &$created, &$resolved, &$tasksCreated) {
            foreach ($functionalities['statuses'] as $status) {
                $status['functionality']->update(['estado' => $status['estado']]);

                if ($status['estado'] === 'Implementada') {
                    $completed = $this->completeGeneratedFunctionalityTask($proyecto, $status['functionality']);
                    $tasksCreated += $completed ? 1 : 0;
                }
            }

            $bugFindings = collect($findings)
                ->filter(fn (array $finding) => $this->isRealBugFinding($finding));
            $activeTitles = $bugFindings->pluck('title')->all();
            $generatedBugs = $proyecto->bugs
                ->filter(fn (Bug $bug) => str_contains($bug->descripcion, '[DevControl::scan]'));

            foreach ($findings as $finding) {
                if ($this->isRealBugFinding($finding)) {
                    $bug = $generatedBugs->firstWhere('titulo', $finding['title']);

                    if ($bug) {
                        $updates = ['descripcion' => $finding['description']];

                        if ($bug->estado === 'Solucionado' || $bug->estado === 'Cerrado') {
                            $updates['estado'] = 'Reportado';
                        }

                        $bug->update($updates);
                    } else {
                        $this->createGeneratedBug($proyecto, $finding);
                        $created++;
                    }
                }

                if ($this->createTaskForFinding($proyecto, $finding)) {
                    $tasksCreated++;
                }
            }

            foreach ($functionalities['tasks'] as $task) {
                if ($this->completeFunctionalityTask($proyecto, $task)
                    || $this->createTaskForFunctionality($proyecto, $task)) {
                    $tasksCreated++;
                }
            }

            foreach ($generatedBugs as $bug) {
                if (! in_array($bug->titulo, $activeTitles, true)
                    && ! in_array($bug->estado, ['Solucionado', 'Cerrado'], true)) {
                    $bug->update([
                        'estado' => 'Solucionado',
                        'descripcion' => $bug->descripcion."\n\n[DevControl::scan] Condición no detectada en el último análisis.",
                    ]);
                    $resolved++;
                }
            }
        });

        $message = "Análisis de DevControl completado.\n\n";
        $message .= $created > 0
            ? "🔴 Bugs nuevos registrados: {$created}\n"
            : "🟢 No se detectaron bugs reales nuevos.\n";
        $message .= $resolved > 0
            ? "✅ Bugs automáticos marcados como solucionados: {$resolved}\n"
            : "ℹ️ No hubo bugs automáticos para cerrar.\n";
        $message .= $tasksCreated > 0
            ? '📝 Tareas de seguimiento asignadas a '.auth()->user()->name.": {$tasksCreated}\n"
            : "📝 No se crearon tareas nuevas de seguimiento.\n";
        $message .= "\nFuncionalidades analizadas individualmente: {$functionalities['total']}.\n";
        $message .= 'Estado actualizado: las funcionalidades verificadas quedan en "Implementada"; '.
            'las que tienen evidencia parcial quedan en "En progreso" y las que no tienen evidencia quedan en "Pendiente".'."\n";
        $message .= 'Las funcionalidades faltantes, incompletas o por verificar se registran como tareas, no como bugs. '.
            'Se inspeccionaron controladores, modelos, servicios, vistas, rutas, configuración, migraciones y pruebas disponibles; todavía no se monitorean servicios externos. '.
            'Los bugs manuales no se modifican automáticamente.';

        return [
            'message' => [
                'role' => 'assistant',
                'content' => $message,
            ],
            'navigation' => route('bugs.index'),
        ];
    }

    private function isRealBugFinding(array $finding): bool
    {
        return ($finding['type'] ?? null) === 'real_bug';
    }

    private function analyzeProjectFunctionalities(Proyecto $proyecto): array
    {
        $findings = [];
        $tasks = [];
        $statuses = [];
        $total = 0;

        foreach ($proyecto->secciones as $seccion) {
            foreach ($seccion->funcionalidades as $funcionalidad) {
                $total++;
                $evidence = $this->findLocalEvidence($funcionalidad);
                $hasEvidence = $evidence->isNotEmpty();
                $isImplemented = $this->hasFunctionalImplementation($seccion, $funcionalidad, $evidence);
                $hasInProgressContext = in_array($funcionalidad->estado, ['Parcial', 'En desarrollo'], true);
                $wasExplicitlyImplemented = $funcionalidad->estado === 'Implementada';
                $newStatus = $isImplemented
                    ? 'Implementada'
                    : ($wasExplicitlyImplemented
                        ? 'Requiere revisión'
                        : ($hasInProgressContext ? 'En progreso' : 'Pendiente'));
                $statuses[] = [
                    'functionality' => $funcionalidad,
                    'estado' => $newStatus,
                ];

                $context = "{$seccion->nombre} / {$funcionalidad->nombre}";
                $taskDescription = $isImplemented
                    ? $this->functionalityTaskDescription($funcionalidad, true, $evidence)
                    : ($hasEvidence
                    ? $this->functionalityTaskDescription($funcionalidad, false, $evidence)
                    : $this->functionalityTaskDescription($funcionalidad, false, collect()));

                if (! $isImplemented) {
                    $tasks[] = [
                        'title' => "Completar funcionalidad: {$funcionalidad->nombre}",
                        'description' => $taskDescription,
                        'priority' => $hasInProgressContext ? 'Media' : 'Alta',
                        'completed' => false,
                        'in_progress' => $hasInProgressContext,
                        'seccion_id' => $seccion->id,
                        'funcionalidad_id' => $funcionalidad->id,
                        'evidence' => $evidence->implode("\n"),
                    ];
                }

                if (! $hasEvidence && ! $isImplemented) {
                    $findings[] = [
                        'title' => "[IA] Funcionalidad sin evidencia local: {$funcionalidad->nombre}",
                        'description' => $this->formatFindingDescription(
                            'Funcionalidad sin evidencia local',
                            $proyecto,
                            $context,
                            "No se encontró evidencia suficiente de \"{$funcionalidad->nombre}\" en el código local de DevControl.",
                            'Cada funcionalidad registrada debe tener rutas, controladores, modelos, vistas o pruebas que permitan verificar su avance.',
                            'Implementar la funcionalidad o relacionar evidencia concreta del código y sus pruebas.',
                            "Estado anterior: {$funcionalidad->estado}\nArchivos revisados: app, resources y routes.\nEvidencia: no encontrada."
                        ),
                        'priority' => 'Alta',
                        'seccion_id' => $seccion->id,
                        'funcionalidad_id' => $funcionalidad->id,
                    ];
                }
            }
        }

        return compact('findings', 'tasks', 'statuses', 'total');
    }

    private function findLocalEvidence($funcionalidad)
    {
        $name = $this->normalizeInstruction($funcionalidad->nombre);
        $keywords = collect(preg_split('/\s+/', $this->normalizeInstruction(
            "{$funcionalidad->nombre} {$funcionalidad->descripcion}"
        )))
            ->filter(fn (string $word) => strlen($word) >= 5)
            ->reject(fn (string $word) => in_array($word, [
                'crear', 'crear', 'editar', 'eliminar', 'mostrar', 'registrar',
                'permitir', 'proyecto', 'proyectos', 'funcionalidad', 'informacion',
            ], true))
            ->unique()
            ->values();

        $aliases = match (true) {
            str_contains($name, 'notificar tareas') => [
                'devcontrolalertservice',
                'app models tarea php',
                'nueva tarea registrada',
                'tarea actualizada',
                'alertas tareas',
            ],
            str_contains($name, 'notificar bugs') => [
                'devcontrolalertservice',
                'app models bug php',
                'nuevo bug registrado',
                'bug actualizado',
            ],
            str_contains($name, 'notificar incidentes') => [
                'devcontrolalertservice',
                'app models incidente php',
                'nuevo incidente registrado',
                'incidente actualizado',
            ],
            str_contains($name, 'configurar notificaciones') => [
                'configuracioncontroller',
                'alertas activas',
                'correo alertas',
                'devcontrolalertservice',
            ],
            default => [],
        };

        $keywords = $keywords->merge($aliases)->unique()->values();

        if ($keywords->isEmpty()) {
            return collect();
        }

        $directories = [
            base_path('app'),
            base_path('resources'),
            base_path('routes'),
            base_path('config'),
            base_path('database'),
            base_path('tests'),
        ];
        $evidence = collect();

        foreach ($directories as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                $contents = $this->normalizeInstruction($file->getContents());
                $path = $this->normalizeInstruction(
                    str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname())
                );
                $matches = $keywords->filter(
                    fn (string $keyword) => str_contains($contents, $keyword) || str_contains($path, $keyword)
                )->count();

                if ($matches >= min(2, $keywords->count())) {
                    $evidence->push($file->getRelativePathname());
                }

                if ($evidence->count() >= 5) {
                    return $evidence->unique()->values();
                }

            }
        }

        return $evidence->unique()->values();
    }

    private function hasFunctionalImplementation($seccion, $funcionalidad, $evidence): bool
    {
        $section = $this->normalizeInstruction($seccion->nombre);
        $name = $this->normalizeInstruction($funcionalidad->nombre);

        $requirements = match (true) {
            $section === 'bugs' => [
                'routes web php',
                'bugcontroller',
                str_contains($name, 'registrar') ? 'bugs store' : 'bugs index',
                str_contains($name, 'editar') ? 'bugs update' : 'bugcontroller',
                str_contains($name, 'eliminar') ? 'bugs destroy' : 'bugcontroller',
            ],
            $section === 'tareas' => [
                'routes web php',
                'tareascontroller',
                'dashboard tareas',
            ],
            $section === 'actualizaciones' => [
                'routes web php',
                'actualizacioncontroller',
                str_contains($name, 'registrar') ? 'actualizaciones store' : 'actualizaciones',
            ],
            $section === 'incidentes' => [
                'routes web php',
                'incidentecontroller',
                'incidentes',
            ],
            $section === 'usuarios' => [
                'routes web php',
                'usuariocontroller',
                str_contains($name, 'crear') ? 'usuarios store' : 'usuarios',
            ],
            $section === 'acceso' => [
                'routes web php',
                str_contains($name, 'registro') ? 'registercontroller' : 'loginccontroller',
            ],
            $section ===             'asistente ia nexus' => [
                'routes web php',
                'asistentecontroller',
                'dashboard asistente',
            ],
            'notificaciones' => match (true) {
                str_contains($name, 'notificar tareas') => [
                    'app models tarea php',
                    'devcontrolalertservice',
                    'nueva tarea registrada',
                    'tarea actualizada',
                ],
                str_contains($name, 'notificar bugs') => [
                    'app models bug php',
                    'devcontrolalertservice',
                    'nuevo bug registrado',
                    'bug actualizado',
                ],
                str_contains($name, 'notificar incidentes') => [
                    'app models incidente php',
                    'devcontrolalertservice',
                    'nuevo incidente registrado',
                    'incidente actualizado',
                ],
                str_contains($name, 'configurar notificaciones') => [
                    'configuracioncontroller',
                    'alertas activas',
                    'correo alertas',
                    'devcontrolalertservice',
                ],
                default => [
                    'devcontrolalertservice',
                    'mail send',
                ],
            },
            'configuracion' => [
                'configuracioncontroller',
                'configuracion blade php',
                'configuraciones',
            ],
            $section === 'dashboard' => [
                'routes web php',
                'dashboard',
                'resources views admin index blade php',
            ],
            str_contains($name, 'crear') && str_contains($name, 'proyecto'),
            str_contains($name, 'registrar') && str_contains($name, 'proyecto') => [
                'routes web php',
                'proyectos store',
            ],
            (str_contains($name, 'editar') || str_contains($name, 'modificar'))
                && str_contains($name, 'proyecto') => [
                    'routes web php',
                    'proyectos update',
                ],
            str_contains($name, 'consultar') && str_contains($name, 'proyecto') => [
                'routes web php',
                'proyectos index',
            ],
            (str_contains($name, 'registrar') || str_contains($name, 'egistrar'))
                && str_contains($name, 'tecnolog') => [
                    'app http controllers proyectocontroller php',
                    'resources views admin proyectos blade php',
                    'tecnologias',
                ],
            str_contains($name, 'control de progreso') || str_contains($name, 'progreso') => [
                'app http controllers proyectocontroller php',
                'app http controllers tareascontroller php',
                'resources views admin proyectos blade php',
                'progreso',
            ],
            str_contains($name, 'eliminar') || str_contains($name, 'archivar') => [
                'routes web php',
                'proyectos destroy',
            ],
            str_contains($name, 'listado') || str_contains($name, 'listar') => [
                'app http controllers proyectocontroller php',
                'routes web php',
                'resources views admin proyectos blade php',
                'index',
            ],
            str_contains($name, 'vista detallada') || str_contains($name, 'detalle') => [
                'app http controllers proyectocontroller php',
                'routes web php',
                'resources views admin proyectos blade php',
                'show',
            ],
            str_contains($name, 'administrar informacion') && str_contains($name, 'proyecto') => [
                'app http controllers proyectocontroller php',
                'app models proyecto php',
                'resources views admin proyectos blade php',
            ],
            str_contains($name, 'administrar tareas relacionadas') => [
                'app http controllers proyectocontroller php',
                'app models tarea php',
                'tareas',
            ],
            str_contains($name, 'administrar bugs relacionados') => [
                'app http controllers proyectocontroller php',
                'app models bug php',
                'bugs',
            ],
            str_contains($name, 'administrar actualizaciones') => [
                'app http controllers proyectocontroller php',
                'app models actualizacion php',
                'actualizaciones',
            ],
            default => [],
        };

        if ($requirements === []) {
            return false;
        }

        $contents = $this->loadLocalProjectContents();

        if ($section === 'notificaciones') {
            $notificationSignals = match (true) {
                str_contains($name, 'notificar tareas') => [
                    'app models tarea php',
                    'devcontrolalertservice',
                    'nueva tarea registrada',
                    'tarea actualizada',
                ],
                str_contains($name, 'notificar bugs') => [
                    'app models bug php',
                    'devcontrolalertservice',
                    'nuevo bug registrado',
                    'bug actualizado',
                ],
                str_contains($name, 'notificar incidentes') => [
                    'app models incidente php',
                    'devcontrolalertservice',
                    'nuevo incidente registrado',
                    'incidente actualizado',
                ],
                str_contains($name, 'configurar notificaciones') => [
                    'configuracioncontroller',
                    'alertas activas',
                    'correo alertas',
                    'devcontrolalertservice',
                ],
                default => [],
            };

            if ($notificationSignals !== []
                && collect($notificationSignals)->every(fn (string $signal) => str_contains($contents, $signal))) {
                return true;
            }
        }

        foreach ($requirements as $requirement) {
            if (! str_contains($contents, $requirement)) {
                return false;
            }
        }

        return $requirements !== [] && collect($requirements)->every(
            fn (string $requirement) => str_contains($contents, $requirement)
        );
    }

    private function functionalityTaskDescription($funcionalidad, bool $implemented, $evidence): string
    {
        $name = $this->normalizeInstruction($funcionalidad->nombre);
        $section = $this->normalizeInstruction($funcionalidad->seccion?->nombre ?? 'la seccion correspondiente');

        if (! $implemented && $section === 'notificaciones') {
            return "La sección Notificaciones todavía está en desarrollo. Para \"{$funcionalidad->nombre}\", definir el evento que debe generar la alerta, sus destinatarios, el canal de entrega y la forma de marcarla como leída. No marcarla como implementada mientras solo exista la pantalla o una ruta de demostración.";
        }

        if (! $implemented && $section === 'monitoreo') {
            return "La sección Monitoreo todavía está en desarrollo. Para \"{$funcionalidad->nombre}\", definir qué servicio o repositorio se observará, cómo se consultará su estado y cómo se registrarán errores o indisponibilidad. La vista preparada no demuestra una integración operativa.";
        }

        if (! $implemented && $section === 'archivos') {
            return "La sección Archivos es parcial. Para \"{$funcionalidad->nombre}\", conectar almacenamiento persistente, permisos, validación del archivo y la operación solicitada. Verificar carga, consulta y eliminación antes de cambiar el estado.";
        }

        if (! $implemented && $section === 'configuracion') {
            return "La sección Configuración está en desarrollo. Para \"{$funcionalidad->nombre}\", conectar el formulario con su configuración real, validar los datos y confirmar que el cambio se conserve y afecte al módulo correspondiente.";
        }

        if (! $implemented && $section === 'seguimiento') {
            return "La sección Seguimiento está en desarrollo. Para \"{$funcionalidad->nombre}\", definir qué avance se registra, de qué proyecto o proceso proviene y cómo se consulta posteriormente. No basta con mostrar una vista estática.";
        }

        if (! $implemented && $section === 'notas') {
            return "La sección Notas está en desarrollo. Para \"{$funcionalidad->nombre}\", implementar persistencia, relación con el proyecto, edición segura y validación de permisos. Verificar el ciclo completo antes de marcarla como implementada.";
        }

        if (str_contains($name, 'control de progreso') || str_contains($name, 'progreso')) {
            return $implemented
                ? 'El control de progreso ya está implementado: DevControl calcula el avance a partir de las tareas completadas y lo muestra en la vista del proyecto. La tarea queda verificada.'
                : 'Completar el control de progreso verificando el cálculo del porcentaje, la actualización al cambiar tareas y su representación visual en el proyecto.';
        }

        if (str_contains($name, 'tecnolog')) {
            return $implemented
                ? 'El registro de tecnologías ya está implementado: el proyecto almacena el campo de tecnologías y la interfaz permite capturarlo, editarlo y mostrarlo. La tarea queda verificada.'
                : 'Completar el registro de tecnologías validando que lenguaje, framework, base de datos y herramientas se guarden, se editen y se muestren correctamente.';
        }

        if (str_contains($name, 'crear') && str_contains($name, 'proyecto')) {
            return $implemented
                ? 'La creación de proyectos ya cuenta con formulario, validación, ruta y método de guardado. La tarea queda verificada.'
                : 'Completar la creación de proyectos revisando formulario, validaciones, persistencia y confirmación visual.';
        }

        if ((str_contains($name, 'editar') || str_contains($name, 'modificar'))
            && str_contains($name, 'proyecto')) {
            return $implemented
                ? 'La edición de proyectos ya cuenta con formulario, ruta de actualización, validación y persistencia. La tarea queda verificada.'
                : 'Completar la edición de proyectos revisando carga de datos, validaciones, actualización y confirmación visual.';
        }

        if (str_contains($name, 'vista detallada') || str_contains($name, 'detalle')) {
            return $implemented
                ? 'La vista detallada permite consultar la información principal del proyecto junto con sus secciones, tareas, bugs, actualizaciones e integración relacionada. La tarea queda verificada.'
                : 'Completar la vista detallada integrando la información general del proyecto con sus tareas, bugs, actualizaciones, archivos y actividad relacionada.';
        }

        if (str_contains($name, 'listado') || str_contains($name, 'listar')) {
            return $implemented
                ? 'El módulo permite consultar el listado de proyectos y abrir el detalle de cada registro. La tarea queda verificada.'
                : 'Completar el listado de proyectos incorporando la carga, estados visibles, navegación al detalle y los casos sin registros.';
        }

        if (str_contains($name, 'eliminar') || str_contains($name, 'archivar')) {
            return $implemented
                ? 'El proyecto cuenta con una acción para retirarlo del flujo activo sin perder su registro e historial. La tarea queda verificada.'
                : 'Completar la baja o archivado de proyectos definiendo la confirmación, el estado resultante y la conservación del historial.';
        }

        if ($implemented) {
            return "La funcionalidad \"{$funcionalidad->nombre}\" ya tiene evidencia del flujo principal. No requiere trabajo adicional; se conserva como implementada.";
        }

        $evidenceNote = $evidence->isNotEmpty()
            ? 'Hay indicios en el código, pero falta comprobar el flujo completo y sus validaciones.'
            : 'No se encontró una ruta, controlador, modelo o vista que permita confirmar el flujo completo.';

        return "Revisar \"{$funcionalidad->nombre}\" dentro de {$funcionalidad->seccion?->nombre}. ".
            "Definir el resultado esperado, completar únicamente las partes faltantes y validar el flujo de inicio a fin antes de cambiar su estado a Implementada.\n\n".
            $evidenceNote;
    }

    private function loadLocalProjectContents(): string
    {
        $contents = '';

        foreach ([
            base_path('app'),
            base_path('resources'),
            base_path('routes'),
            base_path('config'),
            base_path('database'),
            base_path('tests'),
        ] as $directory) {
            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                $contents .= "\n".$this->normalizeInstruction($file->getContents());
                $contents .= "\n".$this->normalizeInstruction(
                    str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname())
                );
            }
        }

        return $contents;
    }

    private function detectProjectFindings(Proyecto $proyecto): array
    {
        $findings = [];
        $hoy = now()->startOfDay();

        foreach ($proyecto->secciones as $seccion) {
            if ($seccion->funcionalidades->isEmpty()) {
                $findings[] = [
                    'title' => "[IA] Sección registrada sin funcionalidades: {$seccion->nombre}",
                    'description' => $this->formatFindingDescription(
                        'Sección registrada sin funcionalidades',
                        $proyecto,
                        $seccion->nombre,
                        'La sección está registrada en DevControl, pero no hay funcionalidades asociadas que permitan comprobar que exista o esté implementada en el proyecto.',
                        'Una sección debe tener funcionalidades definidas y evidencia de implementación antes de considerarse operativa.',
                        'Registrar las funcionalidades que pertenecen a esta sección o confirmar que la sección todavía no forma parte del proyecto implementado.',
                        "Registro encontrado en la tabla secciones: {$seccion->nombre}.\nFuncionalidades asociadas: 0.\nEvidencia de implementación: no disponible."
                    ),
                    'priority' => 'Media',
                ];
            }

            $funcionalidadesImplementadas = $seccion->funcionalidades
                ->where('estado', 'Implementada');

            if ($seccion->funcionalidades->isNotEmpty() && $funcionalidadesImplementadas->isEmpty()) {
                $estados = $seccion->funcionalidades
                    ->map(fn ($funcionalidad) => "{$funcionalidad->nombre} [{$funcionalidad->estado}]")
                    ->implode("\n");

                $findings[] = [
                    'title' => "[IA] Sección registrada sin evidencia de implementación: {$seccion->nombre}",
                    'description' => $this->formatFindingDescription(
                        'Sección registrada sin evidencia de implementación',
                        $proyecto,
                        $seccion->nombre,
                        'La sección está definida en la base de datos, pero ninguna de sus funcionalidades está marcada como implementada. Por eso no se puede confirmar que la sección exista funcionalmente en el proyecto.',
                        'La existencia en la tabla secciones solo demuestra que fue registrada como contexto. Para considerarla implementada debe existir al menos una funcionalidad verificada o evidencia equivalente del código.',
                        'Verificar la sección en el proyecto real, relacionar sus tareas y actualizar el estado de sus funcionalidades solo después de comprobar la implementación.',
                        "Funcionalidades registradas:\n{$estados}\n\nRegistro de la sección: confirmado.\nImplementación funcional: no confirmada."
                    ),
                    'priority' => 'Alta',
                ];
            }

            foreach ($seccion->funcionalidades as $funcionalidad) {
                if ($funcionalidad->estado === 'Requiere revisión') {
                    $findings[] = [
                        'title' => "[IA] Funcionalidad requiere revisión: {$funcionalidad->nombre}",
                        'description' => $this->formatFindingDescription(
                            'Funcionalidad marcada para revisión',
                            $proyecto,
                            "{$seccion->nombre} / {$funcionalidad->nombre}",
                            "La funcionalidad \"{$funcionalidad->nombre}\" tiene el estado \"Requiere revisión\".",
                            'Una funcionalidad debe tener un estado confirmado y una implementación verificable.',
                            'Revisar su alcance, actualizar sus tareas y cambiar el estado cuando se confirme su implementación.',
                            "Estado actual: {$funcionalidad->estado}."
                        ),
                        'priority' => 'Alta',
                    ];
                }

                if ($funcionalidad->estado === 'Implementada') {
                    $pendientes = $proyecto->tareas
                        ->where('funcionalidad_id', $funcionalidad->id)
                        ->where('estado', '!=', 'Completado')
                        ->reject(fn (Tarea $tarea) => str_contains(
                            (string) $tarea->descripcion,
                            '[DevControl::functionality-task]'
                        ));

                    if ($pendientes->isNotEmpty()) {
                        $tareasPendientes = $pendientes
                            ->map(fn (Tarea $tarea) => "- {$tarea->titulo} [{$tarea->estado}]")
                            ->implode("\n");

                        $findings[] = [
                            'title' => "[IA] Implementación inconsistente: {$funcionalidad->nombre}",
                            'description' => $this->formatFindingDescription(
                                'Implementación inconsistente',
                                $proyecto,
                                "{$seccion->nombre} / {$funcionalidad->nombre}",
                                "La funcionalidad \"{$funcionalidad->nombre}\" figura como \"Implementada\", pero todavía tiene tareas sin completar.",
                                'Una funcionalidad implementada no debería conservar tareas pendientes relacionadas.',
                                'Completar o cancelar las tareas pendientes, verificar la funcionalidad y corregir su estado si todavía no está terminada.',
                                "Tareas pendientes ({$pendientes->count()}):\n{$tareasPendientes}"
                            ),
                            'priority' => 'Alta',
                        ];
                    }
                }
            }
        }

        foreach ($proyecto->tareas as $tarea) {
            if ($tarea->fecha_limite
                && $tarea->fecha_limite->startOfDay()->lt($hoy)
                && ! in_array($tarea->estado, ['Completado', 'Cancelado'], true)) {
                $findings[] = [
                    'title' => "[IA] Tarea vencida: {$tarea->titulo}",
                    'description' => $this->formatFindingDescription(
                        'Tarea vencida',
                        $proyecto,
                        $tarea->seccion?->nombre,
                        "La tarea \"{$tarea->titulo}\" superó su fecha límite y continúa abierta.",
                        'Una tarea vencida debería estar completada, cancelada o tener una nueva fecha límite justificada.',
                        'Actualizar el avance, completar la tarea o modificar su fecha límite y dejar constancia del motivo.',
                        "Fecha límite: {$tarea->fecha_limite->format('d/m/Y')}\nEstado actual: {$tarea->estado}\nDías de retraso: {$tarea->fecha_limite->startOfDay()->diffInDays($hoy)}"
                    ),
                    'priority' => 'Alta',
                ];
            }
        }

        return $findings;
    }

    private function formatFindingDescription(
        string $type,
        Proyecto $proyecto,
        ?string $context,
        string $failure,
        string $expected,
        string $recommendation,
        string $evidence
    ): string {
        return "[DevControl::scan]\n\n"
            ."Tipo de hallazgo: {$type}\n"
            ."Proyecto: {$proyecto->nombre}\n"
            .'Contexto: '.($context ?: 'Sin sección')."\n\n"
            ."Qué falta o falla:\n{$failure}\n\n"
            ."Comportamiento esperado:\n{$expected}\n\n"
            ."Evidencia encontrada:\n{$evidence}\n\n"
            ."Recomendación:\n{$recommendation}\n\n"
            .'Origen: análisis automático de la información registrada en DevControl.';
    }

    private function createGeneratedBug(Proyecto $proyecto, array $finding): void
    {
        $lastNumber = Bug::where('folio', 'like', 'BUG-%')
            ->get()
            ->map(fn (Bug $bug) => (int) str_replace('BUG-', '', $bug->folio))
            ->max() ?? 0;

        Bug::create([
            'folio' => 'BUG-'.str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT),
            'proyecto_id' => $proyecto->id,
            'titulo' => $finding['title'],
            'descripcion' => $finding['description'],
            'prioridad' => $finding['priority'],
            'estado' => 'Reportado',
            'fecha_detectado' => now(),
        ]);
    }

    private function createTaskForFinding(Proyecto $proyecto, array $finding): bool
    {
        $marker = '[DevControl::task]';
        if (($finding['funcionalidad_id'] ?? null)
            && $proyecto->secciones
                ->flatMap(fn ($section) => $section->funcionalidades)
                ->firstWhere('id', $finding['funcionalidad_id'])?->estado === 'Implementada') {
            return false;
        }

        $existing = $proyecto->tareas
            ->first(fn (Tarea $tarea) => str_contains((string) $tarea->descripcion, $marker)
                && str_contains((string) $tarea->descripcion, $finding['title']));

        if ($existing) {
            return false;
        }

        Tarea::create([
            'proyecto_id' => $proyecto->id,
            'usuario_id' => auth()->id(),
            'seccion_id' => $finding['seccion_id'] ?? null,
            'funcionalidad_id' => $finding['funcionalidad_id'] ?? null,
            'titulo' => 'Atender hallazgo: '.str_replace('[IA] ', '', $finding['title']),
            'descripcion' => "{$marker}\nHallazgo relacionado: {$finding['title']}\n\n".
                "Esta tarea fue generada automáticamente por Nexus para dar seguimiento al análisis de DevControl.\n\n".
                $finding['description'],
            'prioridad' => $finding['priority'],
            'estado' => 'Pendiente',
            'fecha_inicio' => now()->toDateString(),
            'fecha_limite' => now()->addDays(14)->toDateString(),
        ]);

        return true;
    }

    private function createTaskForFunctionality(Proyecto $proyecto, array $task): bool
    {
        $legacyTitle = 'Verificar funcionalidad: '.str_replace('Completar funcionalidad: ', '', $task['title']);
        $existing = $proyecto->tareas
            ->first(fn (Tarea $tarea) => $tarea->funcionalidad_id === $task['funcionalidad_id']
                && (
                    str_contains((string) $tarea->descripcion, '[DevControl::functionality-task]')
                    || in_array($tarea->titulo, [$task['title'], $legacyTitle], true)
                ));

        if (! $existing) {
            $existing = $proyecto->tareas
                ->first(fn (Tarea $tarea) => in_array($tarea->titulo, [$task['title'], $legacyTitle], true));
        }

        if ($existing) {
            $existing->update([
                'seccion_id' => $task['seccion_id'],
                'funcionalidad_id' => $task['funcionalidad_id'],
                'titulo' => $task['title'],
                'descripcion' => $task['description'],
                'prioridad' => $task['priority'],
                'estado' => $existing->estado === 'Cancelado'
                    ? 'Cancelado'
                    : ($task['in_progress'] ? 'En progreso' : 'Pendiente'),
            ]);

            return false;
        }

        Tarea::create([
            'proyecto_id' => $proyecto->id,
            'usuario_id' => auth()->id(),
            'seccion_id' => $task['seccion_id'],
            'funcionalidad_id' => $task['funcionalidad_id'],
            'titulo' => $task['title'],
            'descripcion' => "[DevControl::functionality-task]\n".$task['description'],
            'prioridad' => $task['priority'],
            'estado' => $task['completed'] ? 'Completado' : ($task['in_progress'] ? 'En progreso' : 'Pendiente'),
            'fecha_inicio' => now()->toDateString(),
            'fecha_limite' => now()->addDays(14)->toDateString(),
            'fecha_completada' => $task['completed'] ? now()->toDateString() : null,
        ]);

        return true;
    }

    private function completeFunctionalityTask(Proyecto $proyecto, array $task): bool
    {
        if (! $task['completed']) {
            return false;
        }

        $existing = $proyecto->tareas
            ->first(fn (Tarea $tarea) => $tarea->funcionalidad_id === $task['funcionalidad_id']
                && ($tarea->titulo === $task['title']
                    || str_contains((string) $tarea->descripcion, '[DevControl::functionality-task]')));

        if (! $existing) {
            return false;
        }

        $existing->update([
            'estado' => 'Completado',
            'fecha_completada' => now()->toDateString(),
            'descripcion' => $task['description'],
        ]);

        return true;
    }

    private function completeGeneratedFunctionalityTask(Proyecto $proyecto, $funcionalidad): bool
    {
        $task = $proyecto->tareas
            ->first(fn (Tarea $tarea) => $tarea->funcionalidad_id === $funcionalidad->id
                && (
                    str_contains((string) $tarea->descripcion, '[DevControl::functionality-task]')
                    || $tarea->titulo === "Verificar funcionalidad: {$funcionalidad->nombre}"
                    || $tarea->titulo === "Completar funcionalidad: {$funcionalidad->nombre}"
                )
                && $tarea->estado !== 'Completado');

        if (! $task) {
            $legacyTitles = [
                "Verificar funcionalidad: {$funcionalidad->nombre}",
                "Completar funcionalidad: {$funcionalidad->nombre}",
            ];
            $task = $proyecto->tareas
                ->first(fn (Tarea $tarea) => in_array($tarea->titulo, $legacyTitles, true)
                    && $tarea->estado !== 'Completado');
        }

        if (! $task) {
            return false;
        }

        $task->update([
            'seccion_id' => $funcionalidad->seccion_id,
            'funcionalidad_id' => $funcionalidad->id,
            'estado' => 'Completado',
            'fecha_completada' => now()->toDateString(),
            'descripcion' => (string) $task->descripcion."\n\n".
                'Nexus verificó que la funcionalidad relacionada está implementada.',
        ]);

        return true;
    }

    private function createTaskFromInstruction(string $input): array
    {
        if (! Schema::hasTable('tareas') || ! Schema::hasTable('proyectos')) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No puedo crear la tarea porque faltan las tablas necesarias de proyectos o tareas.',
                ],
                'navigation' => null,
            ];
        }

        $proyecto = Proyecto::whereRaw('LOWER(nombre) = ?', ['devcontrol'])->first();

        if (! $proyecto) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => 'No puedo crear la tarea porque no encontré un proyecto llamado "DevControl". Primero crea ese proyecto o indícame el nombre exacto.',
                ],
                'navigation' => null,
            ];
        }

        $hoy = now()->startOfDay();
        $fechaLimite = $hoy->copy()->addMonths(3);

        $tarea = Tarea::create([
            'proyecto_id' => $proyecto->id,
            'usuario_id' => auth()->id(),
            'seccion_id' => null,
            'funcionalidad_id' => null,
            'titulo' => 'IA conectada al proyecto',
            'descripcion' => 'Integrar el asistente de IA con el contexto y los módulos operativos de DevControl para interpretar instrucciones de forma segura.',
            'prioridad' => 'Alta',
            'estado' => 'En progreso',
            'fecha_inicio' => $hoy->toDateString(),
            'fecha_limite' => $fechaLimite->toDateString(),
            'fecha_completada' => null,
        ]);

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Tarea creada correctamente:\n\n{$tarea->titulo}\nProyecto: {$proyecto->nombre}\nPrioridad: Alta\nEstado: En progreso\nSección: Sin sección\nFuncionalidad: Sin funcionalidad\nInicio: {$hoy->format('d/m/Y')}\nFecha límite: {$fechaLimite->format('d/m/Y')}",
            ],
            'navigation' => route('tareas.index'),
        ];
    }

    private function requestedModule(string $text): ?string
    {
        $aliases = [
            'dashboard' => 'dashboard',
            'inicio' => 'dashboard',
            'proyecto' => 'proyectos',
            'proyectos' => 'proyectos',
            'tarea' => 'tareas',
            'tareas' => 'tareas',
            'bug' => 'bugs',
            'bugs' => 'bugs',
            'actualizacion' => 'actualizaciones',
            'actualizaciones' => 'actualizaciones',
            'archivo' => 'archivos',
            'archivos' => 'archivos',
            'seguimiento' => 'seguimiento',
            'nota' => 'notas',
            'notas' => 'notas',
            'usuario' => 'usuarios',
            'usuarios' => 'usuarios',
            'monitoreo' => 'monitoreo',
            'monitor' => 'monitoreo',
            'incidente' => 'incidentes',
            'incidentes' => 'incidentes',
            'notificacion' => 'notificaciones',
            'notificaciones' => 'notificaciones',
            'configuracion' => 'configuracion',
            'ajustes' => 'configuracion',
            'actividad' => 'actividad',
            'historial' => 'actividad',
            'analisis' => 'ia analisis',
            'asistente' => 'ia analisis',
            'ia' => 'ia analisis',
        ];

        $navigationWords = [
            'ir', 'abre', 'abrir', 'navega', 'navegar', 'mostrar', 'muestra',
            'llevarme', 'lleva', 'ver', 'entra', 'entrar', 'consulta', 'consultar',
            'ensename', 'quiero', 'necesito', 'dame', 'muestreme', 'revisa',
            'revisar', 'lista', 'listar', 'administrar', 'gestiona', 'gestionar',
            'hay', 'quienes', 'cuantos', 'informacion',
        ];

        foreach ($aliases as $term => $module) {
            $hasNavigationWord = $this->hasApproximateTerm($text, $navigationWords);

            if ($hasNavigationWord && $this->hasApproximateTerm($text, [$term])) {
                return $module;
            }
        }

        return null;
    }

    private function navigateTo(string $module): array
    {
        $definition = self::MODULES[$module];

        if (! $definition['ready']) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => "No te llevaré a {$definition['label']} porque está marcada como no operativa. {$definition['reason']}",
                ],
                'navigation' => null,
            ];
        }

        if (! Route::has($definition['route'])) {
            return [
                'message' => [
                    'role' => 'assistant',
                    'content' => "No puedo abrir {$definition['label']}: su ruta no está registrada.",
                ],
                'navigation' => null,
            ];
        }

        return [
            'message' => [
                'role' => 'assistant',
                'content' => "Claro, te llevo a {$definition['label']} para que puedas revisarlo.",
            ],
            'navigation' => route($definition['route']),
        ];
    }

    private function diagnosticMessage(): string
    {
        $status = collect($this->moduleStatus())
            ->map(fn ($module) => ($module['ready'] ? '🟢' : '🔴').' '.$module['label'].': '.($module['ready'] ? 'operativa' : $module['reason']))
            ->implode("\n");

        $tables = [
            'proyectos' => Schema::hasTable('proyectos'),
            'tareas' => Schema::hasTable('tareas'),
            'bugs' => Schema::hasTable('bugs'),
            'actualizaciones' => Schema::hasTable('actualizaciones'),
        ];

        $database = collect($tables)
            ->map(fn ($exists, $table) => ($exists ? '🟢' : '🔴')." tabla {$table}")
            ->implode("\n");

        $counts = collect([
            'proyectos' => Proyecto::class,
            'tareas' => Tarea::class,
            'bugs' => Bug::class,
        ])->map(function (string $model, string $table) {
            return Schema::hasTable($table) ? $model::count() : 'no disponible';
        });

        return "Estado de DevControl:\n\n{$status}\n\nBase de datos:\n{$database}\n\nRegistros: ".
            $counts['proyectos'].' proyectos, '.$counts['tareas'].' tareas y '.$counts['bugs'].' bugs.';
    }

    private function moduleStatus(): array
    {
        return collect(self::MODULES)->map(function (array $module) {
            $module['url'] = Route::has($module['route']) && $module['ready']
                ? route($module['route'])
                : null;

            return $module;
        })->values()->all();
    }
}
