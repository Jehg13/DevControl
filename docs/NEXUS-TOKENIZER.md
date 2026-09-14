# Nexus AI Tokenizer

Fase 28 define un tokenizer byte-level BPE reproducible para código y artefactos
técnicos. Se mantiene fuera de Laravel para que el entrenamiento y la inferencia
puedan usar el mismo artefacto desde Python, un runtime local o un adaptador
futuro de Nexus AI.

## Cobertura

El entrenamiento acepta archivos UTF-8 y conserva exactamente PHP, Laravel,
Dart, Flutter, JavaScript, TypeScript, SQL, HTML, CSS, JSON, YAML, Markdown,
Git, terminal, logs, errores y DevControl. El nivel byte evita perder símbolos
raros, namespaces, rutas, escapes, stack traces o caracteres Unicode.

## Diseño

- Algoritmo: byte-level BPE determinista.
- Vocabulario objetivo: 65,536 tokens.
- Base: 256 tokens byte.
- Tokens especiales: `pad`, `bos`, `eos`, `unk`, FIM, roles y tool calling.
- Serialización: JSON versionado `nexus-byte-bpe`.
- Integridad: SHA-256 del artefacto canónico.
- Orden de empates: frecuencia descendente y bytes lexicográficos.
- Padding: explícito con `max_length` y `--padding`.
- Truncation: nunca silenciosa; requiere `--truncation`.
- Decode: reconstrucción exacta para cualquier secuencia UTF-8 válida.

El primer tokenizer entrenado debe fijarse con un corpus versionado que incluya
una mezcla representativa de código, documentación, logs, errores y ejemplos
de tool calling. El artefacto guarda hashes SHA-256 de cada archivo del corpus;
no se deben subir repositorios privados al repositorio.

## Entrenamiento reproducible

Desde la raíz:

```text
python scripts/nexus_tokenizer.py train \
  training/corpus \
  --output artifacts/nexus-tokenizer-1.0.0.json \
  --vocab-size 65536 \
  --min-frequency 2
```

El mismo conjunto de bytes y parámetros genera el mismo vocabulario. Para
auditarlo, conserva el archivo de configuración, los hashes del corpus y la
versión del script junto al artefacto.

## Uso

```text
python scripts/nexus_tokenizer.py encode artifacts/nexus-tokenizer-1.0.0.json \
  "namespace App\\Services;" --max-length 128 --padding

python scripts/nexus_tokenizer.py decode artifacts/nexus-tokenizer-1.0.0.json \
  "[12, 98, 301]"

python scripts/nexus_tokenizer.py measure artifacts/nexus-tokenizer-1.0.0.json \
  training/corpus
```

La métrica `tokens_per_byte` permite comparar variantes. Deben medirse por
separado archivos PHP/Laravel, Dart/Flutter, JS/TS, SQL, configuración, logs,
stack traces y comandos. Además de compresión, la evaluación debe comprobar
round-trip exacto y cobertura de símbolos.

## Versionado y evolución

`1.0.0` fija el formato, los tokens especiales y la semántica de encode/decode.
Un cambio en corpus, merges, tokens especiales o reglas de truncation requiere
una nueva versión del artefacto. Los checkpoints de Nexus AI deben almacenar el
hash del tokenizer; nunca se debe cambiar el tokenizer silenciosamente después
de entrenar el modelo.
