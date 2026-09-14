# Fase 37 — Nexus Proactive Detection

Este módulo detecta señales tempranas antes de que el usuario registre una
incidencia. Correlaciona fuentes existentes y genera alertas propuestas; no
crea incidencias ni ejecuta reparaciones automáticamente.

## Fuentes

```text
commits, cambios, errores, logs, métricas, incidencias, bugs,
infraestructura, dependencias, historial y experiencias
```

## Patrones

```text
regresiones
errores recurrentes
cambios peligrosos
degradación
anomalías
configuración sospechosa
dependencias problemáticas
fallas potenciales
```

## Alerta

Cada alerta contiene:

```text
problem
evidence
severity
confidence
impact
project
location
recommendation
sources
alert_id
status
```

Los niveles son:

```text
INFO, LOW, MEDIUM, HIGH, CRITICAL
```

La confianza aumenta solo cuando hay varias evidencias o fuentes
independientes. Las alertas débiles se descartan mediante `min_confidence` y
las alertas se identifican con un hash estable para evitar duplicados.

## Integración con DevControl

`to_devcontrol()` genera un payload compatible con una futura creación de
incidencia:

```json
{
  "title": "degradation",
  "severity": "HIGH",
  "confidence": 0.71,
  "project": "devcontrol",
  "proactive": true,
  "status": "proposed"
}
```

El estado inicial siempre es `proposed`. La integración real debe pasar por
`NexusPermissionManager`, deduplicación de incidencias y confirmación humana
antes de persistir una nueva incidencia.

## Ejecución

```text
python scripts/nexus_proactive.py signals.json \
  --output artifacts/proactive-alerts.json \
  --min-confidence 0.45
```

Las pruebas cubren correlación, escalamiento, señales normales, estabilidad de
IDs y supresión de alertas de baja confianza.
