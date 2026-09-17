# Fase 62 — Nexus Advanced PHP Intelligence

`nexus.php.diagnose` realiza un diagnóstico de solo lectura sobre el alcance PHP
del proyecto. Combina señales estáticas con el runtime actual para producir
hallazgos priorizados y accionables.

Las categorías cubiertas son memoria, referencias, autoloading, Composer, PSR,
SPL, reflection, attributes, serialización, streams, procesos, CLI, entorno,
performance, OPcache y manejo de errores. Cada hallazgo incluye categoría,
severidad, código, evidencia (archivo/línea cuando existe) y recomendación.

El análisis excluye `vendor`, `node_modules`, `storage`, `.git` y cachés, limita
el número y tamaño de archivos, no ejecuta código del proyecto y nunca modifica
archivos ni dependencias.
