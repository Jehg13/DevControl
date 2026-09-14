# Fase 33 — Nexus Technical Reasoning

Esta fase define ejemplos de razonamiento técnico con una estructura explícita.
El modelo no recibe únicamente una respuesta final: aprende a separar objetivo,
información disponible, información faltante, hipótesis, evaluación, estrategia,
acciones, resultados esperados y validación.

## Estructura

```text
objective
available_information
missing_information
hypotheses
hypothesis_evaluation
strategy
actions
expected_results
validation
uncertainty
```

Cada hipótesis incluye:

```json
{
  "statement": "La migración puede causar el error.",
  "status": "plausible",
  "confidence": 0.55,
  "evidence": ["log de migración fallida"]
}
```

Los estados permitidos son:

```text
supported
plausible
rejected
unknown
```

Una hipótesis `plausible` no es un hecho. La confianza siempre debe estar
entre `0` y `1`, y la evidencia debe ser una lista explícita.

## Construcción

```text
python scripts/nexus_reasoning_dataset.py reasoning-examples.jsonl \
  --tokenizer artifacts/nexus-tokenizer-1.0.0.json \
  --output artifacts/nexus-reasoning-dataset-1.0.0
```

El pipeline rechaza hipótesis sin estado, confianza o evidencia, estados
desconocidos, ejemplos no autorizados, duplicados y casos que no distinguen
información disponible de información faltante.

## Benchmark

```text
python scripts/nexus_reasoning_benchmark.py \
  artifacts/nexus-training-0.1.0/latest.json \
  artifacts/nexus-tokenizer-1.0.0.json \
  benchmarks/nexus-technical-reasoning.json \
  --output artifacts/nexus-reasoning-evaluation.json
```

El reporte contiene:

- cobertura por cada etapa;
- tasa de expresión de incertidumbre;
- tasa de violaciones de hechos;
- resultados por caso.

La métrica `fact_violation_rate` debe tender a `0`. Un modelo que produce una
respuesta convincente pero convierte una hipótesis en certeza no es aceptable.
El benchmark inicial valida la infraestructura; debe ampliarse con casos
reales anonimizados y revisión humana antes de usarlo para promoción de
checkpoints.
