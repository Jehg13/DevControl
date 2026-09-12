<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use Illuminate\Http\Request;

class ActividadController extends Controller
{
    public function index(Request $request)
    {
        $actividades = Actividad::with(['proyecto', 'usuario'])
            ->when($request->filled('origen'), fn ($query) => $query->where('origen', $request->string('origen')))
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $term = $request->string('buscar')->toString();
                $query->where(fn ($builder) => $builder
                    ->where('accion', 'like', "%{$term}%")
                    ->orWhere('descripcion', 'like', "%{$term}%"));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.actividad', [
            'actividades' => $actividades,
            'metricas' => [
                'hoy' => Actividad::whereDate('created_at', today())->count(),
                'usuarios' => Actividad::where('origen', 'Usuario')->whereDate('created_at', today())->count(),
                'sistema' => Actividad::where('origen', 'DevControl')->whereDate('created_at', today())->count(),
                'total' => Actividad::count(),
            ],
        ]);
    }
}
