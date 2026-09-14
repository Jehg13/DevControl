# Nexus AI Runtime

El runtime es un proceso independiente de Laravel y de los flujos de Nexus. Cada
versión se define en [nexus-runtime.json](../config/nexus-runtime.json) y apunta a
un checkpoint y tokenizer concretos. La selección es exacta: si la versión no existe,
el proceso termina sin usar otra versión como fallback.

## Health check

```powershell
python scripts/nexus_runtime.py --version "Nexus AI v0.1" --health
```

## Inferencia y streaming

```powershell
python scripts/nexus_runtime.py --version "Nexus AI v0.1"
```

Después envía líneas JSON por stdin:

```json
{"prompt":"Resume el proyecto","context":["solo usa estos datos"],"tool_calls":[]}
```

El proceso emite eventos `token`, `complete`, `runtime_complete` y `error`.
Las herramientas no se ejecutan en el runtime: solo se validan contra la lista
de la versión, dejando su ejecución a una capa externa autorizada.

## Telemetría

Cada ejecución registra:

- versión exacta;
- modelo y tokenizer;
- plataforma, Python, CPU y memoria máxima observada;
- duración;
- tokens de prompt y completion;
- memoria contextual;
- herramientas solicitadas;
- resultado;
- errores.

El runtime mantiene memoria conversacional propia y limita el contexto recuperado.
No comparte estado mutable entre procesos. Los checkpoints originales permanecen
intactos y cada versión puede apuntar a artefactos distintos.
