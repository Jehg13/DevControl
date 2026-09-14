# Fase 35 — Contexto y memoria de Nexus AI

La memoria persistente continúa perteneciendo a Nexus Core. Nexus AI recibe
únicamente una selección limitada y trazable de contexto recuperado.

## Fuentes soportadas

```text
conversation
long_term
experience
knowledge
project_understanding
internal_state
```

Cada elemento conserva:

- identificador;
- tipo de memoria;
- texto;
- proyecto;
- sesión;
- origen;
- timestamp;
- metadata.

## Recuperación

`ContextRetriever` utiliza ranking léxico determinista, alcance por proyecto y
sesión, filtros por tipo de memoria, límite de elementos y presupuesto de
tokens. El modelo no recibe automáticamente toda la conversación, proyecto o
grafo de conocimiento.

```python
context = retriever.retrieve(
    "migration users",
    project_id="alpha",
    session_id="session-2",
    memory_types={"knowledge", "conversation"},
    max_items=8,
    max_tokens=1024,
)
model_context = retriever.build_model_context(context)
```

El contexto resultante incluye scores, fuentes, elementos omitidos y cantidad
de tokens. Esto permite auditar por qué una memoria llegó al modelo.

## Evaluación

El benchmark mide:

- precisión de recuperación;
- recall;
- contaminación por elementos prohibidos;
- cumplimiento del presupuesto de tokens.

```text
python scripts/nexus_retrieval_benchmark.py \
  context-items.json \
  benchmarks/nexus-retrieval.json \
  --tokenizer artifacts/nexus-tokenizer-1.0.0.json
```

Los filtros de proyecto y sesión impiden mezclar por defecto conversaciones
antiguas, proyectos distintos o recuerdos fuera del alcance solicitado.
Experience Memory, Knowledge Engine y Project Understanding siguen siendo
fuentes externas corregibles; sus datos no se copian dentro de los pesos del
modelo.
