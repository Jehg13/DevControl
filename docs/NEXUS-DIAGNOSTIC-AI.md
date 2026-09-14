# Fase 36 — Nexus Diagnostic AI

Esta fase compone señales de Nexus AI, Code Intelligence, Knowledge Engine,
Experience Memory y Reflection en un diagnóstico estructurado. La salida no
convierte automáticamente una hipótesis en un hecho.

## Entrada

`DiagnosticInput` acepta:

```text
error
stack_trace
logs
code
recent_changes
dependencies
configuration
history
knowledge
experiences
reflections
```

## Salida

`DiagnosticReport` contiene:

```text
status
probable_causes
evidence
impact
severity
alternatives
proposed_solution
confidence
missing_information
sources
trace_id
```

`status` puede ser:

```text
supported
insufficient_evidence
```

Cuando no existen logs, stack trace, error u otra evidencia corroborable, el
sistema responde:

```text
No tengo evidencia suficiente para proponer una solución segura.
```

## Ejecución

```text
python scripts/nexus_diagnostic.py diagnostic.json \
  --output artifacts/diagnostic-report.json
```

El `trace_id` permite relacionar el diagnóstico con la evidencia que lo originó.
Las soluciones son propuestas; no ejecutan cambios ni sustituyen permisos,
pruebas o revisión humana.

## Evaluación

[nexus-diagnostics.json](../benchmarks/nexus-diagnostics.json) contiene casos
con evidencia de base de datos y casos sin evidencia. Debe ampliarse con
stack traces, dependencias, configuración, cambios, incidentes reales
anonimizados y experiencias validadas.
