<?php

namespace Database\Seeders;

use App\Models\Proyecto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DevControlProjectSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['Acceso', 'Sistema de autenticación y acceso de los usuarios a DevControl.', 'Implementada', ['Inicio de sesión', 'Registro de usuarios', 'Cerrar sesión', 'Protección de acceso al panel administrativo']],
            ['Dashboard', 'Vista general desde la que el usuario puede acceder y consultar el estado general de DevControl.', 'Implementada', ['Visualizar panel principal', 'Visualizar información general', 'Acceder a los módulos del sistema', 'Acceder al asistente Nexus']],
            ['Proyectos', 'Administración y organización de los proyectos registrados en DevControl.', 'Parcial', ['Crear proyectos', 'Consultar proyectos', 'Editar proyectos', 'Eliminar proyectos', 'Consultar detalle del proyecto', 'Administrar estado y progreso', 'Administrar información del proyecto', 'Administrar tareas relacionadas', 'Administrar bugs relacionados', 'Administrar actualizaciones', 'Administrar archivos', 'Administrar notas', 'Integración con GitHub']],
            ['Tareas', 'Administración del trabajo pendiente y seguimiento de actividades de los proyectos.', 'Implementada', ['Crear tareas', 'Consultar tareas', 'Editar tareas', 'Eliminar tareas', 'Asociar tareas a proyectos', 'Asociar tareas a usuarios', 'Administrar estados', 'Administrar prioridades', 'Administrar fechas', 'Asociar tareas con elementos del proyecto']],
            ['Bugs', 'Registro y seguimiento de errores encontrados en los proyectos.', 'Implementada', ['Registrar bugs', 'Consultar bugs', 'Editar bugs', 'Eliminar bugs', 'Administrar estados de bugs', 'Administrar prioridades', 'Consultar contadores por estado']],
            ['Actualizaciones', 'Registro y seguimiento de cambios y actualizaciones realizadas en los proyectos.', 'Implementada', ['Consultar actualizaciones', 'Registrar actualizaciones', 'Asociar actualizaciones a proyectos', 'Registrar cambios realizados']],
            ['Incidentes', 'Seguimiento de problemas importantes y eventos que afectan a los proyectos.', 'Implementada', ['Consultar incidentes', 'Agrupar incidentes', 'Consultar prioridad', 'Consultar proyecto afectado', 'Administrar estados', 'Visualizar métricas', 'Visualizar flujo de resolución']],
            ['Asistente IA / Nexus', 'Asistente inteligente de DevControl para consultar información y analizar el estado de los proyectos.', 'Implementada', ['Chat con IA', 'Mantener conversación durante la sesión', 'Limpiar historial', 'Revisar hallazgos', 'Revisar salud del sistema', 'Revisar propuestas de mejora', 'Accesos rápidos a proyectos', 'Accesos rápidos a tareas', 'Ejecutar comandos relacionados mediante Artisan']],
            ['Usuarios', 'Administración de los usuarios que tienen acceso a DevControl.', 'Implementada', ['Crear usuarios', 'Consultar usuarios', 'Buscar usuarios', 'Editar usuarios', 'Eliminar usuarios', 'Administrar roles', 'Administrar accesos']],
            ['Monitoreo', 'Módulo destinado a observar aplicaciones, servicios y recursos externos relacionados con los proyectos.', 'En desarrollo', ['Monitorear servicios', 'Monitorear repositorios', 'Consultar estado de servicios', 'Integrar posteriormente servicios externos']],
            ['Archivos', 'Administración de archivos y carpetas relacionados con los proyectos.', 'Parcial', ['Consultar archivos', 'Consultar carpetas', 'Administrar archivos', 'Cargar archivos', 'Editar archivos', 'Eliminar archivos']],
            ['Notificaciones', 'Sistema de alertas y notificaciones importantes de DevControl.', 'En desarrollo', ['Notificar bugs', 'Notificar incidentes', 'Notificar despliegues', 'Notificar tareas', 'Configurar notificaciones', 'Agrupar notificaciones']],
            ['Configuración', 'Configuración general e integraciones de DevControl.', 'En desarrollo', ['Configuración de GitHub', 'Configuración de conexiones y servidores', 'Configuración de notificaciones', 'Configuración de inteligencia artificial', 'Preferencias', 'Configuración de seguridad']],
            ['Actividad', 'Historial de acciones y eventos realizados dentro de DevControl.', 'En desarrollo', ['Consultar actividad', 'Registrar acciones', 'Consultar historial de eventos']],
            ['Seguimiento', 'Seguimiento del progreso y evolución de los proyectos o procesos.', 'En desarrollo', ['Consultar seguimiento', 'Visualizar progreso', 'Dar seguimiento a proyectos']],
            ['Notas', 'Información adicional y notas relacionadas con los proyectos.', 'En desarrollo', ['Crear notas', 'Consultar notas', 'Editar notas', 'Eliminar notas', 'Asociar notas a proyectos']],
        ];

        DB::transaction(function () use ($sections): void {
            $project = Proyecto::updateOrCreate(
                ['nombre' => 'DevControl'],
                [
                    'descripcion' => 'DevControl es un centro inteligente de control, seguimiento, observabilidad y análisis de proyectos de software. Permite administrar proyectos, tareas, bugs, actualizaciones e incidentes, y busca integrar posteriormente GitHub, inteligencia artificial, monitoreo, logs, despliegues, seguridad y automatización.',
                    'contexto' => 'DevControl es una aplicación web desarrollada con Laravel, PHP, MySQL y Tailwind CSS. Su propósito es centralizar la información y el seguimiento técnico de proyectos de software. Actualmente cuenta con autenticación, usuarios, proyectos, tareas, bugs, actualizaciones, incidentes y un asistente de IA llamado Nexus. También existen módulos que se encuentran en construcción o preparados para futuras integraciones, como monitoreo, archivos, notificaciones, configuración y seguimiento. DevControl también será administrado como su propio proyecto dentro de DevControl, permitiendo utilizar el sistema para registrar y dar seguimiento a su propio desarrollo.',
                    'objetivo' => 'Convertirse progresivamente en un centro de control técnico capaz de administrar y comprender los proyectos de software, registrar su evolución, analizar cambios, detectar problemas, relacionar información y posteriormente integrar GitHub, IA, monitoreo, logs, incidentes, despliegues y seguridad.',
                    'tecnologias' => 'Laravel, PHP, MySQL, Tailwind CSS',
                    'reglas' => "DevControl debe evolucionar de forma incremental.\nNo debe convertirse únicamente en un gestor de tareas.\nLa IA debe funcionar como asistente y no como autoridad absoluta.\nLas acciones críticas deben requerir autorización.\nSe debe evitar generar información duplicada o ruido innecesario.\nLas funcionalidades futuras deben integrarse con la arquitectura existente.\nNo asumir que una funcionalidad está implementada si actualmente solo existe como interfaz o demostración.",
                    'repositorio_url' => null,
                    'fecha_inicio' => now()->toDateString(),
                    'fecha_meta' => null,
                    'estado' => 'Activo',
                    'progreso' => 0,
                ]
            );

            $project->secciones()->delete();

            foreach ($sections as $sectionIndex => [$name, $description, $status, $features]) {
                $section = $project->secciones()->create([
                    'nombre' => $name,
                    'descripcion' => $description,
                    'orden' => $sectionIndex,
                ]);

                foreach ($features as $featureIndex => $feature) {
                    $section->funcionalidades()->create([
                        'nombre' => $feature,
                        'descripcion' => null,
                        'estado' => $status,
                        'orden' => $featureIndex,
                    ]);
                }
            }
        });
    }
}
