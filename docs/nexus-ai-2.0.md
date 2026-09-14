# Nexus AI 2.0 — Auditoría definitiva

Fecha: 2026-09-14

## Definición

Nexus AI 2.0 se define como una **inteligencia artificial especializada y
autónoma dentro de los límites establecidos por su arquitectura, evidencia y
permisos**. No se afirma que tenga consciencia, voluntad, emociones ni
comprensión humana.

La autoridad de seguridad sigue estando fuera del modelo, en Nexus Core,
`NexusPermissionManager` y `NexusSecurityBoundary`.

## Auditoría reproducible

Ejecutar desde la raíz de Laravel:

```powershell
php artisan nexus:2-audit
php artisan nexus:2-audit --json
```

El comando comprueba componentes, tablas, artefactos locales, modo de
proveedor, capacidades declaradas y la cadena arquitectónica. Devuelve código
de error cuando faltan pruebas críticas. La existencia de una clase no se
considera por sí sola una prueba de comportamiento end-to-end.

## Veredicto actual

**Nexus AI 2.0 no se declara listo como sistema demostrado end-to-end.**

La arquitectura de integración está presente, pero el equipo no contiene
`latest.json` ni `tokenizer.json` en `storage/app/nexus-model/`. Además,
`LocalNexusModel` declara `toolCalling=false`; el runtime independiente valida
allowlists, pero no ejecuta herramientas. Por tanto no es válido afirmar que
el modelo propio ya comprende DevControl, diagnostica, planifica y ejecuta una
cadena completa.

## Arquitectura auditada

```text
Usuario
  -> Nexus Controller / Execution
  -> Nexus AI (modelo configurado)
  -> Cognitive Core
  -> Knowledge + Memory + Experience
  -> Planner
  -> Permission Manager / Security Boundary
  -> Tool Registry
  -> Proyecto / GitHub / Infrastructure
  -> resultado persistido
  -> Reflection
  -> Learning
  -> Dataset en cuarentena y con aprobación
  -> entrenamiento externo autorizado
  -> checkpoint versionado
```

Los componentes PHP deterministas ya cubren el flujo de control, persistencia,
permisos, ejecución, reflexión y aprendizaje. La parte que todavía necesita
demostración es el comportamiento del checkpoint local conectado a ese flujo.

## Matriz de capacidades

| Capacidad | Estado actual | Evidencia o límite |
|---|---|---|
| Comprender proyectos | Parcial | `NexusProjectUnderstandingService`; falta prueba con checkpoint real |
| Comprender código | Parcial | `NexusCodeIntelligenceService`; el micro-modelo no está validado como modelo de código |
| Detectar problemas | Implementado determinísticamente | `NexusAuditService` y hallazgos persistidos |
| Diagnosticar incidencias | Parcial | servicios y reflexión existen; falta benchmark real del modelo |
| Aprender de experiencias | Parcial | `NexusLearningService`, validación y rollback de registros |
| Planificar | Parcial | `NexusPlannerService`; requiere modelo y evidencia de calidad |
| Modificar proyectos | Implementado con controles | herramientas, confirmación y permisos externos al modelo |
| Ejecutar pruebas | Parcial | disponible según herramientas; debe demostrarse en un recorrido E2E |
| Revisar resultados | Implementado | `NexusReflectionService` registra resultado, errores y evidencia |
| Corregir problemas | Parcial | propuestas y ejecución autorizada; no es capacidad autónoma ilimitada |
| Detectar riesgos preventivamente | Implementado en base | scanner y `NexusProactiveService`, con cooldown y deduplicación |
| Monitorear infraestructura | Implementado como servicio | endpoints y métricas con protección SSRF |
| Crear incidencias | Implementado | hallazgos pueden crear incidencias deduplicadas |
| Avisar al usuario | Implementado con límites | alertas respetan prioridad, cooldown y silenciamiento |
| Mantener trazabilidad | Implementado | runs, tool calls, planes, reflexión y métricas |
| Funcionar localmente | Parcial | runtime offline funciona cuando se instalan artefactos compatibles |
| Sin modelos externos | Disponible | `local` no requiere OpenAI, Anthropic, Google u OpenRouter |
| Usar modelo propio | Parcial | existe runtime y formato; faltan artefactos y validación de calidad |
| Evolucionar controladamente | Parcial | dataset firmado y cuarentena; falta promoción/rollback de pesos completo |
| Seguridad y permisos | Implementado en la capa de control | el modelo nunca es autoridad |

