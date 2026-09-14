# Fase 39 — Nexus Autonomous Maintenance

Esta fase implementa ciclos de mantenimiento continuos, auditables y
permission-gated. Nexus revisa señales y propone tareas; no realiza
modificaciones silenciosas.

## Revisiones

```text
project
dependencies
tests
errors
infrastructure
github
```

Cada reviewer devuelve hallazgos con evidencia, severidad, confianza,
ubicación y recomendación. Los hallazgos se ordenan por:

```text
CRITICAL > HIGH > MEDIUM > LOW > INFO
```

## Integración con DevControl

Cada hallazgo puede convertirse en una tarea propuesta:

```json
{
  "title": "Outdated dependency",
  "project": "devcontrol",
  "severity": "MEDIUM",
  "evidence": ["lockfile is outdated"],
  "source": "nexus.maintenance",
  "status": "proposed"
}
```

El `task_sink` permite conectarlo con el sistema de tareas de DevControl. La
persistencia siempre se acompaña de un archivo de auditoría por ejecución.

## Acciones

Las acciones requieren permisos `nexus.maintenance.<action>`. Las acciones
peligrosas requieren además aprobación explícita:

```text
delete
deploy
dependency_upgrade
modify_production
```

Un permiso por sí solo no autoriza una acción peligrosa. Sin aprobación, el
resultado es `approval_required`; nunca se ejecuta el runner.

## Ejecución

```text
python scripts/nexus_maintenance.py maintenance-input.json \
  --output artifacts/maintenance \
  --permissions nexus.maintenance.investigate
```

El input contiene el proyecto y resultados previamente observados por los
reviewers. La ejecución no descarga datos ni modifica el proyecto por sí sola;
los adapters de revisión y acción deben conectarse explícitamente.

## Auditoría

Cada corrida registra:

- identificador estable;
- inicio y fin;
- checks ejecutados;
- hallazgos;
- tareas;
- acciones;
- permisos y estados;
- ruta del archivo de auditoría.

La revisión periódica puede programarse externamente con un scheduler, pero
cada ciclo mantiene la misma frontera de permisos y aprobación humana.
