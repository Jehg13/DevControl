# Nexus AI — Especificación de arquitectura neuronal

## Estado de la especificación

- Fase: 27
- Estado: diseño técnico, sin entrenamiento
- Dominio: programación, debugging y operación segura de DevControl
- Objetivo: definir un modelo local entrenable y desplegable por etapas
- Modelo inicial propuesto: decoder-only Transformer denso de aproximadamente
  2.3B parámetros

Nexus AI no pretende competir con modelos generales. Será un modelo
especializado que trabaja junto al Cognitive Core, Knowledge Engine, Memory,
Code Intelligence, Planner, Reflection, Dataset Engine, Learning Engine y
Permission Manager existentes.

## Decisión arquitectónica

La arquitectura recomendada es híbrida:

```text
Nexus Core determinista
  ├── Cognitive Core
  ├── Knowledge Engine
  ├── Code Intelligence
  ├── Memory / Experience Memory
  ├── Planner / Reflection
  └── Permission Manager
          |
          v
Nexus AI — decoder-only Transformer
  ├── interpretación técnica
  ├── debugging
  ├── planificación
  ├── explicación
  └── tool calls estructuradas
          |
          v
Supervisor de herramientas
  ├── validación JSON Schema
  ├── permisos
  ├── sandbox
  ├── timeout / presupuesto
  └── auditoría
```

El modelo no ejecuta herramientas ni concede permisos. Solo propone texto,
decisiones o llamadas estructuradas. El sistema actual continúa siendo la
autoridad sobre ejecución, memoria, seguridad y cambios.

## Comparación de arquitecturas

| Alternativa | Ventajas | Costes y riesgos | Decisión |
|---|---|---|---|
| Decoder-only denso | Ecosistema maduro, inferencia local sencilla, compatible con FIM y tool calling | La capacidad crece con los parámetros activos | **Elegida para Nexus AI 1.x** |
| Modelo pequeño especializado en código | Mejor distribución de capacidad para símbolos, sintaxis y parches | Requiere datos de calidad y puede perder conversación general | **Combinar con instruct y debugging** |
| Encoder-decoder | Bueno para transformación y resumen | Menos natural para agentes autoregresivos y runtimes locales | No elegir inicialmente |
| MoE | Más capacidad total con pocos parámetros activos | Memoria de todos los expertos, routing, balanceo y kernels complejos | Escalamiento 3.x |
| Atención lineal/híbrida | Menor coste en secuencias largas | Menor compatibilidad y más complejidad de entrenamiento | Evaluar después de 7B |
| Decoder + RAG/embeddings | Recupera código y conocimiento sin ampliar siempre la ventana | Requiere indexado y reranking | **Obligatorio** |

Un modelo denso reduce el riesgo técnico inicial. Los modelos MoE actuales
demuestran una ruta de escalamiento atractiva, pero sus parámetros totales
siguen necesitando almacenamiento aunque solo una parte se active por token.

## Especificación de Nexus AI 1.0

### Configuración base

| Parámetro | Valor propuesto |
|---|---:|
| Parámetros | aproximadamente 2.3B |
| Capas Transformer | 30 |
| Dimensión oculta | 2,560 |
| Dimensión por head | 128 |
| Query heads | 20 |
| Key/Value heads | 5 |
| Tipo de atención | GQA |
| Dimensión KV | 640 por capa |
| FFN intermedio | 6,912 |
| Activación FFN | SwiGLU |
| Normalización | RMSNorm pre-norm |
| Positional encoding | RoPE |
| RoPE theta | 1,000,000 |
| Contexto nativo | 32,768 tokens |
| Contexto operativo inicial | 8,192–16,384 tokens |
| Vocabulario | 64,000 tokens |
| Embeddings de entrada | 2,560 |
| Output head | linear 2,560 → vocabulario |
| Tied embeddings | sí |
| Dropout | 0 durante inferencia; configurable durante entrenamiento |
| Precisión de entrenamiento | BF16, con FP32 para acumulaciones críticas |
| Precisión local | Q4/Q5/Q6/Q8 según hardware |

La cifra de parámetros es aproximada porque depende del vocabulario, los
embeddings ligados y la implementación exacta del FFN. Antes de iniciar el
entrenamiento debe generarse un reporte de parámetros y congelarse la
configuración.

### Atención GQA

Cada capa utiliza 20 heads de consulta y 5 heads compartidos de
key/value. Esto reduce la memoria de la KV cache frente a MHA, manteniendo
capacidad suficiente para código y tool calls.

