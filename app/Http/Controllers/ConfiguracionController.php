<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Configuracion;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function index()
    {
        return view('admin.configuracion', [
            'configuracion' => [
                'alertas_activas' => (bool) Configuracion::valor('alertas_activas', true),
                'correo_alertas' => Configuracion::valor('correo_alertas', config('services.alerts.email')),
                'alertas_bugs' => (bool) Configuracion::valor('alertas_bugs', true),
                'alertas_incidentes' => (bool) Configuracion::valor('alertas_incidentes', true),
                'alertas_tareas' => (bool) Configuracion::valor('alertas_tareas', true),
                'alertas_commits' => (bool) Configuracion::valor('alertas_commits', true),
                'alertas_actualizaciones' => (bool) Configuracion::valor('alertas_actualizaciones', true),
                'prioridad_minima' => Configuracion::valor('prioridad_minima', 'Baja'),
                'github_rama' => Configuracion::valor('github_rama', 'main'),
                'github_sincronizacion' => (bool) Configuracion::valor('github_sincronizacion', false),
                'nexus_confirmacion' => (bool) Configuracion::valor('nexus_confirmacion', true),
                'nexus_analisis' => (bool) Configuracion::valor('nexus_analisis', true),
                'intentos_acceso' => (int) Configuracion::valor('intentos_acceso', 5),
                'zona_horaria' => Configuracion::valor('zona_horaria', config('app.timezone')),
                'sesion_minutos' => (int) Configuracion::valor('sesion_minutos', config('session.lifetime', 120)),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'alertas_activas' => ['nullable', 'boolean'],
            'correo_alertas' => ['required_if:alertas_activas,1', 'nullable', 'email', 'max:255'],
            'alertas_bugs' => ['nullable', 'boolean'],
            'alertas_incidentes' => ['nullable', 'boolean'],
            'alertas_tareas' => ['nullable', 'boolean'],
            'alertas_commits' => ['nullable', 'boolean'],
            'alertas_actualizaciones' => ['nullable', 'boolean'],
            'prioridad_minima' => ['required', 'in:Baja,Media,Alta'],
            'github_rama' => ['required', 'string', 'max:150', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'github_sincronizacion' => ['nullable', 'boolean'],
            'nexus_confirmacion' => ['nullable', 'boolean'],
            'nexus_analisis' => ['nullable', 'boolean'],
            'intentos_acceso' => ['required', 'integer', 'min:3', 'max:10'],
            'zona_horaria' => ['required', 'timezone'],
            'sesion_minutos' => ['required', 'integer', 'min:15', 'max:1440'],
        ]);

        Configuracion::guardar('alertas_activas', $request->boolean('alertas_activas'));
        Configuracion::guardar('correo_alertas', $datos['correo_alertas'] ?? null);
        foreach (['alertas_bugs', 'alertas_incidentes', 'alertas_tareas', 'alertas_commits', 'alertas_actualizaciones', 'github_sincronizacion', 'nexus_confirmacion', 'nexus_analisis'] as $clave) {
            Configuracion::guardar($clave, $request->boolean($clave));
        }
        foreach (['prioridad_minima', 'github_rama', 'intentos_acceso'] as $clave) {
            Configuracion::guardar($clave, $datos[$clave]);
        }
        Configuracion::guardar('zona_horaria', $datos['zona_horaria']);
        Configuracion::guardar('sesion_minutos', $datos['sesion_minutos']);

        Actividad::registrar('Configuración actualizada', 'Se actualizaron las preferencias generales y de alertas.');

        return back()->with('success', 'Configuración guardada correctamente.');
    }
}
