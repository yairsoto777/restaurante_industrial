# Programaciones de servicios y jornadas

## Modelo y compatibilidad

`servicios` sigue representando una jornada fechada. Conserva sus identificadores y todas las relaciones existentes con `menus`, `atenciones`, comandas, grupos, ventas e incidencias. Los registros previos tienen `schedule_id = NULL` y continúan como servicios de una sola ocasión; no hay conversión ni eliminación de datos.

`service_schedules` identifica la programación y la sede. `service_schedule_revisions` conserva solicitudes y decisiones con una instantánea JSON de nombre, descripción, portada, modalidad, días, horarios, capacidad, vigencia, suspensión y motivo. Cada revisión tiene autor, alcance, fecha de efecto, estado, revisor y fecha/observaciones de revisión. `active_revision_id` identifica la última revisión general aprobada. Las excepciones de una fecha se conservan como revisiones con alcance `one`, sin reemplazar la programación general.

Una propuesta pendiente no sustituye revisiones aprobadas. Solo el gerente activo de la sede puede resolverla, y nunca su propio autor. Se permite una solicitud pendiente por programación; una revisión rechazada permanece en el historial. La aprobación general habilita todas sus jornadas sin nuevas solicitudes diarias. Las revisiones se aplican por fecha de efecto y orden de autorización.

## Jornadas y excepciones

La generación se ejecuta al consultar módulos operativos y al revisar programaciones. Horizonte habitual: 7 días desde el middleware y 35 días en cocina. El planificador y el generador amplían únicamente el periodo consultado (máximo 366 días de diferencia). No se crean jornadas anteriores a la fecha actual ni registros infinitos. Existe además `php artisan kitchen:materialize --site=ID --from=AAAA-MM-DD --until=AAAA-MM-DD`; no se requiere cron para operar localmente.

La transacción bloquea la programación y el índice único `(schedule_id, fecha)` impide duplicados concurrentes. Las primeras solicitudes pendientes pueden tener jornadas en borrador para preparar menús, nunca publicables. Las revisiones pendientes de una programación aprobada no generan jornadas bajo sus nuevas condiciones. Una aprobación actualiza únicamente jornadas futuras programadas/canceladas sin atenciones ni grupos; no toca jornadas en curso/finalizadas ni sus operaciones. La suspensión conserva el registro y sus relaciones con estado `Cancelado`.

Al editar se elige una fecha, esa fecha y siguientes, o toda la programación futura. Para reanudar se envía una modificación sin marcar suspensión. Los cambios extraordinarios de horario, capacidad, festivos y suspensión usan la misma revisión y autorización; el motivo queda registrado. Las jornadas con operaciones conservan su versión anterior, aunque cambie la programación futura.

## Menús y demás roles

Cada menú sigue asociado a un `id_servicio` concreto. El planificador permite seleccionar programación, fecha y semana/mes; distingue días sin servicio, borradores, menús configurados y estado operativo. La copia crea nuevas filas de menú como borrador, reutiliza los platillos y conserva independiente la portada; no sobrescribe un menú destino existente ni admite origen de otra sede.

El generador mantiene sus seis plantillas y configuración persistida. Se añadió filtro opcional por programación y generación acotada de jornadas del periodo. Los documentos públicos conservan filtros de aprobación, publicación y disponibilidad; las fechas sin jornada no aparecen como menús pendientes. No se modificaron sus plantillas, impresión ni exportación PDF.

Mesero, capitán, personal de cocina y cajero mantienen sus consultas y operaciones sobre jornadas (`servicios`). Su acceso genera el horizonte inmediato según sede y rol activo, sin rediseñar interfaces ni ampliar permisos. Las notificaciones del gerente corresponden a solicitudes de programación; las decisiones para cocina se notifican una vez por revisión, no por cada jornada.

## Migración pendiente de autorización

Únicamente `database/migrations/2026_11_15_000009_create_service_schedules.php`:

- Crea `service_schedules` y `service_schedule_revisions`.
- Añade `servicios.schedule_id` y `servicios.schedule_revision_id`, ambos nullable.
- Añade índices y unicidad por programación/fecha.
- No actualiza registros existentes, no elimina columnas/tablas y no ejecuta otras migraciones. Su reversión destructiva está bloqueada.

Comando propuesto, **no ejecutado**:

```text
php artisan migrate --path=database/migrations/2026_11_15_000009_create_service_schedules.php
```

Sin este esquema, las consultas existentes siguen funcionando y el formulario recurrente informa que está pendiente de habilitación. Ninguna migración opcional de fotografías/metadatos pendiente es requisito de esta funcionalidad.

Base real comprobada: `ar_menu_equipo`, 62 tablas, 20 servicios y 58 menús. Respaldo externo verificado (estructura de las 62 tablas, tablas indispensables, terminación del dump y SHA-256):

`C:\Users\johan\Backups\NUTRILIADO\ar_menu_equipo_service_recurrence_20261115_231100.sql`

Tamaño: 152690 bytes. SHA-256: `19f5dbc100cafeb61389de1e69b176f09fe73a93fa300e93d11df43bd072e7c0`.

## Validación

Tres pruebas nuevas, 55 comprobaciones, exclusivamente con SQLite en memoria: aprobación única y permisos, generación idempotente, horarios personalizados, revisiones pendientes, excepción por fecha, conservación de jornadas finalizadas y atenciones, copia independiente de menús, validación de horarios y vistas de programación/planificador/generador. Se comprobó sintaxis de JavaScript. No se hicieron pruebas en Chrome, impresión ni PDF, ni se repitió la suite previa de imágenes.

Pendiente tras autorización: ejecutar exclusivamente la migración indicada y comprobar su estructura en la instalación real. La operación recurrente permanece deshabilitada hasta entonces.