La fórmula aproximada de la KV cache es:

```text
2 × capas × tokens × KV_heads × head_dim × bytes_por_elemento
```

Por ello el contexto máximo no debe utilizarse automáticamente. Nexus debe
recuperar primero los símbolos, archivos, pruebas, commits y errores
relevantes.

### RoPE

RoPE se usará con theta elevado para favorecer contexto de 32K. La extensión
a 64K o 128K requerirá evaluación específica de extrapolación y no se debe
activar solo cambiando una variable de configuración.

La longitud máxima, el theta, los factores de escalamiento y el tokenizer
forman parte inseparable del checkpoint.

### Tokenizer

Tokenizer inicial: byte-level BPE de 64K tokens con tokens especiales para
código y agentes.

Debe preservar correctamente:

- indentación;
- espacios relevantes;
- saltos de línea;
- rutas;
- namespaces;
- nombres compuestos;
- operadores;
- delimitadores;
- stack traces;
- JSON;
- SQL;
- comandos y salidas de herramientas.

Tokens especiales reservados:

```text
<|bos|> <|eos|> <|pad|> <|unk|>
<|system|> <|user|> <|assistant|> <|tool|>
<|tool_call|> <|tool_result|> <|analysis|> <|final|>
<|fim_prefix|> <|fim_middle|> <|fim_suffix|>
<|file|> <|path|> <|line|> <|commit|>
```

El tokenizer se entrenará únicamente sobre datos legalmente utilizables y
debidamente filtrados. No se cambiará después de comenzar el pretraining.

## Capacidades objetivo

### Programación

- continuación de código;
- Fill-in-the-Middle;
- generación de funciones;
- explicación de módulos;
- refactorizaciones acotadas;
- detección de errores sintácticos;
- generación de tests;
- migraciones y consultas SQL;
- PHP/Laravel;
- JavaScript/TypeScript;
- Dart/Flutter.

### Debugging

El modelo recibirá problema, contexto, evidencia y resultados verificables.
Debe separar:

```text
hecho observado
hipótesis
acción propuesta
resultado
solución validada
```

No debe presentar una hipótesis como un hecho.

### Tool calling

La salida estructurada debe usar un esquema cerrado:

```json
{
  "type": "tool_call",
  "tool": "nexus.code.intelligence",
  "arguments": {
    "project_id": 1,
    "path": "app/Services",
    "refresh": false
  },
  "reason": "Necesito confirmar las referencias antes de proponer un cambio."
}
```

El runtime valida nombre, argumentos, permisos y confirmación. Una llamada
inválida no se ejecuta y se devuelve como resultado de herramienta para que el
modelo pueda corregirla.

## Contexto y recuperación

Nexus AI no debe recibir un repositorio completo indiscriminadamente.

Pipeline recomendado:

```text
consulta del usuario
  -> Cognitive Core / Code Intelligence
  -> búsqueda simbólica y Knowledge Graph
  -> recuperación léxica
  -> embeddings de código
  -> reranking
  -> contexto compacto con citas
  -> Nexus AI
```

Cada fragmento recuperado debe incluir:

- proyecto;
- repositorio;
- ruta;
- lenguaje;
- símbolo;
- rango de líneas;
- commit/fingerprint;
- relación del grafo;
- fuente;
- confianza.

### Modelo de embeddings

Los embeddings serán un componente separado del decoder. Inicialmente se puede
utilizar un modelo de embeddings de código compatible con el runtime local,
con una interfaz propia para poder sustituirlo por embeddings entrenados por
DevControl posteriormente.

Configuración conceptual inicial:

```text
dimensión: 768
indexado: archivos, símbolos, documentación, tests, errores y commits
recuperación: híbrida léxica + vectorial
reranker: opcional en la primera versión
```

El embedding no autoriza acciones y no sustituye al Knowledge Graph. El grafo
representa relaciones; el índice vectorial recupera contenido similar.

## Memoria externa

La memoria no se codifica dentro de los pesos del modelo en la primera versión.

Se mantienen separados:

- memoria conversacional;
- Experience Memory;
- Knowledge Graph;
- Code Intelligence;
- métricas y Learning Engine;
- dataset versionado.

Esto permite corregir o invalidar conocimiento sin reentrenar inmediatamente
el modelo.

## Datos y objetivos de entrenamiento

### Fases de entrenamiento

1. **Continued pretraining**
   - código permitido;
   - documentación técnica;
   - tests;
   - SQL;
   - configuraciones no sensibles;
   - errores y diagnósticos anonimizados.

