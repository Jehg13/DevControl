# Nexus Proactive Intelligence

Nexus ejecuta `nexus:proactive` cada cinco minutos junto con el escaneo normal.
La revisión reutiliza los hallazgos existentes, filtra severidades informativas,
confirma la evidencia persistida y agrupa hallazgos relacionados por proyecto,
tipo y severidad.

## Antispam

Cada grupo se guarda en `nexus_proactive_alerts` con huella, ocurrencias,
primera/última detección, evidencia y última notificación. Las alertas nuevas se
notifican una vez; las repetidas respetan el cooldown configurado. Las alertas
altas pueden repetirse después del número de ocurrencias configurado. El historial
permanece aunque la notificación sea suprimida.

Silenciar una alerta:

```powershell
php artisan nexus:proactive --silence=12 --minutes=1440
```

Configuración:

```env
NEXUS_PROACTIVE_ENABLED=true
NEXUS_PROACTIVE_COOLDOWN_MINUTES=60
NEXUS_PROACTIVE_HIGH_PRIORITY_REPEAT=3
NEXUS_PROACTIVE_MINIMUM_SEVERITY=2
```

La notificación usa `DevControlAlertService`, por lo que respeta las preferencias
de alertas, correo, categoría y prioridad mínima existentes. Las incidencias
automáticas siguen siendo deduplicadas por Nexus Audit; la tabla proactiva evita
que una observación persistente genere correo en cada ciclo.

El comando puede ejecutarse manualmente:

```powershell
php artisan nexus:proactive
```
