# Fase 38 — Nexus Autonomous Coding

La ejecución autónoma de código se implementa como un flujo explícito y
permission-gated. El modelo propone un `CodingPlan`; no modifica archivos
directamente.

## Flujo obligatorio

```text
objetivo
 -> análisis
 -> comprensión
 -> plan
 -> propuesta
 -> permisos
 -> modificación
 -> pruebas
 -> observación
 -> reflexión
 -> corrección
 -> validación
```

`AutonomousCoding` no recibe una respuesta libre del modelo: recibe un plan
estructurado con operaciones y comandos de prueba.

## Operaciones

```text
create
modify
delete
```

Permisos requeridos:

```text
nexus.code.create
nexus.code.modify
nexus.code.delete
```

Las rutas se resuelven dentro del workspace y se rechazan escapes mediante
`..`. Los commits y pull requests solo se preparan como propuestas; no se
publican automáticamente.

## Protección contra cambios humanos

El flujo acepta un `human_change_check` y un `expected_snapshot` capturado
después del análisis. Si el snapshot actual no coincide, la ejecución se
detiene antes de modificar archivos. Esto permite detectar cambios humanos
realizados durante la propuesta o aprobación.

## Pruebas y rollback

Después de modificar se ejecutan todos los comandos declarados. Si alguno
falla, el flujo entra en `rolled_back` y restaura bytes originales, incluyendo
archivos modificados y eliminación de archivos nuevos.

## Probar en proyectos de prueba

```text
python scripts/nexus_autonomous_coding.py test-project plan.json \
  --permissions nexus.code.create nexus.code.modify
```

Se recomienda usar un workspace temporal, permisos mínimos y comandos de
prueba enfocados antes de conectar el flujo a un proyecto real.
