<?php

return [
    'ai' => [
        'enabled' => (bool) env('NEXUS_AI_ENABLED', false),
        'driver' => env('NEXUS_AI_DRIVER', 'openai_compatible'),
        'endpoint' => env('NEXUS_AI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        'api_key' => env('NEXUS_AI_API_KEY'),
        'model' => env('NEXUS_AI_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('NEXUS_AI_TEMPERATURE', 0.1),
        'timeout' => (int) env('NEXUS_AI_TIMEOUT', 30),
        'max_steps' => (int) env('NEXUS_AI_MAX_STEPS', 5),
    ],
    'memory' => [
        'recent_messages' => (int) env('NEXUS_MEMORY_RECENT_MESSAGES', 8),
        'message_candidates' => (int) env('NEXUS_MEMORY_MESSAGE_CANDIDATES', 80),
        'relevant_messages' => (int) env('NEXUS_MEMORY_RELEVANT_MESSAGES', 6),
        'relevant_memories' => (int) env('NEXUS_MEMORY_RELEVANT_MEMORIES', 8),
        'automatic_min_confidence' => (int) env('NEXUS_MEMORY_AUTOMATIC_MIN_CONFIDENCE', 75),
        'deduplication_threshold' => (float) env('NEXUS_MEMORY_DEDUPLICATION_THRESHOLD', 0.75),
    ],
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
