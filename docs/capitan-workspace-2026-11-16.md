# Capitán de meseros · rediseño de siete vistas

## Activación autorizada y completada · 16 de noviembre de 2026

Se confirmó mediante `SELECT DATABASE()` que la conexión real usa **ar_menu_equipo**. Se verificó el respaldo anterior y se generó uno fresco inmediatamente antes de ejecutar únicamente `2026_11_16_000001_prepare_captain_workspace.php`, mediante `migrate --path=database/migrations/2026_11_16_000001_prepare_captain_workspace.php --force`.

- Respaldo actualizado: `storage/app/private/backups/captain-authorized-20261116-122340.sql`, 151620 bytes, 62 tablas y exportación completa. Manifiesto `.sql.json`.
- SHA-256: `fe8b58ced36f706bf02eea4c69cdea6ae9acdd915794be230618d789aa769fa1`.
- Comparación antes/después de todas las filas y columnas originales de las 62 tablas: idénticas, salvo la única entrada nueva esperada en `migrations`. Se conservaron servicios recurrentes, menús, platillos, empleados, asignaciones e incidencias.
- Verificados por hash los 65 archivos multimedia anteriores: sin cambios.
- El índice único heredado se sustituyó por el índice de consulta autorizado. Se conservaron la clave primaria, los índices previos de zona y usuario y todas las filas. No se modificaron claves foráneas.
- Confirmadas las columnas para foto, descripción y color de zonas, asignación por `id_servicio` y relaciones opcionales de incidencias con servicio, zona y mesa. La evidencia fotográfica existente se conservó.
- Renderizado real, con usuario Capitán y sede existentes, de Zonas, Asignaciones y Comedor: funciones de esquema habilitadas, 4 zonas y 13 mesas. No se generaron registros ficticios ni se atribuyeron asignaciones generales históricas a jornadas inventadas.
- Se guardó una imagen mediante el mecanismo real de cargas, se recuperó en un segundo proceso y se descargó por HTTP desde Apache en la ruta de esta instalación con SHA-256 idéntico. No depende de la vista previa ni de la sesión. Solo se eliminó esa imagen desechable de comprobación; no se escribió ningún registro de operación.
- Evidencia privada: `storage/app/private/backups/captain-activation-result.json` y `captain-real-check-result.json`.
- No se repitieron las 12 pruebas previas. No se ejecutaron otras migraciones, restauraciones, modificaciones de datos, commit ni push.

Las secciones posteriores describen la preparación previa; su condición de migración pendiente queda sustituida por esta activación completada. El mapa sigue siendo ilustrativo y automático, sin almacenamiento de posiciones.

Implementación local en el proyecto Laravel existente. No se recibieron imágenes adjuntas; se reutilizan los recursos locales y la identidad de los módulos existentes. No se hicieron commit ni push.

## Pantallas y funcionamiento

- Panel principal: bienvenida, seis indicadores reales, servicio seleccionado, ocupación, mapa resumido, equipo, actividad y accesos rápidos.
- Servicios: solo jornadas aprobadas; portadas de Cocina, fecha, horario, capacidad, estado y cobertura de mesas por zona. Los servicios recurrentes usan el `id_servicio` de cada fecha; no se asigna a una programación completa.
- Zonas: tarjetas horizontales, fotografía, descripción, color, mesas, capacidad y personal de la jornada. Formularios con vista previa, edición y almacenamiento permanente cuando las columnas estén autorizadas.
- Mesas: tarjetas por zona y tabla; SVG con tantas sillas como capacidad, formulario dinámico, números consecutivos sin duplicados e historial conservado. La edición de mesas con referencias no permite cambiar su zona.
- Asignaciones: equipo con fotografías existentes del Gerente, distribución por zonas, indicadores, selección por jornada y zona, retiro y cambio mediante retiro/asignación. Las inserciones conservan el historial y bloquean al empleado para validar horarios incompatibles. No se crean cuentas.
- Comedor en vivo: mapa HTML/SVG con distribución automática, suelo, zonas, mesas interactivas y detalles; estado físico separado de ocupación, atención, comanda, comensales, inicio y tiempo de ocupación, personal e incidencias vinculadas. Actualización parcial cada 30 segundos conservando selección y filtros; aviso si falla la actualización.
- Incidencias: indicadores de toda la sede y últimas 100 incidencias, descripción, persona que registró, respuesta, estado y evidencia permanente. Se pueden vincular servicio, zona y mesa cuando estén las columnas. El Capitán no recibe nuevos permisos para resolver incidencias.

La barra lateral contiene exactamente siete enlaces. Se conserva el encabezado compartido con sede, perfil, selector multirrol y campana; el pie mantiene Sistema operativo / Conexión activa. CSS y JavaScript nuevos exclusivos del Capitán. La campana usa el componente existente, IDs estables y lectura en sesión para servicios próximos, zonas descubiertas, incidencias, mesas fuera de servicio y programación aprobada.

