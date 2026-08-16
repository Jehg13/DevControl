<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\Tarea;
use Illuminate\Http\Request;

class TareasController extends Controller
{
    /**
     * Mostrar todas las tareas.
     */
    public function index()
    {
        $tareas = Tarea::with('proyecto')
            ->latest()
            ->get();

        $proyectos = Proyecto::orderBy('nombre')->get();

        return view('admin.tareas', compact('tareas', 'proyectos'));
    }


    /**
     * Mostrar formulario para crear una tarea.
     *
     * Todo se maneja desde admin.tareas
     */
    public function create()
    {
        $tareas = Tarea::with('proyecto')
            ->latest()
            ->get();

        $proyectos = Proyecto::orderBy('nombre')->get();

        return view('admin.tareas', compact('tareas', 'proyectos'));
    }


    /**
     * Guardar una nueva tarea.
     */
    public function store(Request $request)
    {
        $validado = $request->validate([
            'proyecto_id' => [
                'required',
                'exists:proyectos,id'
            ],

            'titulo' => [
                'required',
                'string',
                'max:255'
            ],

            'descripcion' => [
                'nullable',
                'string'
            ],

            'prioridad' => [
                'required',
                'in:Alta,Media,Baja'
            ],

            'estado' => [
                'required',
                'in:Pendiente,En progreso,En revisión,Completado,Cancelado'
            ],

            'fecha_inicio' => [
                'nullable',
                'date'
            ],

            'fecha_limite' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio'
            ],
        ]);


        // Si se crea directamente como completada
        // y no tiene fecha completada, ponemos la fecha actual.
        if (
            $validado['estado'] === 'Completado'
        ) {
            $validado['fecha_completada'] = now()->toDateString();
        } else {
            $validado['fecha_completada'] = null;
        }


        Tarea::create($validado);


        return redirect()
            ->route('tareas.index')
            ->with('success', 'Tarea creada correctamente.');
    }


    /**
     * Mostrar una tarea específica.
     *
     * Se mantiene dentro de admin.tareas.
     */
    public function show(Tarea $tarea)
    {
        $tareas = Tarea::with('proyecto')
            ->latest()
            ->get();

        $proyectos = Proyecto::orderBy('nombre')->get();

        $tarea->load('proyecto');

        return view(
            'admin.tareas',
            compact('tareas', 'proyectos', 'tarea')
        );
    }


    /**
     * Mostrar formulario para editar una tarea.
     *
     * También se maneja desde admin.tareas.
     */
    public function edit(Tarea $tarea)
    {
        $tareas = Tarea::with('proyecto')
            ->latest()
            ->get();

        $proyectos = Proyecto::orderBy('nombre')->get();

        $tarea->load('proyecto');

        return view(
            'admin.tareas',
            compact('tareas', 'proyectos', 'tarea')
        );
    }


    /**
     * Actualizar una tarea.
     */
    public function update(Request $request, Tarea $tarea)
    {
        $validado = $request->validate([
            'proyecto_id' => [
                'required',
                'exists:proyectos,id'
            ],

            'titulo' => [
                'required',
                'string',
                'max:255'
            ],

            'descripcion' => [
                'nullable',
                'string'
            ],

            'prioridad' => [
                'required',
                'in:Alta,Media,Baja'
            ],

            'estado' => [
                'required',
                'in:Pendiente,En progreso,En revisión,Completado,Cancelado'
            ],

            'fecha_inicio' => [
                'nullable',
                'date'
            ],

            'fecha_limite' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio'
            ],

            'fecha_completada' => [
                'nullable',
                'date'
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | FECHA DE COMPLETADO
        |--------------------------------------------------------------------------
        */

        if (
            $validado['estado'] === 'Completado' &&
            empty($validado['fecha_completada'])
        ) {
            $validado['fecha_completada'] = now()->toDateString();
        }


        /*
        |--------------------------------------------------------------------------
        | SI DEJA DE ESTAR COMPLETADA
        |--------------------------------------------------------------------------
        */

        if ($validado['estado'] !== 'Completado') {
            $validado['fecha_completada'] = null;
        }


        $tarea->update($validado);


        return redirect()
            ->route('tareas.index')
            ->with('success', 'Tarea actualizada correctamente.');
    }


    /**
     * Eliminar una tarea.
     */
    public function destroy(Tarea $tarea)
    {
        $tarea->delete();

        return redirect()
            ->route('tareas.index')
            ->with('success', 'Tarea eliminada correctamente.');
    }
}