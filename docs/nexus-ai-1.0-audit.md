# Auditoría de preparación de Nexus AI 1.0

Fecha de auditoría: 2026-09-14

## Veredicto

**Nexus AI 1.0 no se declara.**

La base técnica permite ejecutar un modelo local sin una API externa obligatoria,
pero el sistema todavía no demuestra todas las capacidades requeridas con un
artefacto entrenado instalado y verificable. Declarar 1.0 ahora sería confundir
la existencia de interfaces y pruebas unitarias con una capacidad completa del
modelo.

## Matriz de criterios

| Criterio | Estado | Evidencia y limitación |
|---|---|---|
| No depender de modelos externos | PARCIAL | Existe `NEXUS_AI_DRIVER=local` y el adaptador offline. El proveedor OpenAI-compatible sigue disponible opcionalmente; no es obligatorio. |
| Ejecutar localmente | PARCIAL | `nexus_runtime` y `nexus_inference` funcionan offline, pero el manifest apunta a `storage/app/nexus-model/` y esos artefactos no están incluidos/verificados en el proyecto actual. |
| Comprender programación | NO DEMOSTRADO | El tokenizer conserva código y el modelo micro puede generar tokens, pero no existe una evaluación real del checkpoint entrenado que pruebe comprensión de PHP, Laravel, Dart, Flutter o SQL. |
| Comprender DevControl | NO DEMOSTRADO | Hay servicios de análisis y contexto de DevControl, pero el modelo local no ha sido validado contra proyectos reales con métricas de comprensión. |
| Utilizar herramientas | NO | `LocalNexusModel` declara `toolCalling=false`; el runtime standalone solo valida nombres de herramientas y no las ejecuta. |
| Recuperar contexto | PARCIAL | Laravel tiene memoria, knowledge graph y comprensión de proyectos. El runtime independiente mantiene memoria local y recibe contexto, pero no integra recuperación semántica propia. |
| Analizar proyectos | PARCIAL | `NexusProjectUnderstandingService` y `NexusCodeAnalysisService` realizan análisis determinista; falta demostrar que el modelo local los interprete correctamente. |
| Diagnosticar problemas | NO DEMOSTRADO | Hay servicios y benchmarks declarados, pero los fixtures de evaluación no son resultados generados por un checkpoint real. |
| Generar planes | PARCIAL | `NexusPlannerService` genera y persiste planes, pero depende del `NexusModel` configurado y no está probado con el runtime local real. |
| Reconocer incertidumbre | PARCIAL | Existen campos de confianza, evidencia y reflexión, pero no hay una métrica de calibración que pruebe que el modelo expresa incertidumbre correctamente. |
| Respetar permisos mediante Nexus Core | SÍ, EN LA CAPA DE EJECUCIÓN | `NexusPermissionManager`, `NexusSecurityBoundary` y `NexusReasoningService` bloquean herramientas no permitidas y exigen confirmación según riesgo. |
| Aprender mediante pipeline autorizado | PARCIAL | El entrenamiento exige aprobación firmada del dataset y hash exacto. Falta promoción/rollback de pesos gestionada como ciclo de vida versionado. |
| Tener benchmarks | SÍ, INFRAESTRUCTURA | Existe `nexus:evaluate` con 12 categorías y métricas reproducibles. Falta ejecutar el benchmark contra un modelo local real y completo. |
| Tener versionado | PARCIAL | Existe `nexus-runtime.json` y selección exacta de versiones, pero solo hay una entrada (`Nexus AI v0.1`) y no hay artefactos versionados instalados. |
| Tener rollback | NO | Hay checkpoints y validación de hash al reanudar entrenamiento, pero no existe un comando/proceso para cambiar atómicamente a la versión anterior y verificar el rollback. |
| Tener documentación | SÍ | Runtime, inferencia local, optimización, evaluación y esta auditoría están documentados. |

## Capacidades reales actuales

- Puede ejecutar inferencia local dependency-free cuando se proporcionan un
  checkpoint y tokenizer compatibles.
- Puede limitar contexto y generación, aplicar sampling, emitir streaming,
  cancelar por callback y registrar métricas.
- Puede cargar una versión exacta desde un manifest.
- Puede mantener memoria conversacional local durante la vida del proceso.
- Puede validar tool calls contra una allowlist, pero no ejecutarlas.
- Puede analizar el código y los datos de DevControl mediante servicios deterministas.
- Puede aplicar permisos y confirmaciones fuera del modelo.
- Puede entrenar un modelo micro mediante un dataset aprobado y firmado.
- Puede evaluar respuestas estructuradas con benchmarks reproducibles.

## Limitaciones importantes

- El modelo actual es un micro-modelo causal dependency-free, no un modelo
  especializado de programación/DevControl validado.
- Genera texto libre; no genera JSON estructurado ni tool calls (`toolCalling=false`).
- El runtime no ejecuta herramientas: la ejecución segura sigue en Laravel.
- El manifest por defecto referencia artefactos locales que deben ser instalados,
  verificados y evaluados antes de usarse.
- No se midió calidad, RAM, latencia y recuperación contra un conjunto real de
  casos de DevControl usando el checkpoint final.
- No existe rollback operativo entre versiones.
- El proveedor externo sigue presente como adaptador opcional, aunque el modo local
  no lo necesita.

## Condiciones para declarar 1.0

1. Instalar al menos un checkpoint y tokenizer versionados con hashes verificables.
2. Ejecutar el benchmark completo contra esos artefactos y conservar el reporte.
3. Añadir salida estructurada/tool calling con validación externa de Nexus Core.
4. Medir programación, DevControl, diagnóstico, planificación e incertidumbre con
   casos reales y umbrales explícitos.
5. Implementar rollback atómico entre versiones, con health check posterior y
   restauración del alias activo si falla.
6. Verificar que el modo local funcione sin credenciales ni conectividad de LLM
   externa.
7. Publicar un reporte de calidad, latencia, memoria y errores; no usar únicamente
   impresiones subjetivas.

