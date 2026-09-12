<?php

namespace App\Http\Controllers;

use App\Models\Actualizacion;
use App\Models\Proyecto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class ProyectoController extends Controller
{
    /**
     * Mostrar todos los proyectos.
     */
    public function index()
    {
        $proyectos = Proyecto::latest()->paginate(2);

        /*
        |--------------------------------------------------------------------------
        | Seleccionar el primer proyecto
        |--------------------------------------------------------------------------
        */

        $proyecto = $proyectos->first();

        /*
        |--------------------------------------------------------------------------
        | Cargar información del proyecto
        |--------------------------------------------------------------------------
        */

        if ($proyecto) {
            $relaciones = ['secciones', 'integracionGithub'];

            foreach (['tareas', 'bugs', 'actualizaciones'] as $relacion) {
                if (Schema::hasTable($this->tablaRelacionada($relacion))) {
                    $relaciones[] = $relacion;
                }
            }

            $proyecto->load($relaciones);
        }

        /*
        |--------------------------------------------------------------------------
        | Variables para la vista
        |--------------------------------------------------------------------------
        */

        $usuario = $this->obtenerUsuario();

        $tareas = $proyecto && Schema::hasTable('tareas')
            ? $proyecto->tareas
            : collect();

        $bugs = $proyecto && Schema::hasTable('bugs')
            ? $proyecto->bugs
            : collect();

        $actualizaciones = $proyecto && Schema::hasTable('actualizaciones')
            ? $proyecto->actualizaciones
            : collect();

        $secciones = $proyecto
            ? $proyecto->secciones
            : collect();

        $integracionGithub = $proyecto
            ? $proyecto->integracionGithub
            : null;

        /*
        |--------------------------------------------------------------------------
        | Actividades
        |--------------------------------------------------------------------------
        |
        | Por ahora utilizamos las actualizaciones como actividad del proyecto.
        | Esto permite que la vista funcione aunque todavía no exista una
        | relación independiente de actividades.
        |
        */

        $actividades = $actualizaciones;

        /*
        |--------------------------------------------------------------------------
        | Archivos y notas
        |--------------------------------------------------------------------------
        |
        | Si todavía no existen estas relaciones en el modelo Proyecto,
        | enviamos colecciones vacías para evitar errores en Blade.
        |
        */

        $archivos = collect();

        $notas = collect();

        /*
        |--------------------------------------------------------------------------
        | Estadísticas
        |--------------------------------------------------------------------------
        */

        $estadisticas = $this->obtenerEstadisticas($proyecto);

        return view('admin.proyectos', compact(
            'proyecto',
            'proyectos',
            'estadisticas',
            'usuario',
            'actividades',
            'tareas',
            'bugs',
            'actualizaciones',
            'secciones',
            'integracionGithub',
            'archivos',
            'notas'
        ));
    }

    /**
     * Mostrar un proyecto específico.
     */
    public function show(Proyecto $proyecto)
    {
        /*
        |--------------------------------------------------------------------------
        | Cargar relaciones
        |--------------------------------------------------------------------------
        */

        $proyecto->load([
            'secciones.funcionalidades',
            'integracionGithub',
        ]);

        foreach ([
            'tareas' => 'tareas',
            'bugs' => 'bugs',
            'actualizaciones' => 'actualizaciones',
        ] as $relacion => $tabla) {
            if (Schema::hasTable($tabla)) {
                $proyecto->load($relacion);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Lista de proyectos
        |--------------------------------------------------------------------------
        */

        $proyectos = Proyecto::latest()->paginate(2);

        /*
        |--------------------------------------------------------------------------
        | Variables para la vista
        |--------------------------------------------------------------------------
        */

        $usuario = $this->obtenerUsuario();

        $tareas = Schema::hasTable('tareas')
            ? $proyecto->tareas
            : collect();

        $bugs = Schema::hasTable('bugs')
            ? $proyecto->bugs
            : collect();

        $actualizaciones = Schema::hasTable('actualizaciones')
            ? $proyecto->actualizaciones
            : collect();

        $secciones = $proyecto->secciones;

        $integracionGithub = $proyecto->integracionGithub;

        /*
        |--------------------------------------------------------------------------
        | Actividades
        |--------------------------------------------------------------------------
        */

        $actividades = $actualizaciones;

        /*
        |--------------------------------------------------------------------------
        | Archivos y notas
        |--------------------------------------------------------------------------
        */

        $archivos = collect();

        $notas = collect();

        /*
        |--------------------------------------------------------------------------
        | Estadísticas
        |--------------------------------------------------------------------------
        */

        $estadisticas = $this->obtenerEstadisticas($proyecto);

        return view('admin.proyectos', compact(
            'proyecto',
            'proyectos',
            'estadisticas',
            'usuario',
            'actividades',
            'tareas',
            'bugs',
            'actualizaciones',
            'secciones',
            'archivos',
            'notas'
        ));
    }

    /**
     * Crear proyecto.
     */
    public function store(Request $request)
    {
        $validado = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
            ],

            'descripcion' => [
                'nullable',
                'string',
            ],
            'contexto' => ['nullable', 'string'],
            'objetivo' => ['nullable', 'string'],
            'tecnologias' => ['nullable', 'string'],
            'reglas' => ['nullable', 'string'],
            'repositorio_url' => ['nullable', 'url', 'max:500'],
            'secciones' => ['nullable', 'array'],
            'secciones.*.nombre' => ['required', 'string', 'max:150'],
            'secciones.*.descripcion' => ['nullable', 'string'],
            'secciones.*.funcionalidades' => ['nullable', 'array'],
            'secciones.*.funcionalidades.*.nombre' => ['required', 'string', 'max:180'],
            'secciones.*.funcionalidades.*.descripcion' => ['nullable', 'string'],
            'secciones.*.funcionalidades.*.estado' => [
                'required',
                'in:Pendiente,En desarrollo,Parcial,Implementada,Desconocida,Requiere revisión',
            ],

            'fecha_inicio' => [
                'required',
                'date',
            ],

            'fecha_meta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio',
            ],

            'estado' => [
                'required',
                'in:Activo,Pausado,Completado,Cancelado',
            ],

            'progreso' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Todo proyecto nuevo comienza en 0%
        |--------------------------------------------------------------------------
        */

        $validado['progreso'] = 0;

        /*
        |--------------------------------------------------------------------------
        | Crear proyecto
        |--------------------------------------------------------------------------
        */

        $secciones = $validado['secciones'] ?? [];
        unset($validado['secciones']);

        DB::transaction(function () use ($validado, $secciones) {
            $proyecto = Proyecto::create($validado);
            $this->guardarSecciones($proyecto, $secciones);
            $this->guardarIntegracionGithub($proyecto);
        });

        /*
        |--------------------------------------------------------------------------
        | Regresar a proyectos
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->route('proyectos.index')
            ->with(
                'success',
                'Proyecto creado correctamente.'
            );
    }

    /**
     * Actualizar proyecto.
     */
    public function update(
        Request $request,
        Proyecto $proyecto
    ) {
        $validado = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
            ],

            'descripcion' => [
                'nullable',
                'string',
            ],
            'contexto' => ['nullable', 'string'],
            'objetivo' => ['nullable', 'string'],
            'tecnologias' => ['nullable', 'string'],
            'reglas' => ['nullable', 'string'],
            'repositorio_url' => ['nullable', 'url', 'max:500'],
            'secciones' => ['nullable', 'array'],
            'secciones.*.nombre' => ['required', 'string', 'max:150'],
            'secciones.*.descripcion' => ['nullable', 'string'],
            'secciones.*.funcionalidades' => ['nullable', 'array'],
            'secciones.*.funcionalidades.*.nombre' => ['required', 'string', 'max:180'],
            'secciones.*.funcionalidades.*.descripcion' => ['nullable', 'string'],
            'secciones.*.funcionalidades.*.estado' => [
                'required',
                'in:Pendiente,En desarrollo,Parcial,Implementada,Desconocida,Requiere revisión',
            ],

            'fecha_inicio' => [
                'required',
                'date',
            ],

            'fecha_meta' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio',
            ],

            'estado' => [
                'required',
                'in:Activo,Pausado,Completado,Cancelado',
            ],

            'progreso' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Asegurar progreso válido
        |--------------------------------------------------------------------------
        */

        $validado['progreso'] = min(
            100,
            max(
                0,
                (int) ($validado['progreso'] ?? 0)
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Actualizar proyecto
        |--------------------------------------------------------------------------
        */

        $secciones = $validado['secciones'] ?? [];
        unset($validado['secciones']);

        DB::transaction(function () use ($proyecto, $validado, $secciones) {
            $proyecto->update($validado);
            $proyecto->secciones()->delete();
            $this->guardarSecciones($proyecto, $secciones);
            $this->guardarIntegracionGithub($proyecto);
        });

        /*
        |--------------------------------------------------------------------------
        | Regresar al proyecto actualizado
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->route(
                'proyectos.show',
                $proyecto->id
            )
            ->with(
                'success',
                'Proyecto actualizado correctamente.'
            );
    }

    private function guardarSecciones(Proyecto $proyecto, array $secciones): void
    {
        foreach ($secciones as $seccionIndex => $seccionData) {
            $seccion = $proyecto->secciones()->create([
                'nombre' => $seccionData['nombre'],
                'descripcion' => $seccionData['descripcion'] ?? null,
                'orden' => $seccionIndex,
            ]);

            foreach ($seccionData['funcionalidades'] ?? [] as $funcionalidadIndex => $funcionalidadData) {
                $seccion->funcionalidades()->create([
                    'nombre' => $funcionalidadData['nombre'],
                    'descripcion' => $funcionalidadData['descripcion'] ?? null,
                    'estado' => $funcionalidadData['estado'],
                    'orden' => $funcionalidadIndex,
                ]);
            }
        }
    }

    private function tablaRelacionada(string $relacion): string
    {
        return [
            'tareas' => 'tareas',
            'bugs' => 'bugs',
            'actualizaciones' => 'actualizaciones',
        ][$relacion];
    }

    public function sincronizarGithub(Proyecto $proyecto)
    {
        $integracion = $proyecto->integracionGithub;

        if (! $integracion) {
            return back()->with('error', 'Este proyecto no tiene un repositorio de GitHub configurado.');
        }

        $partes = $this->partesRepositorioGithub($integracion->repositorio_url);

        if (! $partes) {
            $integracion->update([
                'estado' => 'error',
                'ultimo_intento' => now(),
                'ultimo_error' => 'La URL debe tener el formato https://github.com/usuario/repositorio.',
            ]);

            return back()->with('error', 'La URL del repositorio de GitHub no es válida.');
        }

        $integracion->update([
            'estado' => 'sincronizando',
            'ultimo_intento' => now(),
            'ultimo_error' => null,
        ]);

        try {
            $clienteGithub = Http::acceptJson()
                ->withOptions([
                    'verify' => config('services.github.ca_bundle') ?: true,
                ])
                ->timeout(10);

            $repositorio = $clienteGithub
                ->get("https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}")
                ->throw()
                ->json();

            $commit = $clienteGithub
                ->get("https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/commits", [
                    'sha' => $repositorio['default_branch'] ?? 'main',
                    'per_page' => 1,
                ])
                ->throw()
                ->json()[0] ?? null;

            $integracion->update([
                'repositorio_propietario' => $repositorio['owner']['login'] ?? $partes['owner'],
                'repositorio_nombre' => $repositorio['name'] ?? $partes['repo'],
                'rama_principal' => $repositorio['default_branch'] ?? 'main',
                'estado' => 'sincronizado',
                'ultima_sincronizacion' => now(),
                'ultimo_commit_sha' => $commit['sha'] ?? null,
                'ultimo_commit_mensaje' => $commit['commit']['message'] ?? null,
                'ultimo_error' => null,
            ]);

            Actualizacion::create([
                'proyecto_id' => $proyecto->id,
                'titulo' => 'Repositorio sincronizado',
                'detalles' => 'DevControl sincronizó el repositorio '
                    .$integracion->repositorio_propietario.'/'
                    .$integracion->repositorio_nombre.'.',
                'commit' => $commit['sha'] ?? null,
            ]);
        } catch (ConnectionException $exception) {
            $integracion->update([
                'estado' => 'error',
                'ultimo_error' => 'No se pudo verificar el certificado SSL de PHP. '
                    .'Configura GITHUB_CA_BUNDLE con la ruta a cacert.pem.',
            ]);

            return back()->with(
                'error',
                'PHP no pudo verificar el certificado SSL de GitHub. Configura el CA bundle.'
            );
        } catch (RequestException $exception) {
            $integracion->update([
                'estado' => 'error',
                'ultimo_error' => $exception->response?->json('message') ?? 'GitHub rechazó la solicitud.',
            ]);

            return back()->with('error', 'No se pudo sincronizar el repositorio con GitHub.');
        }

        return back()->with('success', 'Repositorio sincronizado correctamente.');
    }

    public function configurarGithubManual(Proyecto $proyecto)
    {
        $integracion = $proyecto->integracionGithub;

        if (! $integracion) {
            return back()->with('error', 'Este proyecto no tiene un repositorio de GitHub configurado.');
        }

        $partes = $this->partesRepositorioGithub($integracion->repositorio_url);

        if (! $partes) {
            return back()->with('error', 'La URL del repositorio de GitHub no es válida.');
        }

        $integracion->update([
            'repositorio_propietario' => $partes['owner'],
            'repositorio_nombre' => $partes['repo'],
            'estado' => 'manual',
            'ultimo_intento' => now(),
            'ultimo_error' => 'Sincronización automática pendiente de configuración SSL o red.',
        ]);

        Actualizacion::create([
            'proyecto_id' => $proyecto->id,
            'titulo' => 'Repositorio configurado manualmente',
            'detalles' => 'DevControl configuró manualmente el repositorio '
                .$partes['owner'].'/'.$partes['repo'].'.',
        ]);

        return back()->with('success', 'Repositorio configurado manualmente.');
    }

    public function analizarGithub(Proyecto $proyecto)
    {
        $integracion = $proyecto->integracionGithub;
        $partes = $integracion ? $this->partesRepositorioGithub($integracion->repositorio_url) : null;

        if (! $partes) {
            return back()->with('error', 'Configura una URL válida de GitHub antes de analizar el repositorio.');
        }

        try {
            $cliente = $this->clienteGithub();
            $repositorio = $cliente->get("https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}")->throw()->json();
            $rama = $repositorio['default_branch'] ?? 'main';
            $arbol = $cliente->get("https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/git/trees/{$rama}", [
                'recursive' => '1',
            ])->throw()->json();
            $archivos = collect($arbol['tree'] ?? [])
                ->where('type', 'blob')
                ->pluck('path');
            $extensiones = $archivos
                ->map(fn (string $path) => strtolower(pathinfo($path, PATHINFO_EXTENSION)))
                ->filter()
                ->countBy()
                ->sortDesc()
                ->take(8)
                ->map(fn (int $count, string $extension) => "{$extension}: {$count}")
                ->implode(', ');
            $directorios = $archivos
                ->map(fn (string $path) => explode('/', $path)[0])
                ->unique()
                ->take(12)
                ->implode(', ');

            $integracion?->update([
                'repositorio_propietario' => $partes['owner'],
                'repositorio_nombre' => $partes['repo'],
                'rama_principal' => $rama,
                'estado' => 'analizado',
                'ultima_sincronizacion' => now(),
                'ultimo_error' => null,
            ]);

            return back()->with(
                'success',
                "Análisis de GitHub completado: {$archivos->count()} archivos en {$rama}. ".
                "Tecnologías detectadas por extensión: ".($extensiones ?: 'no identificadas').". ".
                "Directorios principales: ".($directorios ?: 'raíz del repositorio').'.'
            );
        } catch (ConnectionException $exception) {
            return back()->with('error', 'No se pudo conectar con GitHub para analizar el repositorio.');
        } catch (RequestException $exception) {
            return back()->with('error', $exception->response?->json('message') ?? 'GitHub rechazó el análisis del repositorio.');
        }
    }

    public function crearCommitGithub(Request $request, Proyecto $proyecto)
    {
        $datos = $request->validate([
            'mensaje' => ['required', 'string', 'max:500'],
            'ruta' => ['required', 'string', 'max:500', 'regex:/^(?!\/)(?!.*\.\.).+$/'],
            'contenido' => ['required', 'string', 'max:1000000'],
            'rama' => ['nullable', 'string', 'max:150'],
        ]);
        $token = config('services.github.token');

        if (! $token) {
            return back()->with('error', 'Configura GITHUB_TOKEN en el archivo .env para autorizar commits.');
        }

        try {
            $this->crearCommitGithubConDatos($proyecto, $datos);

            return back()->with('success', "Commit creado correctamente en {$datos['ruta']}.");
        } catch (ConnectionException $exception) {
            return back()->with('error', 'No se pudo conectar con GitHub para crear el commit.');
        } catch (RequestException $exception) {
            return back()->with('error', $exception->response?->json('message') ?? 'GitHub rechazó el commit.');
        }
    }

    /**
     * Crea o actualiza un archivo mediante la API de contenidos de GitHub.
     *
     * Este método también es utilizado por Nexus después de confirmar la acción.
     *
     * @param  array{mensaje:string,ruta:string,contenido:string,rama?:string|null}  $datos
     */
    public function crearCommitGithubConDatos(Proyecto $proyecto, array $datos): void
    {
        $integracion = $proyecto->integracionGithub;
        $partes = $integracion ? $this->partesRepositorioGithub($integracion->repositorio_url) : null;

        if (! $partes) {
            throw new \RuntimeException('Configura una URL válida de GitHub antes de crear commits.');
        }

        $rama = $datos['rama'] ?: ($integracion->rama_principal ?: 'main');
        $endpoint = "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/contents/".ltrim($datos['ruta'], '/');
        $cliente = $this->clienteGithub(true);
        $existente = $cliente->get($endpoint, ['ref' => $rama]);
        $payload = [
            'message' => $datos['mensaje'],
            'content' => base64_encode($datos['contenido']),
            'branch' => $rama,
        ];

        if ($existente->successful()) {
            $payload['sha'] = $existente->json('sha');
        } elseif ($existente->status() !== 404) {
            $existente->throw();
        }

        $commit = $cliente->put($endpoint, $payload)->throw()->json();
        $sha = $commit['commit']['sha'] ?? null;
        $integracion?->update([
            'estado' => 'sincronizado',
            'ultimo_commit_sha' => $sha,
            'ultimo_commit_mensaje' => $datos['mensaje'],
            'ultima_sincronizacion' => now(),
            'ultimo_error' => null,
        ]);
        Actualizacion::create([
            'proyecto_id' => $proyecto->id,
            'titulo' => 'Commit creado en GitHub',
            'detalles' => "Se actualizó {$datos['ruta']} en la rama {$rama}.",
            'commit' => $sha,
        ]);
    }

    /**
     * Devuelve los cambios locales seguros que pueden publicarse en el repositorio.
     *
     * @return array{files:array<int,array{path:string,status:string}>,error:?string}
     */
    public function cambiosLocalesPublicables(): array
    {
        $git = $this->rutaEjecutableGit();

        if (! $git) {
            return [
                'files' => [],
                'error' => 'No se encontró Git en el servidor. Configura GIT_BINARY en .env con la ruta completa a git.exe.',
            ];
        }

        $process = new Process(
            [$git, 'status', '--porcelain', '--untracked-files=all'],
            base_path()
        );
        $process->run();

        if (! $process->isSuccessful()) {
            return [
                'files' => [],
                'error' => trim($process->getErrorOutput()) ?: 'No se pudo consultar el estado local de Git.',
            ];
        }

        $files = [];
        foreach (preg_split('/\R/', rtrim($process->getOutput())) ?: [] as $line) {
            if (strlen($line) < 4) {
                continue;
            }

            $status = trim(substr($line, 0, 2));
            $path = preg_replace('/^\s+|\s+$/u', '', substr($line, 3)) ?? '';
            if (str_contains($path, ' -> ')) {
                $path = preg_replace('/^\s+|\s+$/u', '', strrchr($path, '>')) ?? '';
            }

            if ($path !== '' && ! $this->esRutaLocalPublicable($path)) {
                continue;
            }

            if ($path !== '') {
                $files[] = ['path' => str_replace('\\', '/', $path), 'status' => $status];
            }
        }

        return ['files' => $files, 'error' => null];
    }

    private function rutaEjecutableGit(): ?string
    {
        $configurada = config('services.github.git_binary');
        $candidatas = array_filter([
            $configurada,
            PHP_OS_FAMILY === 'Windows' ? getenv('ProgramFiles').'\Git\cmd\git.exe' : null,
            PHP_OS_FAMILY === 'Windows' ? getenv('ProgramFiles').'\Git\bin\git.exe' : null,
            PHP_OS_FAMILY === 'Windows' ? getenv('ProgramW6432').'\Git\cmd\git.exe' : null,
            PHP_OS_FAMILY === 'Windows' ? getenv('LocalAppData').'\Programs\Git\cmd\git.exe' : null,
            'git',
        ]);

        foreach ($candidatas as $candidata) {
            if ($candidata === 'git' || is_file($candidata)) {
                return $candidata;
            }
        }

        return null;
    }

    /**
     * Publica todos los cambios locales permitidos como un único commit de GitHub.
     *
     * @param  array{mensaje:string,rama?:string|null}  $datos
     */
    public function crearCommitGithubDesdeCambiosLocales(Proyecto $proyecto, array $datos): array
    {
        $integracion = $proyecto->integracionGithub;
        $partes = $integracion ? $this->partesRepositorioGithub($integracion->repositorio_url) : null;
        $cambios = $this->cambiosLocalesPublicables();

        if ($cambios['error']) {
            throw new \RuntimeException($cambios['error']);
        }

        if (! $partes) {
            throw new \RuntimeException('Configura una URL válida de GitHub antes de crear commits.');
        }

        if ($cambios['files'] === []) {
            throw new \RuntimeException('No hay cambios locales publicables en el proyecto.');
        }

        $rama = $datos['rama'] ?: ($integracion->rama_principal ?: 'main');
        $cliente = $this->clienteGithub(true);
        $cliente->get('https://api.github.com/user')->throw();
        $repositorio = $cliente->get(
            "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}"
        );

        if ($repositorio->status() === 404) {
            throw new \RuntimeException(
                "El token no puede ver el repositorio {$partes['owner']}/{$partes['repo']}. Revisa Repository access."
            );
        }

        $repositorio->throw();
        $permisos = $repositorio->json('permissions', []);
        if (($permisos['push'] ?? false) !== true) {
            throw new \RuntimeException(
                "La cuenta del token no tiene permiso de escritura en {$partes['owner']}/{$partes['repo']}."
            );
        }

        $ref = $cliente->get(
            "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/git/ref/heads/{$rama}"
        )->throw()->json();
        $baseCommitSha = $ref['object']['sha'];
        $baseCommit = $cliente->get(
            "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/git/commits/{$baseCommitSha}"
        )->throw()->json();

        $tree = [];
        foreach ($cambios['files'] as $cambio) {
            $path = $cambio['path'];
            if (str_contains($cambio['status'], 'D')) {
                $tree[] = [
                    'path' => $path,
                    'mode' => '100644',
                    'type' => 'blob',
                    'sha' => null,
                ];
                continue;
            }

            $absolutePath = base_path(str_replace('/', DIRECTORY_SEPARATOR, $path));
            if (! is_file($absolutePath)) {
                continue;
            }

            $blob = $cliente->post(
                "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/git/blobs",
                [
                    'content' => base64_encode(file_get_contents($absolutePath)),
                    'encoding' => 'base64',
                ]
            )->throw()->json();
            $tree[] = [
                'path' => $path,
                'mode' => '100644',
                'type' => 'blob',
                'sha' => $blob['sha'],
            ];
        }

        $nuevoArbol = $cliente->post(
            "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/git/trees",
            [
                'base_tree' => $baseCommit['tree']['sha'],
                'tree' => $tree,
            ]
        )->throw()->json();
        $commit = $cliente->post(
            "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/git/commits",
            [
                'message' => $datos['mensaje'],
                'tree' => $nuevoArbol['sha'],
                'parents' => [$baseCommitSha],
            ]
        )->throw()->json();
        $cliente->patch(
            "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/git/refs/heads/{$rama}",
            ['sha' => $commit['sha']]
        )->throw();

        $integracion?->update([
            'estado' => 'sincronizado',
            'ultimo_commit_sha' => $commit['sha'],
            'ultimo_commit_mensaje' => $datos['mensaje'],
            'ultima_sincronizacion' => now(),
            'ultimo_error' => null,
        ]);
        Actualizacion::create([
            'proyecto_id' => $proyecto->id,
            'titulo' => 'Cambios locales publicados en GitHub',
            'detalles' => 'Se publicaron '.count($tree)." archivos en la rama {$rama}.",
            'commit' => $commit['sha'],
        ]);

        return [
            'sha' => $commit['sha'],
            'files' => array_column($cambios['files'], 'path'),
            'branch' => $rama,
        ];
    }

    private function esRutaLocalPublicable(string $path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $path));

        foreach (['.env', 'vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/'] as $excluded) {
            $matches = $excluded === '.env'
                ? preg_match('~(^|/)\.env$~', $normalized) === 1
                : str_starts_with($normalized, $excluded);

            if ($matches) {
                return false;
            }
        }

        return preg_match('~(^|/)\.env$~', $normalized) !== 1;
    }

    private function clienteGithub(bool $authenticated = false)
    {
        $cliente = Http::acceptJson()
            ->withHeaders(['User-Agent' => 'DevControl'])
            ->withOptions(['verify' => config('services.github.ca_bundle') ?: true])
            ->timeout(20);

        return $authenticated
            ? $cliente->withToken(config('services.github.token'))
            : $cliente;
    }

    private function guardarIntegracionGithub(Proyecto $proyecto): void
    {
        if (! $proyecto->repositorio_url) {
            $proyecto->integracionGithub()->delete();

            return;
        }

        $proyecto->integracionGithub()->updateOrCreate(
            ['proveedor' => 'github'],
            [
                'repositorio_url' => $proyecto->repositorio_url,
                'estado' => 'pendiente',
                'ultimo_error' => null,
            ]
        );
    }

    private function partesRepositorioGithub(string $url): ?array
    {
        $partes = parse_url($url);
        $ruta = trim($partes['path'] ?? '', '/');
        $segmentos = explode('/', $ruta);

        if (($partes['host'] ?? null) !== 'github.com' || count($segmentos) !== 2) {
            return null;
        }

        $repo = preg_replace('/\.git$/', '', $segmentos[1]);

        return $repo ? ['owner' => $segmentos[0], 'repo' => $repo] : null;
    }

    /**
     * Eliminar proyecto.
     */
    public function destroy(Proyecto $proyecto)
    {
        /*
        |--------------------------------------------------------------------------
        | Eliminar proyecto
        |--------------------------------------------------------------------------
        */

        $proyecto->delete();

        /*
        |--------------------------------------------------------------------------
        | Regresar a proyectos
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->route('proyectos.index')
            ->with(
                'success',
                'Proyecto eliminado correctamente.'
            );
    }

    /**
     * Obtener estadísticas del proyecto.
     */
    private function obtenerEstadisticas($proyecto)
    {
        /*
        |--------------------------------------------------------------------------
        | Si no existe proyecto
        |--------------------------------------------------------------------------
        */

        if (! $proyecto) {
            return [
                'pendientes' => 0,
                'actualizaciones' => 0,
                'completados' => 0,
                'bugs' => 0,
                'progreso' => 0,
                'total_tareas' => 0,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Obtener relaciones
        |--------------------------------------------------------------------------
        */

        $tareas = Schema::hasTable('tareas')
            ? $proyecto->tareas
            : collect();

        $bugs = Schema::hasTable('bugs')
            ? $proyecto->bugs
            : collect();

        $actualizaciones = Schema::hasTable('actualizaciones')
            ? $proyecto->actualizaciones
            : collect();

        /*
        |--------------------------------------------------------------------------
        | Tareas pendientes
        |--------------------------------------------------------------------------
        */

        $pendientes = $tareas
            ->whereIn('estado', [
                'Pendiente',
                'Pendientes',
            ])
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Tareas completadas
        |--------------------------------------------------------------------------
        */

        $completados = $tareas
            ->whereIn('estado', [
                'Completado',
                'Completada',
            ])
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Total de tareas
        |--------------------------------------------------------------------------
        */

        $totalTareas = $tareas->count();

        /*
        |--------------------------------------------------------------------------
        | Calcular progreso
        |--------------------------------------------------------------------------
        */

        if ($totalTareas > 0) {

            $progreso = round(
                ($completados / $totalTareas) * 100
            );

        } else {

            /*
            | Si no existen tareas, conservar el progreso
            | que tenga registrado el proyecto.
            */

            $progreso = (int) (
                $proyecto->progreso ?? 0
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Asegurar que esté entre 0 y 100
        |--------------------------------------------------------------------------
        */

        $progreso = min(
            100,
            max(
                0,
                $progreso
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Actualizar progreso automáticamente
        |--------------------------------------------------------------------------
        */

        if (
            (int) $proyecto->progreso !==
            (int) $progreso
        ) {

            $proyecto->updateQuietly([
                'progreso' => $progreso,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Regresar estadísticas
        |--------------------------------------------------------------------------
        */

        return [
            'pendientes' => $pendientes,

            'actualizaciones' => $actualizaciones->count(),

            'completados' => $completados,

            'bugs' => $bugs->count(),

            'progreso' => $progreso,

            'total_tareas' => $totalTareas,
        ];
    }

    /**
     * Actualizar progreso de un proyecto
     * utilizando sus tareas.
     */
    private function actualizarProgresoProyecto(
        $proyectoId
    ) {
        /*
        |--------------------------------------------------------------------------
        | Buscar proyecto
        |--------------------------------------------------------------------------
        */

        $proyecto = Proyecto::find($proyectoId);

        if (! $proyecto) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Total de tareas
        |--------------------------------------------------------------------------
        */

        $totalTareas = $proyecto
            ->tareas()
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Tareas completadas
        |--------------------------------------------------------------------------
        */

        $tareasCompletadas = $proyecto
            ->tareas()
            ->whereIn('estado', [
                'Completado',
                'Completada',
            ])
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Calcular progreso
        |--------------------------------------------------------------------------
        */

        if ($totalTareas === 0) {

            $progreso = 0;

        } else {

            $progreso = round(
                ($tareasCompletadas / $totalTareas) * 100
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Asegurar rango 0 - 100
        |--------------------------------------------------------------------------
        */

        $progreso = min(
            100,
            max(
                0,
                $progreso
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Guardar progreso
        |--------------------------------------------------------------------------
        */

        $proyecto->updateQuietly([
            'progreso' => $progreso,
        ]);
    }

    /**
     * Obtener información del usuario autenticado.
     *
     * La vista necesita:
     *
     * $usuario['nombre']
     * $usuario['rol']
     * $usuario['foto']
     */
    private function obtenerUsuario()
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Si no existe usuario autenticado
        |--------------------------------------------------------------------------
        */

        if (! $user) {

            return [
                'nombre' => 'Jesús Guerra',
                'rol' => 'Desarrollador',
                'foto' => asset(
                    'storage/images/jesus-guerra.jpg'
                ),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Regresar información del usuario
        |--------------------------------------------------------------------------
        */

        return [
            'nombre' => $user->nombre
                ?? $user->name
                ?? 'Jesús Guerra',

            'rol' => $user->rol
                ?? 'Desarrollador',

            'foto' => $user->foto
                ? asset('storage/'.$user->foto)
                : asset(
                    'storage/images/jesus-guerra.jpg'
                ),
        ];
    }
}
