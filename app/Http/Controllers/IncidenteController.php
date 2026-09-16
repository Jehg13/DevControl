<?php

namespace App\Http\Controllers;

use App\Models\Incidente;
use App\Models\Proyecto;
use Illuminate\Http\Request;

class IncidenteController extends Controller
{
    public function index()
    {
        return view('admin.incidentes', [
            'incidentes' => Incidente::with('proyecto')->latest()->get(),
            'proyectos' => Proyecto::orderBy('nombre')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.incidentes.create', ['proyectos' => Proyecto::orderBy('nombre')->get()]);
    }

    public function store(Request $request)
    {
        $incidente = Incidente::create($this->validated($request));

        return redirect()->route('incidentes')->with('success', 'Incidente creado correctamente.');
    }

    public function edit(Incidente $incidente)
    {
        return view('admin.incidentes.edit', [
            'incidente' => $incidente->load('proyecto'),
            'proyectos' => Proyecto::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Incidente $incidente)
    {
        $incidente->update($this->validated($request));

        return redirect()->route('incidentes')->with('success', 'Incidente actualizado correctamente.');
    }

    public function destroy(Incidente $incidente)
    {
        $incidente->delete();

        return redirect()->route('incidentes')->with('success', 'Incidente eliminado correctamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'proyecto_id' => ['required', 'exists:proyectos,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'prioridad' => ['required', 'in:Alta,Media,Baja'],
            'estado' => ['required', 'in:Abierto,En investigación,En resolución,Resuelto'],
            'fecha_detectado' => ['nullable', 'date'],
        ]);
    }
}