Los permisos se verifican por rol activo y sede en el backend. Se bloquean rutas ajenas a la operación del Capitán. Mesero, las consultas compartidas de mesas y la supervisión de servicios filtran las nuevas asignaciones por jornada; atención, cocina y caja conservan sus relaciones existentes.

## Límites reales del esquema

- Mesas: lógica existente de Disponible, Mantenimiento y Fuera de servicio; Ocupada se deriva de una atención abierta. No se añadieron limpieza ni reserva como estados nuevos. La paleta incluye tonos para estados existentes de servicios e incidencias.
- Incidencias: la base actual usa Pendiente, En proceso y Resuelta. Las nuevas incidencias se registran como Pendiente. No se inventan prioridad, tipo ni responsable de seguimiento: se muestra quién la registró.
- Usuarios/empleados no tienen un campo de baja o activo en el esquema actual; la pertenencia vigente al rol Mesero en la sede determina el personal disponible. Si se incorpora una baja laboral, hará falta conectar ese mecanismo con este filtro.
- Zonas no tienen estado activo/inactivo: el indicador dice Zonas registradas. No se añadió ese estado de negocio.
- Sin portadas o fotos de zona registradas se usa una imagen local decorativa. Empleados sin foto usan iniciales; no se asignan fotografías de terceros.
- No existe almacenamiento de posiciones: el mapa es ilustrativo y automático. Para personalizar posiciones se necesitaría una tabla de distribución por sede/zona/mesa con coordenadas normalizadas y versión, además de su endpoint autorizado. No se incluye una migración de posiciones innecesaria para esta primera distribución.
- No se añadió arrastrar y soltar; los formularios y botones son accesibles.

## Migración pendiente: requiere autorización

Archivo: `database/migrations/2026_11_16_000001_prepare_captain_workspace.php`.

1. `zonas`: `descripcion`, `imagen_url` y `color`.
2. `zona_meseros`: `id_servicio` nullable para identificar la jornada. Los registros generales anteriores quedan sin atribución a jornadas inventadas.
3. Sustituir el índice único heredado `uk_zona_mesero_activo`, que impide múltiples jornadas y retiros sucesivos, por un índice de consulta `captain_jornada_staff_index`. La validación concurrente bloquea al mesero antes de insertar. No se elimina ninguna fila.
4. `incidencias`: relaciones opcionales `id_servicio`, `id_zona` e `id_mesa`; añadir `evidencia_url` solo si falta (ya existe en la base inspeccionada).

Se preparó una única migración aditiva y de índices. No se ejecutó. Las asignaciones nuevas por jornada permanecen bloqueadas hasta su autorización; las asignaciones generales anteriores siguen disponibles en consulta. Las fotos/color/descripción de zonas se habilitan al existir las columnas. La evidencia de incidencias ya funciona con el esquema actual.

Las cargas usan el disco `public` y el mecanismo existente de validación y guardado: JPG/JPEG, PNG, WEBP, máximo 4 MB, nombres nuevos por carga, limpieza únicamente del archivo nuevo si falla la escritura de base de datos y conservación de archivos anteriores al reemplazar. Las fotografías de empleados reutilizan `EmployeePresentation` y un endpoint del Capitán que verifica sede y rol del empleado.

## Respaldo verificado

- SQL privado: `storage/app/private/backups/captain-before-schema-20261116-121224.sql`.
- Manifiesto: mismo nombre con extensión adicional `.json`.
- Tamaño: 151620 bytes; 62 tablas base presentes.
- SHA-256: `2e5c340ef6871879c284986f910118df286abe0a20672743e572e58c6b768975`.
- Exportación `mysqldump --single-transaction --skip-lock-tables`, con rutinas, eventos y triggers. Verificados código de salida, marca de exportación completa, presencia de todas las tablas y hash. No se restauró en la base real ni se afirma haber probado una restauración.

Si la autorización llega después de nuevas operaciones, se debe generar y verificar un respaldo fresco inmediatamente antes de ejecutar únicamente esta migración. No ejecutar todas las migraciones pendientes indiscriminadamente.

## Comprobaciones

12 pruebas funcionales seleccionadas, 168 aserciones, correctas en SQLite `:memory:`: las siete pantallas y navegación, aislamiento por sede, permisos, consecutivos duplicados sin inserción parcial, asignación por jornada, horarios incompatibles, persistencia y reemplazo de fotos, evidencia, recuperación de foto de Gestión de usuarios, aislamiento de fotos por sede, permisos del Mesero por jornada, flujo original atención → caja → cierre y observaciones de mesa.

Sintaxis PHP/JS y compilación Blade comprobadas. Sin instalación ni cambios de dependencias. No se realizó revisión visual en navegador; no se dispone de las referencias adjuntas. Una ejecución más amplia detectó una expectativa antigua del test `test_shared_views_do_not_apply_operational_theme_to_other_roles`: espera 200 en Incidencias del Jefe de cocina, mientras el middleware existente redirige a `chef.incidents`. No se modificó ese comportamiento ajeno al Capitán.
