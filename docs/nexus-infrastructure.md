# Fase 49 — Infraestructura de Nexus AI

## Diagnóstico medido

El equipo actual medido en Windows es:

- AMD Ryzen 7 5700U, 8 núcleos y 16 hilos.
- 10.82 GiB de RAM física.
- Radeon integrada; el sistema reporta 1 GiB compartido, no VRAM dedicada.
- No hay `latest.json` ni `tokenizer.json` instalados en
  `storage/app/nexus-model/`.

El comando reproducible es:

```powershell
python scripts/nexus_hardware.py
python scripts/nexus_hardware.py --json
```

La ausencia de artefactos impide calcular el tamaño exacto del modelo activo.
El script lo informa como ausente, en lugar de inventar parámetros.

## Requisitos del runtime actual

El micro-modelo de Nexus usa matrices `vocab_size x hidden_size` para embeddings
y salida. Su cantidad de escalares es:

```text
parámetros = 2 × vocab_size × hidden_size
```

El checkpoint JSON ocupa más que los pesos binarios por serialización. Para
inferencia se necesita aproximadamente el tamaño de los pesos, tokenizer,
runtime y un margen del 20%; el contexto de 2,048 tokens aumenta memoria de
activaciones, pero este modelo no mantiene una KV cache de Transformer real.
El batch actual por defecto es 1 y el entrenamiento usa `batch_size=1`.

El equipo actual es suficiente para:

- entrenar el micro-modelo y datasets pequeños;
- ejecutar tokenizer, benchmarks, diagnósticos y generación CPU;
- probar cuantización int8, aunque no se debe asumir que será más rápida.

No es razonable usarlo para fine-tuning o preentrenamiento de modelos
multibillonarios: no tiene VRAM dedicada y solo deja un margen pequeño para
Laravel, Python y el sistema operativo.

## Configuraciones calculadas

| Perfil | RAM | VRAM dedicada | Almacenamiento libre | CPU | Energía/refrigeración | Tamaño razonable |
|---|---:|---:|---:|---|---|---|
| Hardware actual | 10.8 GiB | 0 GiB | 20 GiB | Ryzen 7 5700U | 15–35 W sostenidos; refrigeración de portátil | Micro-modelo actual; hasta ~50M parámetros en CPU con contexto corto |
| Upgrade económico | 32 GiB | 8 GiB | 100 GiB | 6–8 núcleos | 250–400 W; torre con buen flujo | 3B–7B cuantizado para inferencia; LoRA ligero |
| Workstation intermedia | 64 GiB | 16 GiB | 500 GiB | 8–16 núcleos | 450–650 W; disipador de torre | 7B–13B cuantizado; LoRA/QLoRA de 3B–7B |
| Workstation avanzada | 128 GiB | 24 GiB | 2 TiB | 16–32 núcleos | 800–1,200 W; fuente Gold/Platinum | 13B–34B cuantizado; fine-tuning de 7B–13B |
| Servidor de entrenamiento | 256 GiB | 80 GiB | 4 TiB | 32–64 núcleos | 1,500–3,000 W; refrigeración de servidor | 34B–70B cuantizado; fine-tuning grande |

La VRAM para entrenamiento no equivale al tamaño de los pesos. En Adam/AdamW
hay pesos, gradientes, estados del optimizador y activaciones; como regla
conservadora se reserva 4–8 veces el tamaño de los pesos, más contexto y
batch. Por eso una GPU de 8 GiB no convierte automáticamente un modelo 7B en
entrenable desde cero. Para fine-tuning con LoRA el multiplicador baja, pero
depende del backend, rango LoRA, batch y longitud de contexto.

## Dataset, tokenizer y almacenamiento

El dataset debe reservar su tamaño actual, al menos tres checkpoints,
validaciones, logs y temporales. Para un dataset de `D` GiB, un presupuesto
prudente es `D + 3 × checkpoint + 2 × D` durante entrenamiento. El tokenizer
byte-level preserva código y Unicode y su tamaño suele ser pequeño frente a
los pesos; no es el factor que determina la GPU.

## Decisión

No se recomienda comprar hardware todavía para el Nexus actual. Primero deben
generarse un checkpoint real y benchmarks con tokens/segundo, RAM, tamaño y
calidad. El upgrade económico solo tiene sentido si el objetivo cambia a
inferencia de un modelo 3B–7B cuantizado o a LoRA. Una workstation avanzada o
servidor se justifica únicamente con un dataset aprobado, objetivos de
fine-tuning medidos y una necesidad sostenida de throughput; más VRAM mejora
el tamaño/batch posible, pero no la calidad por sí sola.
