<?php

namespace App\Http\Controllers;

use App\Models\Actualizacion;
use App\Models\Proyecto;
use Illuminate\Http\Request;

class ActualizacionController extends Controller
{
    public function index(Request $request)
    {
        $actualizaciones = Actualizacion::with('proyecto')
            ->when($request->filled('proyecto_id'), fn ($query) => $query->where('proyecto_id', $request->integer('proyecto_id')))
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $term = $request->string('buscar')->toString();

                $query->where(function ($builder) use ($term) {
                    $builder->where('titulo', 'like', "%{$term}%")
                        ->orWhere('detalles', 'like', "%{$term}%")
                        ->orWhere('commit', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->get();

        return view('admin.actualizaciones', [
            'actualizaciones' => $actualizaciones,
            'proyectos' => Proyecto::orderBy('nombre')->get(),
            'filtros' => $request->only(['proyecto_id', 'buscar']),
            'usuario' => [
                'nombre' => auth()->user()->name ?? 'Usuario',
                'rol' => auth()->user()->rol ?? 'Administrador',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validado = $request->validate([
            'proyecto_id' => ['required', 'exists:proyectos,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'detalles' => ['required', 'string'],
            'commit' => ['nullable', 'string', 'max:40'],
        ]);

        Actualizacion::create($validado);

        return redirect()
            ->route('actualizaciones')
            ->with('success', 'Actualización registrada correctamente.');
    }

    public function edit(Actualizacion $actualizacion)
    {
        return view('admin.actualizaciones.edit', [
            'actualizacion' => $actualizacion->load('proyecto'),
            'proyectos' => Proyecto::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Actualizacion $actualizacion)
    {
        $validado = $request->validate([
            'proyecto_id' => ['required', 'exists:proyectos,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'detalles' => ['required', 'string'],
            'commit' => ['nullable', 'string', 'max:40'],
        ]);

        $actualizacion->update($validado);

        return redirect()->route('actualizaciones')
            ->with('success', 'Actualización actualizada correctamente.');
    }

    public function destroy(Actualizacion $actualizacion)
    {
        $actualizacion->delete();

        return redirect()->route('actualizaciones')
            ->with('success', 'Actualización eliminada correctamente.');
    }
}
