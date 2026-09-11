<?php

return [
    'modules' => [
        'proyectos' => ['label' => 'Proyectos', 'route' => 'proyectos.index'],
        'tareas' => ['label' => 'Tareas', 'route' => 'tareas.index'],
        'bugs' => ['label' => 'Bugs', 'route' => 'bugs.index'],
        'actualizaciones' => ['label' => 'Actualizaciones', 'route' => 'actualizaciones'],
        'usuarios' => ['label' => 'Usuarios', 'route' => 'usuarios'],
        'ia analisis' => ['label' => 'IA / Análisis', 'route' => 'asistente.index'],
    ],
    'states' => [
        'tareas' => ['Pendiente', 'En progreso', 'En revisión', 'Completado', 'Cancelado'],
        'bugs' => ['Reportado', 'Investigando', 'En desarrollo', 'En pruebas', 'Solucionado', 'Cerrado'],
    ],
    'rules' => [
        'scan_is_read_only' => true,
        'secrets_are_redacted' => true,
    ],
    'watch_directories' => [
        'app',
        'routes',
        'config',
        'database',
        'resources',
    ],
    'watch_excluded' => [
        '.env',
        'vendor/',
        'storage/',
        'bootstrap/cache/',
    ],
];