2. **Especialización instructiva**
   - problemas técnicos;
   - explicaciones;
   - análisis de impacto;
   - planificación;
   - separación entre evidencia e hipótesis.

3. **Fill-in-the-Middle**
   - prefijo, hueco y sufijo;
   - parches;
   - métodos incompletos;
   - tests faltantes.

4. **Tool use**
   - trayectorias completas;
   - llamadas válidas;
   - argumentos inválidos;
   - herramientas inexistentes;
   - errores de ejecución;
   - reintentos y cambio de estrategia.

5. **Debugging verificable**
   - problema;
   - evidencia;
   - hipótesis;
   - parche;
   - tests;
   - resultado real.

Los datasets se producirán mediante Dataset Engine y Learning Engine, pero
cada versión debe pasar revisión de calidad y evaluación antes de incorporarse.

### Objetivos de pérdida

La primera versión puede utilizar una pérdida combinada:

```text
L_total =
  0.55 × causal_language_modeling
  0.20 × fill_in_the_middle
  0.15 × structured_tool_calling
  0.10 × technical_decision_format
```

Los pesos son iniciales y deben ajustarse mediante validación. La pérdida no
es suficiente para medir debugging: debe complementarse con compilación, tests,
validación de patches y exactitud de tool calls.

## Parámetros entrenables

### Pretraining

Entrenables:

- embeddings;
- todas las capas Transformer;
- RMSNorm;
- output head si no está ligado.

### LoRA/QLoRA

Para iteración local se recomienda entrenar adaptadores primero en:

- proyecciones `q_proj`;
- `k_proj`;
- `v_proj`;
- `o_proj`;
- proyecciones del SwiGLU.

El modelo base permanecerá congelado durante las primeras especializaciones.
Cada adaptador tendrá su propio dataset, versión, métricas y licencia.

Adaptadores previstos:

```text
nexus-code
nexus-debugging
nexus-devcontrol
nexus-tool-use
nexus-php-laravel
nexus-dart-flutter
```

No se deben fusionar adaptadores sin evaluación de regresión.

## Hardware y despliegue por etapas

Las cifras son aproximadas y dependen de batch, contexto, cuantización y
runtime.

### Etapa inicial

```text
CPU: 8–16 núcleos
RAM: 32 GB mínimo
GPU: 8–12 GB VRAM o 16–24 GB de memoria unificada
Modelo: 2.3B en Q4/Q5
Contexto operativo: 8K–16K
Runtime: llama.cpp/GGUF o Transformers
```

Uso esperado:

- explicación de errores;
- consultas de símbolos;
- parches pequeños;
- generación de tests;
- tool calls de lectura.

### Etapa local práctica

```text
VRAM: 16–24 GB o 64 GB de memoria unificada
RAM: 64 GB recomendados
Modelo: 3B–7B Q4/Q5
Contexto: 16K–32K
```

### Etapa futura

```text
VRAM: 24–48 GB o múltiples GPUs
Modelo: 7B–14B denso
Contexto: 32K–128K
Runtime: vLLM/SGLang cuando exista concurrencia
```

### Escalamiento MoE

Después de validar datos y evaluaciones se puede diseñar un MoE de 16B–30B
totales con aproximadamente 2B–4B activos. No debe ser el primer modelo
propio: reutilizará el tokenizer, contratos, datasets y evaluaciones del
modelo denso.

## Formatos de checkpoint

### Formato canónico

Safetensors será el formato de entrenamiento y distribución principal:

```text
nexus-ai-2.3b/
  config.json
  tokenizer.json
  tokenizer_config.json
  special_tokens_map.json
  model-00001-of-00004.safetensors
  model-00002-of-00004.safetensors
  model-00003-of-00004.safetensors
  model-00004-of-00004.safetensors
  model.safetensors.index.json
  generation_config.json
  training_config.json
  evaluation.json
  LICENSE
  README.md
```

Safetensors es el formato canónico porque evita depender de deserialización
arbitraria de pickle y permite carga eficiente por tensor.

### Adaptadores

```text
adapter_model.safetensors
adapter_config.json
training_config.json
evaluation.json
README.md
```

Los adaptadores requieren declarar el identificador y hash del modelo base.

### Inferencia local

GGUF será un artefacto derivado para llama.cpp:

```text
Safetensors BF16/FP16 -> GGUF Q8_0
                       -> GGUF Q6_K
                       -> GGUF Q5_K_M
                       -> GGUF Q4_K_M
```

