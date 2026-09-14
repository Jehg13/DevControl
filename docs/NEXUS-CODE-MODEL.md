# Fase 31 — Nexus Code Model

La especialización de programación se construye sobre el tokenizer, dataset y
entrenador existentes. No mezcla silenciosamente lenguajes: cada ejemplo
conserva `language` y `task`, y los artefactos se escriben en un shard por
lenguaje.

## Prioridad

```text
PHP -> Laravel -> Dart -> Flutter -> SQL -> JavaScript -> TypeScript
-> HTML/CSS -> Bash/PowerShell -> Git
```

Las tareas permitidas son completar, explicar, detectar y corregir errores,
refactorizar, tests, dependencias, arquitectura, migraciones, APIs,
autenticación y bases de datos.

## Dataset de especialización

Ejemplo JSONL:

```json
{"example_id":"php-001","language":"PHP","task":"fix_error","prompt":"...","completion":"...","license":"MIT","permitted":true}
```

Construcción:

```text
python scripts/nexus_code_dataset.py code-examples.jsonl \
  --tokenizer artifacts/nexus-tokenizer-1.0.0.json \
  --output artifacts/nexus-code-dataset-1.0.0
```

El pipeline rechaza lenguajes o tareas no soportados, ejemplos sin permiso,
entradas vacías y duplicados. `specialization-stats.json` registra la
distribución por lenguaje y tarea. Para entrenar una especialización, se usa
cada shard como dataset de entrenamiento y se conserva su metadata.

## Benchmarks propios

[nexus-code-benchmark.json](../benchmarks/nexus-code-benchmark.json) contiene
un caso mínimo por lenguaje. La evaluación calcula score por lenguaje, no solo
un promedio global. El benchmark inicial es de smoke testing; antes de
promover un modelo debe crecer con casos separados de sintaxis, debugging,
refactor, tests, migraciones, APIs y arquitectura, revisados por expertos.

```text
python scripts/nexus_code_benchmark.py \
  artifacts/nexus-training-0.1.0/latest.json \
  artifacts/nexus-tokenizer-1.0.0.json \
  benchmarks/nexus-code-benchmark.json \
  --output artifacts/nexus-code-evaluation.json
```

Las métricas deben compararse por lenguaje y tarea. Un promedio alto no puede
ocultar regresiones en PHP, Laravel o SQL. El modelo actual de Fase 30 es un
prototipo causal de contexto mínimo; estos benchmarks validan la infraestructura
y no certifican todavía competencia de programación.
