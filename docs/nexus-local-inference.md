# Nexus local inference

Nexus puede ejecutar inferencia sin OpenAI, Anthropic, Google, OpenRouter ni otra API
externa usando `nexus_inference` y el modelo causal local de `nexus_training`.

## Artefactos

El runtime requiere:

- Un checkpoint compatible (`latest.json`).
- Un tokenizer `nexus-byte-bpe` verificado.
- Python local.

Las rutas predeterminadas son:

```text
storage/app/nexus-model/latest.json
storage/app/nexus-model/tokenizer.json
```

Se pueden cambiar con `NEXUS_AI_MODEL_PATH`, `NEXUS_AI_TOKENIZER_PATH` y
`NEXUS_LOCAL_PYTHON` (los nombres heredados `NEXUS_LOCAL_CHECKPOINT` y
`NEXUS_LOCAL_TOKENIZER` siguen siendo compatibles). Laravel usa el adaptador local
cuando:

```env
NEXUS_AI_ENABLED=true
NEXUS_AI_DRIVER=local
```

El proveedor `local` no hace ninguna petición de red.

El checkpoint debe tener formato `nexus-micro-checkpoint-v1`, generado por
`nexus_training.trainer`, y contener un modelo con `vocab_size`, `hidden_size`,
`embeddings` y `output`. El tokenizer debe tener formato `nexus-byte-bpe`,
incluyendo su hash de integridad. Ambos artefactos deben compartir exactamente el
mismo tamaño de vocabulario.

El loader Python valida y carga ambos artefactos una sola vez por proceso Python.
`NexusAiApplication` conserva el modelo cargado y combina el contexto estructurado
de NLP, memoria, conocimiento, razonamiento y planificación con la inferencia.
Python solo genera texto y nunca ejecuta herramientas; Laravel mantiene la
autorización y la ejecución.

## Runtime directo

```powershell
python scripts/nexus_infer.py `
  --checkpoint storage/app/nexus-model/latest.json `
  --tokenizer storage/app/nexus-model/tokenizer.json
```

El proceso recibe una línea JSON por solicitud:

```json
{"prompt":"Explica el estado del proyecto","stream":true}
```

Emite eventos JSON `token`, `complete` y `error`. Esto permite streaming sin cargar
la respuesta completa en memoria.

## Controles

El runtime aplica:

- límite de contexto con truncamiento explícito;
- límite de tokens generados;
- temperatura `0` para greedy o temperatura positiva para sampling;
- `top_k` y `top_p`;
- semilla reproducible;
- timeout;
- cancelación mediante callback o terminación del proceso;
- ejecución aislada por proceso, permitiendo concurrencia sin compartir estado mutable;
- métricas de tokens, tiempo y tokens por segundo;
- caché LRU de logits para estados repetidos;
- batching secuencial mediante `generate_batch`;
- cuantización int8 opcional, nunca activada automáticamente;
- logs de cada inferencia completada en el canal de Laravel.

La inferencia local genera texto. No decide permisos ni ejecuta herramientas: esas
decisiones continúan bajo Nexus Core, `NexusReasoningService` y el Permission Manager.

## Comprobación

```powershell
php artisan nexus:ai-health
php artisan nexus:ai-health --json
```

El comando distingue `ENABLED`, disponibilidad de Python, existencia y carga del
modelo/tokenizer e `INFERENCE READY`. Si falta un artefacto, devuelve
`model_unavailable` sin generar una respuesta simulada.

## Optimización y comparación

El checkpoint original no se modifica. Para comparar el modo normal contra el
candidato int8:

```powershell
python scripts/nexus_optimize.py `
  --checkpoint storage/app/nexus-model/latest.json `
  --tokenizer storage/app/nexus-model/tokenizer.json `
  --prompt "Resume las tareas pendientes" `
  --prompt "Diagnostica el proyecto"
```

El informe compara latencia promedio y p95, memoria Python, tamaño estimado del
modelo, aciertos de caché y coincidencia exacta de salidas. El modo int8 solo se
acepta manualmente si la calidad medida es suficiente:

```env
NEXUS_LOCAL_QUANTIZATION=none
```

No se activa GPU, compilación ni KV cache: este runtime dependency-free no detecta
un backend local de GPU y el micro-modelo actual solo usa el último token, por lo
que una KV cache real no aplica a su arquitectura.

## Entrenamiento reproducible

El preparador local usa únicamente JSONL bajo `nexus_ai/fundamentals` y genera
splits separados, además de `approval.json`, antes de entrenar:

```powershell
$env:NEXUS_DATASET_APPROVAL_KEY="clave-local"
python scripts/prepare_nexus_local_model.py
```

El resumen persistido incluye tokens por split, `final_validation_loss` y
`final_test_loss`. El modelo resultante es un artefacto experimental para
generación local; los datos factuales de DevControl deben seguir resolviéndose
mediante el pipeline autorizado de Laravel.
