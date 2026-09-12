<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Actualizacion;
use App\Models\Bug;
use App\Models\Proyecto;
use App\Models\Tarea;

class DashboardController extends Controller
{
    public function index()
    {
        $tareasAbiertas = ['Pendiente', 'En progreso', 'En revisión'];
        $proyectos = Proyecto::withCount([
            'tareas as pendientes_count' => fn ($query) => $query->whereIn('estado', $tareasAbiertas),
            'tareas as completados_count' => fn ($query) => $query->where('estado', 'Completado'),
            'bugs as bugs_count' => fn ($query) => $query->whereNotIn('estado', ['Solucionado', 'Cerrado']),
            'actualizaciones as actualizaciones_count',
        ])->orderByDesc('updated_at')->limit(6)->get();

        $tareasProximas = Tarea::with('proyecto')
            ->whereIn('estado', $tareasAbiertas)
            ->whereNotNull('fecha_limite')
            ->orderBy('fecha_limite')
            ->limit(5)
            ->get();

        $bugs = Bug::with('proyecto')
            ->whereNotIn('estado', ['Solucionado', 'Cerrado'])
            ->latest('fecha_detectado')
            ->limit(5)
            ->get();

        return view('admin.index', [
            'usuario' => [
                'nombre' => auth()->user()->name,
                'rol' => auth()->user()->rol,
                'foto' => auth()->user()->foto,
            ],
            'resumen' => [
                'proyectos' => Proyecto::where('estado', '!=', 'archivado')->count(),
                'pendientes' => Tarea::whereIn('estado', $tareasAbiertas)->count(),
                'en_progreso' => Tarea::where('estado', 'En progreso')->count(),
                'completados' => Tarea::where('estado', 'Completado')
                    ->whereMonth('fecha_completada', now()->month)
                    ->whereYear('fecha_completada', now()->year)
                    ->count(),
                'bugs' => Bug::whereNotIn('estado', ['Solucionado', 'Cerrado'])->count(),
            ],
            'proyectos' => $proyectos,
            'tareasProximas' => $tareasProximas,
            'bugs' => $bugs,
            'actividad' => Actividad::with('proyecto')->latest()->limit(6)->get(),
            'actualizaciones' => Actualizacion::with('proyecto')->latest()->limit(5)->get(),
            'tareasSemana' => Tarea::with('proyecto')
                ->whereBetween('fecha_limite', [now()->startOfWeek(), now()->endOfWeek()])
                ->orderBy('fecha_limite')
                ->get(),
        ]);
    }
}
