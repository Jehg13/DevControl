<?php

namespace App\Http\Controllers;

use App\Models\Bug;
use App\Models\Proyecto;
use Illuminate\Http\Request;

class BugController extends Controller
{
    /**
     * Mostrar todos los bugs.
     *
     * Vista principal: admin.bugs
     */
    public function index()
    {
        $bugs = Bug::with('proyecto')
            ->latest('id')
            ->get();

        $proyectos = Proyecto::orderBy('nombre')->get();

        return view('admin.bugs', compact(
            'bugs',
            'proyectos'
        ));
    }


    /**
     * Mostrar formulario para crear un bug.
     */
    public function create()
    {
        $proyectos = Proyecto::orderBy('nombre')->get();

        return view('admin.bugs.create', compact(
            'proyectos'
        ));
    }


    /**
     * Guardar un nuevo bug.
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

            'estado' => [
                'required',
                'in:Reportado,Investigando,En desarrollo,En pruebas,Solucionado,Cerrado'
            ],

            'prioridad' => [
                'required',
                'in:Baja,Media,Alta'
            ],

            'fecha_detectado' => [
                'nullable',
                'date'
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | GENERAR FOLIO
        |--------------------------------------------------------------------------
        */

        $ultimoBug = Bug::latest('id')->first();

        if ($ultimoBug && $ultimoBug->folio) {

            $ultimoNumero = (int) str_replace(
                'BUG-',
                '',
                $ultimoBug->folio
            );

            $numero = $ultimoNumero + 1;

        } else {

            $numero = 1;
        }

        $folio = 'BUG-' . str_pad(
            $numero,
            3,
            '0',
            STR_PAD_LEFT
        );


        /*
        |--------------------------------------------------------------------------
        | CREAR BUG
        |--------------------------------------------------------------------------
        */

        $bug = new Bug();

        $bug->folio = $folio;
        $bug->proyecto_id = $validado['proyecto_id'];
        $bug->titulo = $validado['titulo'];
        $bug->descripcion = $validado['descripcion'] ?? null;
        $bug->estado = $validado['estado'];
        $bug->prioridad = $validado['prioridad'];

        $bug->fecha_detectado =
            $validado['fecha_detectado']
            ?? now()->toDateString();

        $bug->save();


        return redirect()
            ->route('bugs.index')
            ->with(
                'success',
                'Bug agregado correctamente.'
            );
    }


    /**
     * Mostrar un bug específico.
     */
    public function show(Bug $bug)
    {
        $bug->load('proyecto');

        return view(
            'admin.bugs.show',
            compact('bug')
        );
    }


    /**
     * Mostrar formulario para editar.
     */
    public function edit(Bug $bug)
    {
        $bug->load('proyecto');

        $proyectos = Proyecto::orderBy('nombre')->get();

        return view(
            'admin.bugs.edit',
            compact(
                'bug',
                'proyectos'
            )
        );
    }


    /**
     * Actualizar un bug.
     */
    public function update(
        Request $request,
        Bug $bug
    ) {
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

            'estado' => [
                'required',
                'in:Reportado,Investigando,En desarrollo,En pruebas,Solucionado,Cerrado'
            ],

            'prioridad' => [
                'required',
                'in:Baja,Media,Alta'
            ],

            'fecha_detectado' => [
                'nullable',
                'date'
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR
        |--------------------------------------------------------------------------
        */

        $bug->proyecto_id = $validado['proyecto_id'];

        $bug->titulo =
            $validado['titulo'];

        $bug->descripcion =
            $validado['descripcion'] ?? null;

        $bug->estado =
            $validado['estado'];

        $bug->prioridad =
            $validado['prioridad'];

        $bug->fecha_detectado =
            $validado['fecha_detectado']
            ?? $bug->fecha_detectado;

        $bug->save();


        return redirect()
            ->route('bugs.index')
            ->with(
                'success',
                'Bug actualizado correctamente.'
            );
    }


    /**
     * Eliminar un bug.
     */
    public function destroy(Bug $bug)
    {
        $bug->delete();

        return redirect()
            ->route('bugs.index')
            ->with(
                'success',
                'Bug eliminado correctamente.'
            );
    }
}