# Dataset de preentrenamiento de Nexus AI

La Fase 29 define un pipeline reproducible y auditable. No descarga datos
automáticamente: solo procesa un manifiesto explícito de fuentes cuya licencia
y autorización ya fueron verificadas por el operador.

## Manifiesto de fuentes

Cada línea es JSONL y debe incluir licencia, autorización y procedencia:

```json
{"source_id":"laravel-docs-001","text":"...","license":"MIT","source_type":"documentation","permitted":true,"private":false,"url":"https://example.invalid/docs","path":"README.md","metadata":{"framework":"Laravel"}}
```

Se aceptan licencias explícitas del allowlist implementado: MIT, Apache-2.0,
BSD-2-Clause, BSD-3-Clause, ISC, CC0, CC-BY, Unlicense, Public-Domain y
`proprietary-authorized` cuando exista autorización documentada. Las fuentes
privadas, sin permiso o con licencia desconocida se rechazan; no se infiere
autorización a partir de que un archivo sea accesible.

## Pipeline

```text
manifest JSONL
  -> autorización/licencia
  -> normalización UTF-8
  -> límites de tamaño
  -> secretos
  -> malware innecesario
  -> calidad
  -> deduplicación SHA-256 normalizada
  -> clasificación
  -> tokenización
  -> split determinista
  -> shards JSONL
  -> estadísticas y hash del manifiesto
```

Los registros se guardan como IDs de tokens, no como texto plano, para que el
artefacto de entrenamiento no replique innecesariamente el corpus original.
Cada shard conserva el hash del contenido, la licencia, la categoría, la
calidad, el split y los metadatos autorizados.

## Ejecución

```text
python scripts/nexus_dataset.py sources.jsonl \
  --tokenizer artifacts/nexus-tokenizer-1.0.0.json \
  --output artifacts/nexus-dataset-1.0.0 \
  --shard-size 1000
```

El directorio genera `training-*.jsonl`, `validation-*.jsonl`,
`test-*.jsonl` y `dataset-stats.json`. El split usa SHA-256, por lo que es
estable entre ejecuciones. Las proporciones predeterminadas son 96% training,
2% validation y 2% test; deben revisarse cuando el dataset real sea grande.

## Métricas obligatorias antes de entrenar

`dataset-stats.json` registra:

- documentos ingeridos, aceptados y rechazados por causa;
- tokens totales;
- categorías y licencias;
- cantidad de shards;
- vocabulario del tokenizer;
- hash de estadísticas.

Antes de entrenar, revisar también manualmente la procedencia, licencias,
distribución por lenguaje/framework y muestras de cada categoría. Este pipeline
no sustituye una revisión legal ni garantiza que una licencia sea compatible
con el uso comercial del modelo.
