# Nexus 1.0

## Estado

Nexus es un agente técnico de proyectos y programación coordinado por un modelo
de lenguaje externo. Su núcleo no es un modelo entrenado por DevControl:
interpreta objetivos, recupera contexto, selecciona herramientas y delega la
ejecución a servicios protegidos.

El flujo verificado es:

```text
Usuario
  -> contexto y memoria relevante
  -> razonamiento mediante NexusModel
  -> comprensión del proyecto / Planner
  -> Permission Manager
  -> NexusToolRegistry
  -> ejecución y observación
  -> estado interno y Reflection
  -> experiencia / memoria cuando corresponde
  -> validación y respuesta
```

La inferencia está desacoplada mediante el contrato interno
`App\Contracts\NexusModel` y `NexusInferenceEngine`. `NexusModelRequest` actúa
como contexto de modelo y `NexusModelResponse` como respuesta normalizada.
`NexusModelCapabilities` describe capacidades disponibles sin exponer detalles
del proveedor al núcleo.

El `NexusCognitiveCore` es una capa determinista independiente de la inferencia:

```text
Cognitive Core
  -> clasificación, reglas y prioridades
  -> Knowledge Engine y Experience Memory
  -> acciones disponibles y plan propuesto
  -> Permission Manager (sin autoautorización)
  -> Tools (ejecución únicamente en una capa posterior)
```

El Core puede identificar información conocida, desconocida y acciones
investigables sin ejecutar herramientas ni requerir un LLM. `Nexus AI` queda
separado como una futura implementación de `NexusModel`; `Tools`, `Knowledge` y
`Memory` siguen siendo subsistemas independientes.

El proveedor predeterminado es `none`: cuando no existe un adaptador configurado
Nexus responde con `model_unavailable` de forma controlada y no intenta usar
OpenAI ni otro proveedor como fallback.

## Responsabilidades

- `NexusReasoningService`: interpreta solicitudes y valida llamadas de
  herramientas devueltas por el modelo.
- `NexusMemoryService`: conversaciones, mensajes y memoria persistente
  relevante.
- `NexusProjectUnderstandingService`: representación estructurada y consultas
  sobre proyectos.
- `NexusPlannerService` y `NexusPlanService`: planes, dependencias, estados y
  avance.
- `NexusPermissionManager`: autoridad única para permisos, riesgo, aprobación y
  auditoría.
- `NexusToolRegistry` y `AbstractNexusTool`: contratos, validación, ejecución y
  control de herramientas.
- `NexusReflectionService`: evaluación estructurada, evidencia, errores y
  retroalimentación.
- `NexusAutonomousExecutionService`: ejecución limitada, pausas, reanudación,
  reintentos y límites.
- `NexusExperienceService`: aprendizaje recuperable separado de la memoria
  conversacional.
- `NexusGithubService`, `NexusInfrastructureService` y los servicios de
  DevControl: integraciones externas y observación.
- `NexusOptimizationService`: métricas y propuestas revisables; no aplica
  auto-modificaciones.
- `NexusKnowledgeEngine` y `NexusCognitiveCore`: búsqueda contextual,
  clasificación, condiciones, selección de acciones y planes deterministas
  sin ejecución automática.
- `NexusKnowledgeGraphService`: conocimiento técnico estructurado como
  entidades y afirmaciones versionadas. Conserva tipo epistemológico
  (`FACT`, `INFERENCE`, `HYPOTHESIS` o `UNKNOWN`), fuente, fingerprint,
  confianza, trazabilidad, invalidación e impacto.
- `NexusCodeIntelligenceService`: indexación estructural determinista mediante
  parsers extensibles para PHP, JavaScript/TypeScript, Dart y SQL. Conserva
  fingerprints por archivo, reutiliza análisis sin cambios y reconstruye solo
  archivos modificados.
- `NexusDatasetService`: normaliza experiencias, ejecuciones, planes, bugs y
  tareas en ejemplos estructurados para un futuro Nexus AI. Aplica limpieza,
  deduplicación, calidad, versionado, scoring, anonimización, filtrado de
  secretos, clasificación y divisiones reproducibles de training, validation
  y test. No entrena modelos.
- `NexusLearningService`: detecta recurrencias, estrategias efectivas,
  obsolescencia y errores repetidos a partir de ejecuciones, métricas y
  experiencias. Distingue aprendizaje observado, inferido, validado y
  rechazado; conserva evidencia y permite rollback.
- `NexusInferenceEngine`: frontera interna entre el núcleo y los adaptadores de
  inferencia.
