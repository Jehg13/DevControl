<?php

return [
    'ai' => [
        'enabled' => (bool) env('NEXUS_AI_ENABLED', false),
        'driver' => env('MODEL_PROVIDER', env('NEXUS_AI_DRIVER', 'none')),
        'endpoint' => env('NEXUS_AI_ENDPOINT'),
        'api_key' => env('NEXUS_AI_API_KEY'),
        'model' => env('NEXUS_AI_MODEL', 'gpt-4o-mini'),
        'temperature' => (float) env('NEXUS_AI_TEMPERATURE', 0.1),
        'timeout' => (int) env('NEXUS_AI_TIMEOUT', 30),
        'max_steps' => (int) env('NEXUS_AI_MAX_STEPS', 5),
    ],
    'security' => [
        'mode' => env('NEXUS_SECURITY_MODE', 'safe'),
        'allow_system' => (bool) env('NEXUS_SECURITY_ALLOW_SYSTEM', true),
        'protected_permissions' => [
            'nexus.write',
            'devcontrol.write',
            'github.write',
            'execute_command',
            'destructive_action',
        ],
        'autonomous_tools' => [],
    ],
    'autonomy' => [
        'max_steps' => (int) env('NEXUS_AUTONOMY_MAX_STEPS', 10),
        'max_retries' => (int) env('NEXUS_AUTONOMY_MAX_RETRIES', 2),
        'timeout_seconds' => (int) env('NEXUS_AUTONOMY_TIMEOUT', 300),
        'budget' => (int) env('NEXUS_AUTONOMY_BUDGET', 20),
        'allowed_tools' => null,
        'prohibited_tools' => [],
    ],
    'infrastructure' => [
        'default_timeout' => (int) env('NEXUS_INFRASTRUCTURE_TIMEOUT', 5),
        'thresholds' => [
            'cpu_percent' => (float) env('NEXUS_INFRASTRUCTURE_CPU_THRESHOLD', 90),
            'memory_percent' => (float) env('NEXUS_INFRASTRUCTURE_MEMORY_THRESHOLD', 90),
            'disk_percent' => (float) env('NEXUS_INFRASTRUCTURE_DISK_THRESHOLD', 90),
            'ssl_days' => (int) env('NEXUS_INFRASTRUCTURE_SSL_DAYS', 30),
        ],
    ],
    'experiences' => [
        'minimum_confidence' => (int) env('NEXUS_EXPERIENCE_MIN_CONFIDENCE', 70),
        'minimum_relevance' => (int) env('NEXUS_EXPERIENCE_MIN_RELEVANCE', 60),
        'planner_limit' => (int) env('NEXUS_EXPERIENCE_PLANNER_LIMIT', 5),
    ],
    'optimization' => [
        'report_limit' => (int) env('NEXUS_OPTIMIZATION_REPORT_LIMIT', 100),
        'step_threshold' => (float) env('NEXUS_OPTIMIZATION_STEP_THRESHOLD', 6),
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