## Modelo, tokenizer y dataset

El modelo actual es un micro-modelo causal dependency-free con embeddings y
matriz de salida. No es un Transformer ni debe describirse como un modelo
avanzado de programación. El tokenizer es byte-level y conserva código,
Unicode y tokens especiales, pero conservar texto no equivale a comprenderlo.

Los ejemplos nuevos entran en cuarentena. El entrenamiento exige aprobación
externa, hash exacto del dataset, aprobador y firma HMAC. El modelo no puede
aprobar sus propios datos ni modificar permisos, políticas, pesos o el pipeline
de seguridad.

## Runtime e infraestructura

`nexus_runtime` selecciona una versión exacta, carga tokenizer/checkpoint,
limita contexto y salida, aplica sampling, timeout, cancelación, streaming y
métricas. La Fase 49 documenta el hardware medido y los perfiles de tamaño.
La configuración actual del entorno mantiene el modo local desactivado hasta
que existan los artefactos:

```env
NEXUS_AI_ENABLED=false
NEXUS_AI_DRIVER=local
```

Los adaptadores OpenAI-compatible pueden existir como herramienta opcional de
desarrollo, pero no son requisito del runtime local de producción. El auditor
marca explícitamente que no son una dependencia obligatoria.

## Benchmarks y prueba E2E requerida

Existe un catálogo reproducible de 12 áreas: PHP, Laravel, Flutter, Dart, SQL,
debugging, arquitectura, DevControl, diagnóstico, tool calling, planificación
y seguridad. Hasta instalar un checkpoint real, esos fixtures prueban el
evaluador, no la comprensión del modelo.

Para declarar el sistema demostrado se deben conservar:

1. health check del runtime con checkpoint y tokenizer;
2. benchmark completo con exactitud, errores, alucinaciones, permisos y latencia;
3. solicitud de usuario con contexto recuperado;
4. plan persistido con dependencias;
5. tool call estructurado validado por Nexus Core;
6. ejecución confirmada sobre un proyecto de prueba;
7. resultado, prueba y reflexión;
8. aprendizaje validado y ejemplo en dataset en cuarentena;
9. aprobación externa y hash del dataset;
10. checkpoint versionado, health check y rollback.

Mientras falte cualquiera de estos artefactos, el resultado correcto es
`not_ready`, no una declaración optimista.

## Limitaciones

- No hay consciencia real ni autoridad propia del modelo.
- El micro-modelo actual no demuestra comprensión semántica.
- Tool calling del modelo local aún no está implementado.
- Solo existe una versión manifestada (`Nexus AI v0.1`) y no hay rollback
  operativo de pesos entre versiones.
- GitHub e infraestructura dependen de sus credenciales y endpoints
  configurados; son integraciones opcionales, no conocimiento mágico.
- El rendimiento y la calidad dependen del dataset, arquitectura, evaluación y
  hardware; comprar más hardware no mejora automáticamente el modelo.
- La autonomía permanece limitada por confirmaciones, allowlists, permisos,
  límites de pasos y evidencia.

## Roadmap posterior

1. Generar e instalar un checkpoint/tokenizer versionados.
2. Implementar salida estructurada y tool calling local.
3. Crear un runner E2E aislado con proyecto fixture y permisos mínimos.
4. Ejecutar benchmarks reales y fijar umbrales de aceptación.
5. Implementar promoción atómica y rollback de versiones del modelo.
6. Añadir recuperación semántica local verificable.
7. Repetir la auditoría y declarar 2.0 solo si todos los bloqueadores están
   respaldados por evidencia reproducible.
