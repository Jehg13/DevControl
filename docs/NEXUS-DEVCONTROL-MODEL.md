# Fase 32 — Nexus DevControl Model

Esta especialización enseña el flujo operativo de DevControl sin permitir que
el modelo convierta una hipótesis en un hecho. Cada ejemplo separa contexto,
evidencia, respuesta esperada y estado de soporte.

## Dominios

```text
proyectos, tareas, bugs, incidencias, análisis, repositorios, cambios,
despliegues, infraestructura, historial, mantenimiento, riesgos
```

## Tareas

```text
analyze_project
find_problems
explain_incident
propose_solution
change_impact
prioritize_incidents
probable_cause
repair_plan
missing_information
```

## Ejemplo

```json
{
  "example_id": "incident-001",
  "task": "probable_cause",
  "context": "La API responde 500 después del despliegue.",
  "evidence": ["log: migration users failed", "commit: abc123"],
  "response": "La migración fallida es una causa probable; debe validarse en el entorno.",
  "expected_status": "supported",
  "domains": ["incidents", "deployments", "changes"],
  "license": "proprietary-authorized",
  "permitted": true
}
```

Cuando no hay evidencia:

```json
{
  "expected_status": "insufficient_evidence",
  "response": "No tengo evidencia suficiente."
}
```

El pipeline rechaza ejemplos insuficientemente evidenciados que afirmen una
causa concreta, y también rechaza abstenciones en casos marcados como
`supported`.

## Construcción

```text
python scripts/nexus_devcontrol_dataset.py devcontrol-examples.jsonl \
  --tokenizer artifacts/nexus-tokenizer-1.0.0.json \
  --output artifacts/nexus-devcontrol-dataset-1.0.0
```

El resultado es `training-devcontrol.jsonl` y `devcontrol-stats.json`, con
conteos por tarea, dominio, estado y ejemplos de abstención.

## Evaluación

```text
python scripts/nexus_devcontrol_benchmark.py \
  artifacts/nexus-training-0.1.0/latest.json \
  artifacts/nexus-tokenizer-1.0.0.json \
  benchmarks/nexus-devcontrol-benchmark.json \
  --output artifacts/nexus-devcontrol-evaluation.json
```

El benchmark separa:

- `status_accuracy`: si el modelo se abstiene cuando falta evidencia;
- `evidence_score`: coincidencia de términos técnicos esperados.

Un modelo no se considera apto solo por generar texto plausible: debe mantener
una tasa alta de abstención correcta y nunca tener acceso para inventar datos,
concederse permisos o ejecutar acciones.
