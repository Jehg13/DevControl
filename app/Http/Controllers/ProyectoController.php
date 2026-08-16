<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            $proyecto->load([
                'tareas',
                'bugs',
                'actualizaciones',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Variables para la vista
        |--------------------------------------------------------------------------
        */

        $usuario = $this->obtenerUsuario();

        $tareas = $proyecto
            ? $proyecto->tareas
            : collect();

        $bugs = $proyecto
            ? $proyecto->bugs
            : collect();

        $actualizaciones = $proyecto
            ? $proyecto->actualizaciones
            : collect();

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
            'tareas',
            'bugs',
            'actualizaciones',
        ]);

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

        $tareas = $proyecto->tareas;

        $bugs = $proyecto->bugs;

        $actualizaciones = $proyecto->actualizaciones;

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

        Proyecto::create($validado);

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

        $proyecto->update($validado);

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

        if (!$proyecto) {
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

        $tareas = $proyecto->tareas ?? collect();

        $bugs = $proyecto->bugs ?? collect();

        $actualizaciones =
            $proyecto->actualizaciones ?? collect();


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

            'actualizaciones' =>
                $actualizaciones->count(),

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

        if (!$proyecto) {
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
     *
     */
    private function obtenerUsuario()
    {
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Si no existe usuario autenticado
        |--------------------------------------------------------------------------
        */

        if (!$user) {

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
            'nombre' =>
                $user->nombre
                ?? $user->name
                ?? 'Jesús Guerra',

            'rol' =>
                $user->rol
                ?? 'Desarrollador',

            'foto' =>
                $user->foto
                ? asset('storage/' . $user->foto)
                : asset(
                    'storage/images/jesus-guerra.jpg'
                ),
        ];
    }
}