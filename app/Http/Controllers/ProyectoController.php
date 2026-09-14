<?php

namespace App\Http\Controllers;

use App\Models\Actualizacion;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Models\Configuracion;
use App\Models\NexusGithubAnalysis;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

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

        $archivos = $proyecto
            ? $proyecto->nexusCodeFiles()->where('status', 'active')->latest()->get()
            : collect();

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

    public function importarProyecto(Request $request)
    {
        $datos = $request->validate([
            'repositorio_url' => ['required', 'url', 'max:500'],
        ]);
        $partes = $this->partesRepositorioGithub($datos['repositorio_url']);

        if (! $partes) {
            return back()->withInput()->with('error', 'La URL debe ser un repositorio válido de GitHub.');
        }

        $proyecto = Proyecto::create([
            'nombre' => $partes['repo'],
            'repositorio_url' => $datos['repositorio_url'],
            'fecha_inicio' => today(),
            'estado' => 'Activo',
            'progreso' => 0,
        ]);
        $this->guardarIntegracionGithub($proyecto);
        NexusGithubAnalysis::create([
            'proyecto_id' => $proyecto->id,
            'status' => 'pending',
            'branch' => 'main',
        ]);

        $respuesta = $this->importarGithub($proyecto);
        if ($respuesta->getSession()->has('error')) {
            $proyecto->delete();
        }

        return $respuesta;
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

    /**
     * Importa la documentación y la estructura de un repositorio existente.
     */
        public function importarGithub(Proyecto $proyecto)
        {
            $integracion = $proyecto->integracionGithub;
            $partes = $integracion ? $this->partesRepositorioGithub($integracion->repositorio_url) : null;
            if (! $partes) {
                return back()->with('error', 'Configura una URL válida de GitHub antes de importar el proyecto.');
            }

            try {
                $cliente = $this->clienteGithub((bool) config('services.github.token'));
                $repositorio = $cliente
                    ->get("https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}")
                    ->throw()
                    ->json();
                $rama = $repositorio['default_branch'] ?? 'main';
                $arbol = $cliente
                    ->get("https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/git/trees/{$rama}", ['recursive' => '1'])
                    ->throw()
                    ->json();
                $archivos = collect($arbol['tree'] ?? [])
                    ->where('type', 'blob')
                    ->pluck('path')
                    ->filter(fn (string $path): bool => ! str_starts_with($path, '.git/'));
                $readme = $this->leerReadmeGithub($cliente, $partes, $rama);
                $datos = $this->extraerDocumentacionGithub($readme);
                $analisisCodigo = $this->analizarCodigoGithub($cliente, $partes, $rama, $archivos);
                $secciones = $this->combinarSeccionesRepositorio(
                    $this->seccionesDesdeRepositorio($readme, $archivos),
                    $analisisCodigo
                );
                $secciones = $this->combinarSeccionesRepositorio(
                    $secciones,
                    $this->seccionesFuncionalesDesdeRepositorio($cliente, $partes, $rama, $archivos)
                );
                $datos['descripcion'] = $datos['descripcion'] ?: ($repositorio['description'] ?? '');
                $datos['contexto'] = $datos['contexto'] ?: $this->contextoDesdeCodigo($analisisCodigo, $archivos, $readme, $repositorio['name'] ?? $partes['repo']);
                $datos['objetivo'] = $datos['objetivo'] ?: $this->objetivoDesdeCodigo($analisisCodigo, $datos['contexto']);
                $datos['descripcion'] = $datos['descripcion'] ?: $this->descripcionDesdeContexto($datos['contexto']);
                $datos['tecnologias'] = $datos['tecnologias'] ?: $this->tecnologiasDesdeCodigo($archivos);

                DB::transaction(function () use ($proyecto, $integracion, $repositorio, $partes, $rama, $datos, $secciones, $archivos): void {
                    $proyecto->tareas()
                        ->where('titulo', 'like', 'Implementado:%')
                        ->delete();
                    $proyecto->update([
                        'nombre' => $datos['nombre'] ?: ($repositorio['name'] ?? $partes['repo']),
                        'descripcion' => $datos['descripcion'] ?: $proyecto->descripcion,
                        'contexto' => $datos['contexto'] ?: $proyecto->contexto,
                        'objetivo' => $datos['objetivo'] ?: $proyecto->objetivo,
                        'tecnologias' => $datos['tecnologias'] ?: $proyecto->tecnologias,
                        'repositorio_url' => $repositorio['html_url'] ?? $integracion->repositorio_url,
                    ]);
                    $proyecto->secciones()->delete();
                    foreach ($secciones as $seccionIndex => $seccionData) {
                        $seccion = $proyecto->secciones()->create([
                            'nombre' => $seccionData['nombre'],
                            'descripcion' => $seccionData['descripcion'],
                            'orden' => $seccionIndex,
                        ]);
                        foreach ($seccionData['funcionalidades'] as $funcionalidadIndex => $funcionalidad) {
                            $nombreFuncionalidad = is_array($funcionalidad) ? $funcionalidad['nombre'] : $funcionalidad;
                            $descripcionFuncionalidad = is_array($funcionalidad)
                                ? $funcionalidad['descripcion']
                                : 'Capacidad detectada en el código fuente del módulo.';
                            $registro = $seccion->funcionalidades()->create([
                                'nombre' => $nombreFuncionalidad,
                                'descripcion' => $descripcionFuncionalidad,
                                'estado' => 'Implementada',
                                'orden' => $funcionalidadIndex,
                            ]);
                            $titulo = "Implementado: {$nombreFuncionalidad}";
                            Tarea::create([
                                'proyecto_id' => $proyecto->id,
                                'seccion_id' => $seccion->id,
                                'funcionalidad_id' => $registro->id,
                                'titulo' => $titulo,
                                'descripcion' => $descripcionFuncionalidad,
                                'prioridad' => 'Media',
                                'estado' => 'Completado',
                                'fecha_completada' => today(),
                            ]);
                        }
                    }

                    $integracion->update([
                        'repositorio_propietario' => $repositorio['owner']['login'] ?? $partes['owner'],
                        'repositorio_nombre' => $repositorio['name'] ?? $partes['repo'],
                        'rama_principal' => $rama,
                        'estado' => 'sincronizado',
                        'ultima_sincronizacion' => now(),
                        'ultimo_error' => null,
                    ]);
                    $this->guardarIntegracionGithub($proyecto->fresh());
                });

                return back()->with('success', "Proyecto importado: se analizaron {$archivos->count()} archivos, {$secciones->count()} secciones y funcionalidades detectadas en el código.");
            } catch (ConnectionException $exception) {
                return back()->with('error', 'No se pudo conectar con GitHub para importar el proyecto.');
            } catch (RequestException $exception) {
                return back()->with('error', $exception->response?->json('message') ?? 'GitHub rechazó la importación.');
            } catch (Throwable $exception) {
                report($exception);

                return back()->with('error', 'La importación del repositorio no pudo completarse. Revisa los registros de Laravel.');
            }
        }

        private function leerReadmeGithub($cliente, array $partes, string $rama): string
        {
            foreach (['README.md', 'readme.md', 'README.MD'] as $nombre) {
                $respuesta = $cliente->get("https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/contents/{$nombre}", ['ref' => $rama]);
                if ($respuesta->successful()) {
                    return base64_decode((string) $respuesta->json('content'), true) ?: '';
                }
            }

            return '';
        }

        private function extraerDocumentacionGithub(string $readme): array
        {
            $lineas = preg_split('/\R/', $readme) ?: [];
            $secciones = [];
            $actual = null;
            foreach ($lineas as $linea) {
                if (preg_match('/^#{1,3}\s+(.+)$/', trim($linea), $coincidencia)) {
                    $actual = strtolower(trim($coincidencia[1]));
                    $secciones[$actual] = [];
                    continue;
                }
                if ($actual) {
                    $secciones[$actual][] = trim($linea);
                }
            }
            $texto = fn (array $lineas): string => trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($lineas))));
            $descripcion = $texto($secciones['descripción'] ?? $secciones['description'] ?? []);
            $contexto = $texto($secciones['contexto'] ?? $secciones['about'] ?? $secciones['acerca de'] ?? []);
            $objetivo = $texto($secciones['objetivo'] ?? $secciones['purpose'] ?? $secciones['goals'] ?? []);
            $tecnologias = $texto($secciones['tecnologías'] ?? $secciones['tecnologias'] ?? $secciones['technologies'] ?? $secciones['tech stack'] ?? []);

            return [
                'nombre' => trim((string) (preg_match('/^#\s+(.+)$/m', $readme, $coincidencia) ? $coincidencia[1] : '')),
                'descripcion' => $descripcion,
                'contexto' => $contexto,
                'objetivo' => $objetivo,
                'tecnologias' => $tecnologias,
            ];
        }

        private function seccionesDesdeRepositorio(string $readme, $archivos)
        {
            // README sections are often framework boilerplate or sponsor lists.
            // Functional sections are inferred from source code instead.
            return collect();
        }

        private function analizarCodigoGithub($cliente, array $partes, string $rama, $archivos)
        {
            $extensiones = ['php', 'js', 'jsx', 'ts', 'tsx', 'vue', 'py', 'java', 'go', 'rb', 'cs'];
            $fuentes = $archivos
                ->filter(function (string $path) use ($extensiones): bool {
                    $normalizado = strtolower(str_replace('\\', '/', $path));
                    $extension = strtolower(pathinfo($normalizado, PATHINFO_EXTENSION));
                    return in_array($extension, $extensiones, true)
                        && ! preg_match('~(^|/)(vendor|node_modules|dist|build|public/build|storage)/~', $normalizado);
                })
                ->take(max(1, (int) config('services.github.import_max_files', 20)));
            $secciones = collect();

            foreach ($fuentes as $ruta) {
                $respuesta = $cliente->get(
                    "https://api.github.com/repos/{$partes['owner']}/{$partes['repo']}/contents/".str_replace('%2F', '/', rawurlencode($ruta)),
                    ['ref' => $rama]
                );
                if (! $respuesta->successful()) {
                    continue;
                }
                $contenido = base64_decode((string) $respuesta->json('content'), true);
                if ($contenido === false || strlen($contenido) > 180000) {
                    continue;
                }

                $directorio = $this->seccionDesdeCodigo($ruta, $contenido);
                $funcionalidades = $this->funcionalidadesDesdeCodigo($ruta, $contenido);
                if ($funcionalidades === []) {
                    continue;
                }
                $actual = $secciones->firstWhere('nombre', $directorio);
                if (! $actual) {
                    $secciones->push([
                        'nombre' => $directorio,
                        'descripcion' => $this->descripcionDeDominio($directorio),
                        'funcionalidades' => [],
                    ]);
                    $actual = $secciones->last();
                }
                $indice = $secciones->search(fn (array $item): bool => $item['nombre'] === $actual['nombre']);
                $combinadas = $this->combinarFuncionalidades($actual['funcionalidades'], $funcionalidades);
                $secciones->put($indice, array_merge($actual, [
                    'funcionalidades' => array_slice($combinadas, 0, 40),
                ]));
            }

            return $secciones;
        }

        private function simbolosDeCodigo(string $contenido, string $ruta): array
        {
            $simbolos = [];
            $patrones = [
                '/\b(?:class|interface|trait)\s+([A-Za-z_][\w]*)/' => 'Módulo',
                '/\b(?:public\s+|private\s+|protected\s+|static\s+)*function\s+([A-Za-z_][\w]*)\s*\(/' => 'Función',
                '/\b(?:export\s+)?(?:async\s+)?function\s+([A-Za-z_][\w]*)\s*\(/' => 'Función',
                '/\b(?:const|let|var)\s+([A-Za-z_][\w]*)\s*=\s*(?:async\s*)?\([^)]*\)\s*=>/' => 'Función',
                '/(?:app|router)\.(?:get|post|put|patch|delete)\s*\(\s*[\'"]([^\'"]+)[\'"]/' => 'Ruta',
                '/Route::(?:get|post|put|patch|delete|resource)\s*\(\s*[\'"]([^\'"]+)[\'"]/' => 'Ruta',
                '/<([A-Z][A-Za-z0-9]*)\b/' => 'Componente',
            ];

            foreach ($patrones as $patron => $tipo) {
                if (preg_match_all($patron, $contenido, $coincidencias)) {
                    foreach ($coincidencias[1] as $nombre) {
                        $nombre = trim($nombre);
                        if ($nombre !== '' && strlen($nombre) <= 180 && $this->esSimboloRelevante($tipo, $nombre)) {
                            $simbolos[] = [
                                'nombre' => $this->nombreLegibleSimbolo($tipo, $nombre),
                                'descripcion' => $this->descripcionDeSimbolo($tipo, $nombre),
                            ];
                        }

                    }
                }
            }

            $unicos = [];
            foreach ($simbolos as $simbolo) {
                $unicos[$simbolo['nombre']] = $simbolo;
            }
            return array_values(array_slice($unicos, 0, 40));
        }

        private function seccionDesdeCodigo(string $ruta, string $contenido): string
        {
            $texto = strtolower(str_replace(['\\', '_', '-'], ' ', $ruta.' '.$contenido));
            $reglas = [
                'Inicio y navegación' => ['index', 'home', 'dashboard', 'inicio', 'mainlayout'],
                'Inicio de sesión' => ['login', 'signin', 'authcontroller', 'autenticacion'],
                'Registro de usuarios' => ['register', 'signup', 'registro'],
                'Usuarios y perfiles' => ['user', 'usuario', 'cliente', 'profile', 'perfil'],
                'Proyectos' => ['proyecto', 'project'],
                'Tareas' => ['tarea', 'task'],
                'Bugs e incidencias' => ['bug', 'incidente', 'incident', 'error'],
                'Catálogo y productos' => ['producto', 'product', 'catalogo', 'catalog', 'inventario'],
                'Pedidos y ventas' => ['pedido', 'order', 'venta', 'sale', 'checkout'],
                'Asistente y automatización' => ['asistente', 'assistant', 'nexus', 'ia', 'ai'],
                'Configuración' => ['configuracion', 'settings', 'config'],
            ];
            foreach ($reglas as $seccion => $palabras) {
                foreach ($palabras as $palabra) {
                    if (preg_match('/\b'.preg_quote($palabra, '/').'\b/i', $texto)) {
                        return $seccion;
                    }
                }
            }
            return 'Lógica principal';
        }

        private function funcionalidadesDesdeCodigo(string $ruta, string $contenido): array
        {
            $texto = strtolower($ruta.' '.$contenido);
            $funcionalidades = [];
            $reglas = [
                'Autenticación de usuarios' => ['login', 'signin', 'logout', 'autentic'],
                'Registro de usuarios' => ['register', 'signup', 'registro'],
                'Gestión de perfiles' => ['profile', 'perfil', 'usuario', 'cliente'],
                'Gestión de proyectos' => ['proyecto', 'project'],
                'Gestión de tareas' => ['tarea', 'task'],
                'Gestión de bugs e incidencias' => ['bug', 'incidente', 'incident'],
                'Gestión de productos' => ['producto', 'product', 'catalog', 'inventario'],
                'Gestión de pedidos y ventas' => ['pedido', 'order', 'venta', 'sale', 'checkout'],
                'Panel administrativo' => ['admin', 'administrador', 'dashboard'],
                'Consulta de información' => ['fetch', 'axios', 'http', 'api'],
            ];
            foreach ($reglas as $nombre => $palabras) {
                foreach ($palabras as $palabra) {
                    if (preg_match('/\b'.preg_quote($palabra, '/').'\b/i', $texto)) {
                        $funcionalidades[] = [
                            'nombre' => $nombre,
                            'descripcion' => $this->descripcionDeSeccion($nombre),
                        ];
                        break;
                    }
                }
            }
            return $funcionalidades;
        }

        private function seccionesFuncionalesDesdeRepositorio($cliente, array $partes, string $rama, $archivos)
        {
            $fuentes = $archivos
                ->filter(function (string $path): bool {
                    $path = strtolower(str_replace('\\', '/', $path));
                    return preg_match('/\.(php|blade\.php|js|jsx|ts|tsx|vue)$/', $path) === 1
                        && ! preg_match('~(^|/)(vendor|node_modules|dist|build|storage|public/build)/~', $path);
                })
                ->take(250);
            $evidencia = [];
            $definiciones = $this->definicionesSeccionesFuncionales();

            foreach ($fuentes as $ruta) {
                $texto = strtolower($ruta);

                foreach ($definiciones as $nombre => $definicion) {
                    foreach ($definicion['evidencias'] as $evidenciaTexto) {
                        if (preg_match('/\b'.preg_quote($evidenciaTexto, '/').'\b/i', $texto)) {
                            $evidencia[$nombre] = ($evidencia[$nombre] ?? 0) + 1;
                            break;
                        }
                    }
                }
            }

            return collect($definiciones)
                ->filter(fn (array $definicion, string $nombre): bool => ($evidencia[$nombre] ?? 0) >= $definicion['minimo'])
                ->map(fn (array $definicion, string $nombre): array => [
                    'nombre' => $nombre,
                    'descripcion' => $definicion['descripcion'],
                    'funcionalidades' => [[
                        'nombre' => $definicion['funcionalidad'],
                        'descripcion' => $definicion['descripcion'],
                    ]],
                ])
                ->values();
        }

        private function definicionesSeccionesFuncionales(): array
        {
            return [
                'Inicio y navegación' => [
                    'evidencias' => ['dashboard', 'index', 'home', 'inicio', 'mainlayout', 'navegacion'],
                    'minimo' => 1,
                    'funcionalidad' => 'Visualización del inicio y navegación principal',
                    'descripcion' => 'Presenta el punto de entrada del sistema y permite desplazarse entre sus módulos principales.',
                ],
                'Inicio de sesión' => [
                    'evidencias' => ['login', 'signin', 'autenticacion', 'authcontroller', 'logout'],
                    'minimo' => 1,
                    'funcionalidad' => 'Autenticación de usuarios',
                    'descripcion' => 'Permite validar credenciales, iniciar sesión, cerrar sesión y proteger el acceso al sistema.',
                ],
                'Registro de usuarios' => [
                    'evidencias' => ['register', 'signup', 'registro'],
                    'minimo' => 1,
                    'funcionalidad' => 'Creación de cuentas',
                    'descripcion' => 'Permite registrar nuevos usuarios y completar el proceso de alta en la aplicación.',
                ],
                'Usuarios y perfiles' => [
                    'evidencias' => ['usuario', 'usuarios', 'user', 'users', 'cliente', 'clientes', 'profile', 'perfil'],
                    'minimo' => 2,
                    'funcionalidad' => 'Administración de usuarios y perfiles',
                    'descripcion' => 'Permite consultar, administrar y actualizar la información de usuarios o perfiles.',
                ],
                'Proyectos' => [
                    'evidencias' => ['proyecto', 'proyectos', 'project', 'projects'],
                    'minimo' => 2,
                    'funcionalidad' => 'Gestión de proyectos',
                    'descripcion' => 'Permite registrar proyectos, consultar su información y dar seguimiento a su avance.',
                ],
                'Tareas' => [
                    'evidencias' => ['tarea', 'tareas', 'task', 'tasks'],
                    'minimo' => 2,
                    'funcionalidad' => 'Gestión de tareas',
                    'descripcion' => 'Permite organizar, actualizar y completar tareas relacionadas con los proyectos.',
                ],
                'Bugs e incidencias' => [
                    'evidencias' => ['bug', 'bugs', 'incidente', 'incidentes', 'incident'],
                    'minimo' => 2,
                    'funcionalidad' => 'Seguimiento de bugs e incidencias',
                    'descripcion' => 'Permite registrar, consultar y dar seguimiento a problemas, bugs e incidentes.',
                ],
                'Actualizaciones' => [
                    'evidencias' => ['actualizacion', 'actualizaciones', 'update', 'updates', 'changelog'],
                    'minimo' => 2,
                    'funcionalidad' => 'Registro de actualizaciones',
                    'descripcion' => 'Permite documentar cambios, avances y actualizaciones realizadas en los proyectos.',
                ],
                'Monitoreo' => [
                    'evidencias' => ['monitoreo', 'monitor', 'monitoring', 'health'],
                    'minimo' => 2,
                    'funcionalidad' => 'Monitoreo del sistema',
                    'descripcion' => 'Permite revisar el estado de servicios, procesos o recursos supervisados.',
                ],
                'Asistente y automatización' => [
                    'evidencias' => ['asistente', 'assistant', 'nexus', 'hallazgo', 'propuesta'],
                    'minimo' => 2,
                    'funcionalidad' => 'Asistencia y automatización',
                    'descripcion' => 'Ofrece apoyo para analizar información, detectar hallazgos y automatizar acciones del sistema.',
                ],
                'Actividad' => [
                    'evidencias' => ['actividad', 'actividades', 'activity', 'historial'],
                    'minimo' => 2,
                    'funcionalidad' => 'Historial de actividad',
                    'descripcion' => 'Registra y presenta las acciones relevantes realizadas dentro de la aplicación.',
                ],
                'Archivos' => [
                    'evidencias' => ['archivo', 'archivos', 'file', 'files', 'carpeta', 'folder'],
                    'minimo' => 2,
                    'funcionalidad' => 'Gestión de archivos',
                    'descripcion' => 'Permite organizar, consultar o administrar archivos asociados a los proyectos.',
                ],
                'Configuración' => [
                    'evidencias' => ['configuracion', 'configuraciones', 'settings', 'preferencias'],
                    'minimo' => 2,
                    'funcionalidad' => 'Configuración del sistema',
                    'descripcion' => 'Permite ajustar preferencias y parámetros generales de funcionamiento.',
                ],
            ];
        }

        private function descripcionDeSeccion(string $nombre): string
        {
            return [
                'Autenticación de usuarios' => 'Permite iniciar y cerrar sesión, validar credenciales y proteger el acceso a la aplicación.',
                'Registro de usuarios' => 'Permite crear nuevas cuentas y completar el proceso de alta de usuarios.',
                'Gestión de perfiles' => 'Permite consultar y administrar la información asociada a usuarios o clientes.',
                'Gestión de proyectos' => 'Permite registrar proyectos, consultar su información y dar seguimiento a su avance.',
                'Gestión de tareas' => 'Permite organizar, actualizar y completar tareas relacionadas con los proyectos.',
                'Gestión de bugs e incidencias' => 'Permite registrar, consultar y dar seguimiento a problemas e incidencias.',
                'Gestión de productos' => 'Permite administrar y consultar los productos que maneja la aplicación.',
                'Gestión de pedidos y ventas' => 'Permite administrar operaciones comerciales, pedidos o ventas.',
                'Panel administrativo' => 'Concentra las herramientas de administración y supervisión del sistema.',
                'Consulta de información' => 'Obtiene y presenta información mediante servicios internos o una API.',
            ][$nombre] ?? 'Agrupa el flujo funcional relacionado con esta parte de la aplicación.';
        }

        private function combinarSeccionesRepositorio($documentacion, $codigo)
        {
            $resultado = collect($documentacion);
            foreach ($codigo as $seccionCodigo) {
                $indice = $resultado->search(fn (array $item): bool => strtolower($item['nombre']) === strtolower($seccionCodigo['nombre']));
                if ($indice === false) {
                    $resultado->push($seccionCodigo);
                    continue;
                }
                $actual = $resultado->get($indice);
                $resultado->put($indice, array_merge($actual, [
                    'funcionalidades' => $this->combinarFuncionalidades(
                        $actual['funcionalidades'],
                        $seccionCodigo['funcionalidades']
                    ),
                ]));
            }
            return $resultado->take(30)->values();
        }

        private function tecnologiasDesdeCodigo($archivos): string
        {
            $nombres = [
                'php' => 'PHP',
                'js' => 'JavaScript',
                'jsx' => 'React',
                'ts' => 'TypeScript',
                'tsx' => 'TypeScript',
                'vue' => 'Vue.js',
                'scss' => 'SCSS',
                'css' => 'CSS',
                'html' => 'HTML',
                'json' => 'JSON',
                'py' => 'Python',
                'java' => 'Java',
                'go' => 'Go',
            ];
            return $archivos
                ->map(fn (string $path): string => strtolower(pathinfo($path, PATHINFO_EXTENSION)))
                ->filter()
                ->map(fn (string $extension): string => $nombres[$extension] ?? '')
                ->filter()
                ->unique()
                ->implode(', ');
        }

        private function contextoDesdeCodigo($secciones, $archivos, string $readme, string $nombreRepositorio): string
        {
            $seccionesDetectadas = $secciones->pluck('nombre')->unique()->values();
            if ($seccionesDetectadas->isEmpty()) {
                return 'Aplicación de software desarrollada a partir del código existente del repositorio, con módulos funcionales organizados para atender las necesidades descritas en su documentación.';
            }
            $temas = $seccionesDetectadas->take(6)->implode(', ');
            return "Aplicación organizada en las áreas de {$temas}. El código analizado contiene las pantallas, reglas y servicios que sostienen esos flujos.";
        }

        private function objetivoDesdeCodigo($secciones, string $contexto): string
        {
            return "Consolidar los flujos funcionales identificados en el proyecto y mantener alineadas sus pantallas, reglas de negocio y servicios. El objetivo es mejorar el sistema existente sin agregar capacidades que no estén respaldadas por el código.";
        }

        private function descripcionDesdeContexto(string $contexto): string
        {
            return "Aplicación web que {$contexto} Su estructura combina una interfaz para usuarios, lógica de negocio y servicios de soporte detectados en el repositorio.";
        }

        private function limpiarMarkdown(string $texto): string
        {
            $texto = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $texto) ?? $texto;
            $texto = preg_replace('/[`*_>#]/', '', $texto) ?? $texto;
            return trim(preg_replace('/\s+/', ' ', $texto));
        }

        private function dominioDesdeRuta(string $ruta): string
        {
            $texto = strtolower(str_replace('\\', '/', $ruta));
            $dominios = [
                'administración' => ['admin', 'administrador', 'dashboard'],
                'autenticación y usuarios' => ['auth', 'login', 'register', 'usuario', 'cliente'],
                'catálogo y productos' => ['product', 'producto', 'catalog', 'tienda', 'store'],
                'carrito y pedidos' => ['cart', 'carrito', 'order', 'pedido', 'sale', 'venta', 'checkout'],
                'API y servicios' => ['api', 'service', 'conexion', 'controller'],
                'interfaz de usuario' => ['component', 'layout', 'page', 'pages', 'view', 'assets'],
                'configuración' => ['config', 'router', 'route', 'store'],
            ];
            foreach ($dominios as $nombre => $palabras) {
                foreach ($palabras as $palabra) {
                    if (str_contains($texto, $palabra)) {
                        return $nombre;
                    }
                }
            }
            return 'Lógica principal de la aplicación';
        }

        private function descripcionDeDominio(string $dominio): string
        {
            return [
                'administración' => 'Funciones para gestionar productos, clientes, promociones, pedidos y operaciones internas.',
                'autenticación y usuarios' => 'Flujos de acceso, registro, recuperación de cuenta y gestión de usuarios.',
                'catálogo y productos' => 'Consulta, organización, detalle y administración de los productos ofrecidos.',
                'carrito y pedidos' => 'Selección de productos, cantidades, compras y seguimiento del estado de los pedidos.',
                'API y servicios' => 'Comunicación con el backend y servicios que proporcionan o procesan la información.',
                'interfaz de usuario' => 'Pantallas y componentes visuales que permiten al usuario interactuar con la aplicación.',
                'configuración' => 'Configuración de rutas, estado global, herramientas y comportamiento de la aplicación.',
                'Lógica principal de la aplicación' => 'Reglas y código principal que soportan el funcionamiento del proyecto.',
            ][$dominio] ?? 'Módulo funcional detectado en el código fuente.';
        }

        private function nombreLegibleSimbolo(string $tipo, string $nombre): string
        {
            return match ($tipo) {
                'Ruta' => 'Endpoint '.$nombre,
                'Componente' => 'Interfaz '.$nombre,
                'Módulo' => 'Módulo '.$nombre,
                default => 'Capacidad '.preg_replace('/([a-z])([A-Z])/', '$1 $2', $nombre),
            };
        }

        private function descripcionDeSimbolo(string $tipo, string $nombre): string
        {
            $legible = strtolower(preg_replace('/([a-z])([A-Z])/', '$1 $2', $nombre));
            if (preg_match('/login|register|auth|usuario|cliente/', $legible)) {
                return 'Gestiona el acceso, registro o información de las cuentas de usuario.';
            }
            if (preg_match('/product|producto|catalog|inventario/', $legible)) {
                return 'Gestiona la consulta, edición o presentación de productos del sistema.';
            }
            if (preg_match('/pedido|order|cart|carrito|venta|sale/', $legible)) {
                return 'Gestiona el flujo de pedidos, ventas o selección de elementos para una operación.';
            }
            if (preg_match('/tarea|proyecto|bug|incidente|actividad/', $legible)) {
                return 'Gestiona información operativa del seguimiento y control del proyecto.';
            }
            return match ($tipo) {
                'Ruta' => "Expone el flujo {$legible} para que la aplicación pueda recibir o procesar solicitudes.",
                'Componente' => "Presenta la interfaz {$legible} y concentra la interacción visual de ese flujo.",
                'Módulo' => "Agrupa reglas y comportamiento relacionado con {$legible}.",
                default => "Ejecuta el flujo de {$legible} dentro de la aplicación.",
            };
        }

        private function descripcionDeFuncionalidad(string $nombre): string
        {
            $legible = strtolower(trim($nombre));
            return "Permite {$legible} como parte del flujo funcional documentado del proyecto.";
        }

        private function combinarFuncionalidades(array $actuales, array $nuevas): array
        {
            $resultado = [];
            foreach (array_merge($actuales, $nuevas) as $funcionalidad) {
                $item = is_array($funcionalidad)
                    ? $funcionalidad
                    : ['nombre' => (string) $funcionalidad, 'descripcion' => $this->descripcionDeFuncionalidad((string) $funcionalidad)];
                $resultado[$item['nombre']] = $item;
            }
            return array_values(array_slice($resultado, 0, 40));
        }

        private function esSimboloRelevante(string $tipo, string $nombre): bool
        {
            $normalizado = strtolower($nombre);
            $tecnicos = [
                'below', 'type', 'go', 'run', 'init', 'setup', 'reset', 'format', 'escapehtml',
                'handleerror', 'constructor', 'render', 'mount', 'unmount', 'quasarfeatureflags',
            ];
            if (in_array($normalizado, $tecnicos, true)) {
                return false;
            }
            if ($tipo === 'Función' && strlen($normalizado) < 4) {
                return false;
            }
            return true;
        }

    public function analizarGithub(Proyecto $proyecto)
    {
        $integracion = $proyecto->integracionGithub;
        $partes = $integracion ? $this->partesRepositorioGithub($integracion->repositorio_url) : null;

        if (! $partes) {
            return back()->with('error', 'Configura una URL válida de GitHub antes de analizar el repositorio.');
        }

        $actual = NexusGithubAnalysis::where('proyecto_id', $proyecto->id)
            ->whereIn('status', ['pending', 'processing'])
            ->latest('id')
            ->first();
        if ($actual) {
            return back()->with('success', 'El análisis de GitHub ya está en curso; consulta su progreso en unos momentos.');
        }

        NexusGithubAnalysis::create([
            'proyecto_id' => $proyecto->id,
            'status' => 'pending',
            'branch' => $integracion->rama_principal,
        ]);
        $integracion->update([
            'repositorio_propietario' => $partes['owner'],
            'repositorio_nombre' => $partes['repo'],
            'estado' => 'analizando',
            'ultimo_intento' => now(),
            'ultimo_error' => null,
        ]);

        return back()->with('success', 'Análisis completo de GitHub iniciado por lotes. El progreso quedará disponible mientras se procesa.');
    }

    public function estadoAnalisisGithub(Proyecto $proyecto)
    {
        $analysis = NexusGithubAnalysis::where('proyecto_id', $proyecto->id)->latest('id')->first();

        if (! $analysis) {
            return response()->json(['status' => 'idle']);
        }

        return response()->json([
            'id' => $analysis->id,
            'status' => $analysis->status,
            'branch' => $analysis->branch,
            'total_files' => $analysis->total_files,
            'processed_files' => $analysis->processed_files,
            'skipped_files' => $analysis->skipped_files,
            'progress' => $analysis->total_files > 0
                ? round(($analysis->cursor / $analysis->total_files) * 100, 2)
                : 0,
            'error' => $analysis->error,
            'started_at' => $analysis->started_at,
            'finished_at' => $analysis->finished_at,
        ]);
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

        $rama = $datos['rama'] ?: ($integracion->rama_principal ?: Configuracion::valor('github_rama', 'main'));
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

        $rama = $datos['rama'] ?: ($integracion->rama_principal ?: Configuracion::valor('github_rama', 'main'));
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
            ->connectTimeout(5)
            ->timeout(10);

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
                'foto' => asset('favicon.ico'),
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
                : asset('favicon.ico'),
        ];
    }
}
