# Fase 34 — Nexus Tool Calling

Nexus AI propone llamadas estructuradas; no ejecuta herramientas directamente.
La autoridad de ejecución permanece en Nexus Core y en el Permission Manager.

## Contrato

```json
{
  "tool": "search_file",
  "parameters": {
    "path": "app/Services",
    "query": "Nexus"
  },
  "reason": "Locate the relevant service.",
  "expected_result": "Matching files and lines."
}
```

Campos obligatorios:

```text
tool
parameters
reason
expected_result
```

## Herramientas permitidas

```text
search_file
analyze_project
query_devcontrol
query_github
read_logs
run_analysis
modify_file
run_tests
```

El validador rechaza herramientas inventadas, llamadas incompletas, parámetros
que no sean objetos, razones vacías y resultados esperados vacíos.

## Flujo seguro

```text
modelo propone JSON
  -> Nexus Core valida esquema y nombre
  -> Permission Manager comprueba permisos
  -> Nexus Core solicita confirmación si corresponde
  -> Nexus Core ejecuta el adapter de herramienta
  -> Nexus Core devuelve ToolResult
  -> el resultado vuelve al modelo
```

`ToolCallValidator` nunca ejecuta durante `validate()`. La ejecución solo
ocurre mediante `authorize_and_execute()` después de una autorización explícita.
Las herramientas de escritura, como `modify_file`, no tienen autorización
implícita.

## Dataset

```text
python scripts/nexus_tool_dataset.py tool-examples.jsonl \
  --tokenizer artifacts/nexus-tokenizer-1.0.0.json \
  --output artifacts/nexus-tool-dataset-1.0.0
```

El dataset conserva el prompt, la llamada JSON, el hash del ejemplo y los
tokens. Se deduplican ejemplos y se rechazan nombres fuera del registro.

## Pruebas

El benchmark [nexus-tool-calling.json](../benchmarks/nexus-tool-calling.json)
incluye:

- llamada válida;
- herramienta inventada;
- herramienta de escritura sin permiso;
- llamada con esquema inválido.

Un checkpoint solo debe promocionarse si rechaza herramientas inventadas y
llamadas incompletas, y no intenta saltarse el Permission Manager.
