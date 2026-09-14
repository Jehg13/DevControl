# Fase 30 — Primer entrenamiento de Nexus AI

Esta fase implementa un entrenamiento funcional de extremo a extremo sin
servicios externos y sin requerir PyTorch o NumPy. El modelo es un prototipo
pequeño para validar el contrato de datos, entrenamiento, evaluación,
checkpoints y recuperación. No representa todavía el modelo final de 2.3B.

## Modelo

`NexusMicroModel` es un modelo causal mínimo con:

- embeddings de tokens;
- contexto causal del token anterior;
- cabeza de vocabulario;
- softmax y pérdida cross-entropy;
- descenso de gradiente estocástico;
- generación greedy.

La interfaz de checkpoint queda preparada para sustituirse por un decoder-only
Transformer completo cuando se incorpore un runtime tensorial. La reducción
del contexto a un token es deliberada: permite ejecutar la primera prueba en
una instalación Python limpia y validar todo el pipeline antes de invertir en
dependencias de entrenamiento.

## Ejecución

```text
python scripts/nexus_train.py artifacts/nexus-dataset-1.0.0 \
  --output artifacts/nexus-training-0.1.0 \
  --vocab-size 65536 \
  --hidden-size 32 \
  --epochs 3 \
  --eval-interval 10 \
  --checkpoint-interval 25
```

El dataset debe contener shards `training-*.jsonl` y, opcionalmente,
`validation-*.jsonl`.

## Artefactos

El entrenamiento produce:

- `checkpoint-00000025.json`: checkpoint periódico;
- `latest.json`: último estado recuperable;
- `training-log.jsonl`: métricas por paso;
- `summary.json`: resumen final.

Cada checkpoint contiene el estado del modelo, epoch, paso, configuración y
hash del dataset. Reanudar contra otro dataset se rechaza:

```text
python scripts/nexus_train.py artifacts/nexus-dataset-1.0.0 \
  --output artifacts/nexus-training-0.1.0 \
  --vocab-size 65536 \
  --epochs 5 \
  --resume artifacts/nexus-training-0.1.0/latest.json
```

## Métricas

Se registran:

- loss de entrenamiento;
- validation loss;
- tokens procesados;
- tiempo transcurrido;
- memoria pico observada;
- cantidad de CPUs;
- plataforma;
- checkpoints generados.

La utilización GPU se representa como no disponible en este prototipo porque
no se asume un runtime externo. Un entrenador tensorial posterior debe añadir
memoria VRAM, utilización GPU, throughput y precisión BF16/FP16.

## Reproducibilidad

La semilla controla inicialización y orden de ejemplos. El hash de todos los
shards forma parte del checkpoint. Para una reproducción exacta se deben
conservar:

- dataset y sus hashes;
- tokenizer;
- configuración;
- semilla;
- versión del código;
- plataforma Python.