- La especificación de arquitectura neuronal del futuro modelo propio está en
  [NEXUS-AI-ARCHITECTURE.md](./NEXUS-AI-ARCHITECTURE.md). Define un decoder-only
  denso inicial, RAG, embeddings, tool calling, checkpoints y escalamiento sin
  entrenar todavía.

## Seguridad y trazabilidad

Toda herramienta pasa por `NexusPermissionManager`. Las herramientas sin
permisos explícitos son rechazadas y las operaciones sensibles pueden requerir
confirmación. Los permisos explícitos proporcionados por el contexto de una
ejecución se propagan al contexto real de cada herramienta.

Las herramientas se registran por nombre único. Un registro duplicado falla
explícitamente en lugar de reemplazar silenciosamente una herramienta.

Las ejecuciones, llamadas, autorizaciones, planes, reflexiones, experiencias,
métricas y propuestas tienen persistencia separada. Las propuestas de
optimización solo pueden pasar a `approved` mediante una acción humana; aprobar
una propuesta no implementa cambios.

## Persistencia auditada

La auditoría del proyecto encontró 30 tablas creadas por las migraciones
actuales, incluidas las estructuras base de DevControl y las de Nexus:

- `nexus_runs`, `nexus_tool_calls`
- `nexus_conversations`, `nexus_messages`, `nexus_memories`
- `nexus_plans`, `nexus_analysis_results`
- `nexus_project_understandings`
- `nexus_permission_audits`
- `nexus_autonomous_runs`
- `nexus_infrastructures`, `nexus_infrastructure_events`
- `nexus_experiences`
- `nexus_performance_metrics`, `nexus_optimization_proposals`
- proyectos, tareas, bugs, incidencias, archivos, carpetas, configuraciones,
  funcionalidades, secciones, integraciones, actividades, actualizaciones y
  usuarios.

El número real observado no coincide con la referencia de 32 tablas de la
especificación; no se agregaron tablas artificiales para completar ese número.
No se eliminó ninguna tabla porque no se comprobó una duplicación funcional que
justificara esa medida.

## Integraciones

Los adaptadores externos viven en la composición de infraestructura. El núcleo
solo conoce el contrato interno. Actualmente se conserva el adaptador
`openai_compatible` para desarrollo explícito; también existe el adaptador
`none`. Un adaptador local o el futuro `Nexus AI` puede implementarse sin
modificar Reasoning, Planner, permisos ni herramientas.

- DevControl: proyectos, tareas, bugs, incidencias, análisis y hallazgos.
- GitHub: repositorios, ramas, commits, archivos, cambios, issues, pull
  requests y releases, con escrituras protegidas y verificación de SHA.
- Infraestructura: snapshots, health checks HTTP, recursos, servicios,
  aplicaciones, bases de datos, dominios, SSL y anomalías básicas.
- Knowledge Engine: indexa evidencia de Project Understanding (tecnologías,
  archivos, rutas, dependencias, símbolos y relaciones) y la expone mediante
  `nexus.knowledge.query`. DevControl, GitHub, Memory y Experience Memory
  siguen siendo fuentes separadas; sus identificadores pueden registrarse como
  fuentes de una afirmación sin copiar sus datos.
- Code Intelligence: genera símbolos, imports, referencias, dependencias,
  llamadas, consultas, tablas y relaciones de código sin usar un LLM.
  `nexus.code.intelligence` es de solo lectura y soporta análisis incremental.
- Dataset Engine: `nexus.dataset.generate` requiere `nexus.write` y
  confirmación. Produce JSONL exportable con los campos PROBLEMA, CONTEXTO,
  EVIDENCIA, ANÁLISIS, HIPÓTESIS, DECISIÓN, ACCIÓN, RESULTADO, SOLUCIÓN y
  VALIDACIÓN. Nunca conserva passwords, tokens, API keys, credenciales, private
  keys ni secretos.
- Learning Engine: `nexus.learning.analyze` requiere `nexus.write` y
  confirmación. Analizar no autoriza cambios; aplicar requiere validación
  explícita y cada aplicación conserva un snapshot para rollback. El motor no
  modifica Permission Manager, restricciones, seguridad, código crítico ni la
  arquitectura central.

## Límites explícitos

Nexus 1.0 no afirma consciencia, voluntad independiente ni interpretación
perfecta. No puede otorgarse permisos, eliminar restricciones, modificar
libremente su núcleo, convertir una hipótesis en un hecho, ejecutar acciones
destructivas sin autorización, reparar infraestructura arbitrariamente ni
entrenarse por sí mismo.

La integración con un modelo requiere habilitación y credenciales válidas. La
comprensión de proyectos, la observación de infraestructura y la recuperación
de experiencias dependen de la evidencia disponible.
