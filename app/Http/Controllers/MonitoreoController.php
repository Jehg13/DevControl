<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Configuracion;
use App\Models\Proyecto;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MonitoreoController extends Controller
{
    public function index()
    {
        $monitores = json_decode((string) Configuracion::valor('monitores_http', '[]'), true) ?: [];
        $proyectos = Proyecto::query()->orderBy('nombre')->get(['id', 'nombre']);

        return view('admin.monitoreo', compact('monitores', 'proyectos'));
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'proyecto_id' => ['nullable', 'integer', 'exists:proyectos,id'],
        ]);

        $monitores = json_decode((string) Configuracion::valor('monitores_http', '[]'), true) ?: [];
        $monitores[] = [
            'id' => bin2hex(random_bytes(8)),
            'nombre' => $datos['nombre'],
            'url' => $datos['url'],
            'proyecto_id' => $datos['proyecto_id'] ?? null,
            'estado' => 'pendiente',
            'codigo' => null,
            'latencia_ms' => null,
            'ultima_comprobacion' => null,
        ];
        Configuracion::guardar('monitores_http', json_encode($monitores, JSON_UNESCAPED_SLASHES), true);
        $proyecto = ! empty($datos['proyecto_id']) ? Proyecto::find($datos['proyecto_id']) : null;
        Actividad::registrar(
            'Monitor agregado',
            "Se agregó el monitor HTTP {$datos['nombre']}".($proyecto ? " al proyecto {$proyecto->nombre}." : '.'),
            $proyecto
        );

        return back()->with('success', 'Monitor agregado correctamente.');
    }

    public function check(string $monitor)
    {
        $monitores = json_decode((string) Configuracion::valor('monitores_http', '[]'), true) ?: [];
        $indice = collect($monitores)->search(fn (array $item) => $item['id'] === $monitor);

        abort_if($indice === false, 404);

        $inicio = microtime(true);
        try {
            $respuesta = Http::connectTimeout(5)->timeout(10)->get($monitores[$indice]['url']);
        } catch (ConnectionException) {
            $monitores[$indice]['estado'] = 'fallo';
            $monitores[$indice]['codigo'] = null;
            $monitores[$indice]['latencia_ms'] = (int) round((microtime(true) - $inicio) * 1000);
            $monitores[$indice]['ultima_comprobacion'] = now()->toIso8601String();
            Configuracion::guardar('monitores_http', json_encode($monitores, JSON_UNESCAPED_SLASHES));

            return back()->with('error', 'No fue posible conectar con el monitor.');
        }
        $latencia = (int) round((microtime(true) - $inicio) * 1000);

        $monitores[$indice]['estado'] = $respuesta->successful() ? 'operativo' : 'fallo';
        $monitores[$indice]['codigo'] = $respuesta->status();
        $monitores[$indice]['latencia_ms'] = $latencia;
        $monitores[$indice]['ultima_comprobacion'] = now()->toIso8601String();
        Configuracion::guardar('monitores_http', json_encode($monitores, JSON_UNESCAPED_SLASHES));

        return back()->with(
            $respuesta->successful() ? 'success' : 'error',
            $respuesta->successful()
                ? "El monitor respondió correctamente ({$respuesta->status()})."
                : "El monitor respondió con el código {$respuesta->status()}."
        );
    }

    public function destroy(string $monitor)
    {
        $monitores = json_decode((string) Configuracion::valor('monitores_http', '[]'), true) ?: [];
        $actualizados = array_values(array_filter($monitores, fn (array $item) => $item['id'] !== $monitor));

        abort_if(count($actualizados) === count($monitores), 404);

        Configuracion::guardar('monitores_http', json_encode($actualizados, JSON_UNESCAPED_SLASHES), true);

        return back()->with('success', 'Monitor eliminado correctamente.');
    }
}
