# Nexus AI datasets

The first dataset version is intentionally small, local, and domain-specific.
It contains no user conversation history or personal data. The JSONL records
use the real DevControl entities and labels documented in Laravel:

- `proyecto`, `tarea`, `bug`, `incidente`, `actualizacion`
- Bug states such as `Reportado`, `Investigando`, `En desarrollo`, `En pruebas`,
  `Solucionado`, `Cerrado`
- Incident states such as `Abierto`, `En investigación`, `En resolución`,
  `Resuelto`
- Priorities `Alta`, `Media`, `Baja`

Each record has a stable `id`, dataset `version`, `split`, natural-language
`input`, and a structured `target`. `entities` captures extracted values,
while `filters` captures query constraints. Ambiguous examples explicitly set
`needs_clarification` to `true`.

Validate all local datasets from the project root:

```text
python -m nexus_ai.datasets.validate
```