El GGUF no sustituye al checkpoint de entrenamiento. La conversión debe
registrar versión del conversor, commit del modelo, tokenizer y parámetros de
cuantización.

## Evaluación

### Evaluación estática

- pérdida por conjunto;
- exactitud de formato;
- JSON válido;
- nombre de herramienta válido;
- argumentos conformes al schema;
- separación de hecho e hipótesis;
- recuperación de símbolos;
- clasificación de lenguaje/framework.

### Evaluación ejecutable

- tests generados que compilan;
- patches aplicables;
- regresión de tests;
- reducción de errores;
- análisis de impacto correcto;
- comandos bloqueados correctamente;
- no ejecución de herramientas sin autorización.

### Métricas mínimas

```text
tool_call_validity
tool_argument_validity
patch_apply_rate
test_pass_rate
debug_success_rate
false_claim_rate
secret_leak_rate
permission_violation_rate
average_steps
latency
tokens_per_second
```

Un modelo no puede promoverse aunque tenga buena pérdida si empeora
`secret_leak_rate`, `permission_violation_rate` o `false_claim_rate`.

## Seguridad y gobernanza

Nexus AI no podrá:

- cambiar Permission Manager;
- concederse permisos;
- modificar restricciones;
- alterar el modelo de seguridad;
- escribir código crítico sin una herramienta autorizada;
- ejecutar shell libre;
- acceder a secretos;
- convertir una hipótesis en un hecho;
- publicar o entrenar automáticamente un checkpoint.

Todo checkpoint debe incluir:

- origen de datos;
- versión del dataset;
- filtros aplicados;
- licencia;
- configuración;
- hash;
- evaluación;
- límites conocidos;
- adaptadores fusionados;
- fecha de entrenamiento.

## Roadmap de implementación

### 27.1 — Contratos

- `NexusModel` ya existente como frontera;
- definir `Nexus AI Adapter`;
- definir esquema estable de tool calls;
- definir formato de contexto y citas.

### 27.2 — Datos

- auditar Dataset Engine;
- agregar trayectorias de tool use;
- validar anonimización;
- crear conjuntos estáticos y ejecutables;
- congelar splits.

### 27.3 — Prototipo

- entrenar o adaptar un modelo de 1.5B–3B;
- probar FIM;
- probar tool calls de lectura;
- ejecutar evaluación en repositorios propios.

### 27.4 — Agente controlado

- integrar RAG;
- integrar resultados de Code Intelligence;
- ejecutar únicamente herramientas autorizadas;
- registrar métricas y Learning Engine.

### 27.5 — Escalamiento

- probar 7B–14B;
- comparar densos contra MoE;
- ampliar contexto solo con evidencia;
- optimizar serving y concurrencia.

## Referencias técnicas

- [StarCoder2-3B](https://huggingface.co/bigcode/starcoder2-3b)
- [Qwen2.5-Coder-7B-Instruct](https://huggingface.co/Qwen/Qwen2.5-Coder-7B-Instruct)
- [DeepSeek-Coder-V2-Lite-Instruct](https://huggingface.co/deepseek-ai/DeepSeek-Coder-V2-Lite-Instruct)
- [Qwen3-Coder-30B-A3B-Instruct](https://huggingface.co/Qwen/Qwen3-Coder-30B-A3B-Instruct)
- [Qwen3-Coder-Next](https://huggingface.co/Qwen/Qwen3-Coder-Next)
- [Safetensors](https://huggingface.co/docs/safetensors/index)
- [PEFT checkpoints](https://huggingface.co/docs/peft/en/developer_guides/checkpoint)
- [GGUF](https://github.com/ggml-org/ggml/blob/master/docs/gguf.md)
- [Transformers GGUF](https://huggingface.co/docs/transformers/main/quantization/gguf)

## Decisión final

Nexus AI debe comenzar como un **decoder-only Transformer denso de
aproximadamente 2.3B parámetros**, con RoPE, GQA, RMSNorm, SwiGLU, tokenizer
byte-level BPE de 64K, contexto nativo de 32K, FIM, tool calling estructurado,
RAG híbrido y embeddings separados.

El modelo será útil porque estará especializado en el flujo técnico de
DevControl, no porque intente resolver cualquier dominio. Safetensors será el
formato canónico; GGUF será el formato de ejecución local; LoRA/QLoRA será el
mecanismo inicial de especialización.

Todavía no se entrenará ningún modelo en esta fase.
