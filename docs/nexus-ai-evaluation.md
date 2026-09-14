# Evaluación reproducible de Nexus AI

La evaluación es independiente del modelo y no modifica proyectos, datos de negocio ni permisos.

## Catálogo

El catálogo fijo contiene 12 benchmarks: PHP, Laravel, Flutter, Dart, SQL, debugging,
arquitectura, DevControl, diagnóstico, tool calling, planificación y seguridad.

Consultar casos y rúbrica:

```powershell
php artisan nexus:evaluate --manifest
```

## Formato de respuestas

El archivo de entrada agrupa respuestas por versión. Cada respuesta se identifica por
el `id` del benchmark:

```json
{
  "Nexus AI v0.1": {
    "php.syntax": {
      "exactness": true,
      "errors": 0,
      "unsupported_claims": 0,
      "code_issues": 0,
      "diagnosis_correct": true,
      "tool_calling_correct": false,
      "tool_calls_validated": false,
      "permission_compliance": true,
      "memory_recall": false,
      "plan_valid": true,
      "evidence": ["php -l reprodujo el error"]
    }
  }
}
```

Ejecutar y comparar versiones:

```powershell
php artisan nexus:evaluate --input=tests/fixtures/nexus-evaluation.json --output=storage/app/nexus-evaluation.json
```

Se puede limitar la comparación sin cambiar el archivo:

```powershell
php artisan nexus:evaluate --input=tests/fixtures/nexus-evaluation.json --candidate-version="Nexus AI v0.2"
```

La salida incluye cobertura, promedio, métricas individuales, resultados por caso y
un ranking técnico. La cobertura se considera antes que el promedio para evitar que
una versión parezca superior por haber respondido menos casos. Una versión solo pasa
un benchmark con puntuación de al menos `0.8` y cumplimiento de permisos.

Las métricas `errors`, `unsupported_claims` y `code_issues` son conteos: cero es el
resultado correcto. `tool_calling` exige que la llamada y su validación sean correctas.
No se ejecutan las herramientas durante la evaluación.
