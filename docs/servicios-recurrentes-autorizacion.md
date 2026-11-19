# Programación recurrente: activación completada

## Ejecución autorizada · 16 de noviembre de 2026, 13:02

Se ejecutó exclusivamente el comando autorizado, sin `--force` ni otras rutas:

```text
php artisan migrate --path=database/migrations/2026_11_15_000009_create_service_schedules.php
```

Antes de ejecutarlo se confirmó nuevamente `SELECT DATABASE() = ar_menu_equipo` y la integridad SHA-256 del respaldo externo indicado abajo. La revisión del archivo confirmó únicamente creación de tablas nuevas, columnas opcionales e índices, sin cambios de filas históricas.

Resultado: 64 tablas base, con las 62 tablas anteriores conservadas. Comparación de todos los registros y columnas originales: idénticos, salvo una única entrada nueva esperada en `migrations`. Los 866 registros de la referencia previa permanecen conservados. Los 280 archivos originales de imágenes, recursos y vistas comparados por SHA-256 tampoco cambiaron.

La instalación real reconoce el esquema completo y el índice único por programación/fecha. El formulario real ya muestra las cuatro modalidades, los siete horarios diarios, la vigencia y «Guardar y enviar a revisión» habilitado; desapareció el aviso de migración pendiente.

Se realizó una comprobación nueva de activación sobre tablas InnoDB, dentro de una transacción revertida al finalizar, utilizando los controladores reales y roles existentes de la misma sede: guardar una programación personalizada, comprobar su solicitud pendiente en la bandeja del Gerente, aprobarla, generar automáticamente dos jornadas con horarios distintos, verificar la ausencia de duplicados, abrir el planificador de menú de una jornada específica y asignar un mesero a una jornada sin asignarlo a la siguiente. No quedaron programaciones, servicios ni asignaciones de comprobación; no se modificaron menús existentes.

Evidencia privada: `storage/app/private/backups/recurrence-activation-result.json` y `recurrence-live-result.json`. No se repitieron las suites anteriores de pruebas, no se ejecutaron otras migraciones ni se modificaron diseños o permisos. Sin commit ni push.

Las secciones siguientes conservan el diagnóstico previo y el detalle de la autorización; su estado pendiente queda sustituido por esta activación completada.

Revisión del 16 de noviembre de 2026. Base comprobada mediante `SELECT DATABASE()`: **ar_menu_equipo**.

## Causa del bloqueo al guardar

La migración `2026_11_15_000009_create_service_schedules` no aparece en el historial y sus estructuras tampoco existen. `ServiceRecurrence::ready()` devuelve falso. Por ello el formulario de Nuevo servicio muestra el aviso indicado y deshabilita «Guardar y enviar a revisión»; el endpoint `chef.schedules.store` rechaza con 503 cualquier envío directo. No es un problema del reloj, del archivo de portada ni de los permisos del Jefe de cocina.

La ruta de creación existente ya utiliza `ServiceScheduleController`; no es necesario duplicarla ni rediseñar las vistas.

## Única migración que requiere autorización

`database/migrations/2026_11_15_000009_create_service_schedules.php`

- Crear `service_schedules`: sede, solicitante y revisión activa.
- Crear `service_schedule_revisions`: propuestas inmutables, modalidad, vigencia, días, horarios, datos descriptivos y portada en JSON; estado de aprobación, revisor y observaciones. Su clave foránea solo relaciona las dos tablas nuevas.
- Añadir a `servicios` las columnas opcionales `schedule_id` y `schedule_revision_id`.
- Añadir el índice único `servicios_schedule_date_unique (schedule_id, fecha)` para evitar jornadas duplicadas dentro de una programación.

Es aditiva: no contiene DELETE, TRUNCATE, DROP de tablas ni actualización o reasignación de servicios existentes. Las columnas nuevas son nullable, por lo que los 20 servicios previos conservarán `schedule_id = NULL`; sus fechas, fotografías, menús, atenciones y asignaciones no se convierten ni alteran. El índice admite esos registros históricos con programación nula.

El esquema pasaría de 62 a 64 tablas base. No se autoriza ni se necesita ejecutar ninguna otra migración.

Ya existen `servicios.cover_url`, `servicios.menu_cover_url`, `menus.id_servicio` y `zona_meseros.id_servicio`, con los índices de asignaciones por jornada. No hacen falta migraciones de fotografías, menús, plantillas gastronómicas ni Capitán. La migración opcional de descripción de servicios tampoco es requisito: la descripción de la programación se conserva en el JSON de su revisión.

Comando previsto, **no ejecutado**:

```text
php artisan migrate --path=database/migrations/2026_11_15_000009_create_service_schedules.php --force
```

## Respaldo externo actualizado y verificado

- Ubicación fuera del repositorio y de la raíz web: `C:\xampp\backups\NUTRILIADO\ar_menu_equipo-before-recurrence-20261116-125546.sql`.
- Manifiesto: mismo nombre con extensión adicional `.json`; incluye auditoría de las estructuras existentes.
- 62 tablas verificadas; 152616 bytes.
- SHA-256: `37d857fc503ca349feb3ae4dc9a5ccbc13a4bdc750d331920970c6ac7ac82de7`.
- Exportación consistente con `--single-transaction --skip-lock-tables`, rutinas, eventos y triggers; salida exitosa, marca de exportación completa y todas las tablas presentes. No se realizó restauración ni se afirma haberla probado.

Si hay operaciones entre esta revisión y la autorización, renovar este respaldo antes de aplicar únicamente el archivo indicado y comparar después las filas y recursos originales.

## Preparación funcional realizada

La infraestructura existente implementa las cuatro modalidades (diaria, semanal, personalizada y evento), horarios por día, vigencia abierta o acotada, revisiones pendientes y aprobación por otro Gerente autorizado de la misma sede. Las notificaciones y la bandeja de aprobación del Gerente ya leen esas revisiones cuando el esquema está habilitado.

Las jornadas se materializan automáticamente en las consultas existentes de Cocina y de operación, con bloqueo de la programación y consulta previa por fecha más índice único. El comando existente `kitchen:materialize` permite generar un periodo acotado si se desea ejecución programada externa; no se instaló una tarea del sistema ni se generaron servicios reales durante esta revisión.

Cada jornada es un `id_servicio` independiente: los menús y las asignaciones del Capitán se vinculan a ese identificador, no a la programación entera. La copia de menú existente crea un borrador independiente; no reemplaza el menú original.

Se reforzó `ready()` para exigir las columnas completas y el índice de unicidad, en lugar de considerar suficiente una instalación parcial. También se protegieron las jornadas con asignaciones previas del Capitán frente a cambios posteriores de horario/capacidad por una revisión de la programación; permanecen intactas sus responsabilidades e historial.

## Comprobación nueva y aislada

Una prueba dirigida, 22 aserciones, correcta en SQLite `:memory:`: reproduce el bloqueo sin esquema, rechaza una estructura incompleta sin índice, registra las cuatro modalidades usando los campos enviados por el formulario real (incluidos horarios generales ausentes en personalizado), conserva las solicitudes pendientes y protege una jornada asignada frente a una nueva revisión aprobada.

No se repitieron las pruebas anteriores de recurrencia, autenticación, menús, fotografías, reloj o Capitán. No se rediseñó ninguna vista ni se cambiaron permisos. No se modificaron datos de la instalación real, no se ejecutaron migraciones, commit ni push.

**Estado:** corrección preparada y respaldo externo disponible. La habilitación en la instalación real requiere autorización expresa de la única migración anterior y una comprobación posterior de sus estructuras y conservación de registros.
