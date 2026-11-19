# Espacio de Jefe de cocina — NUTRILIADO

## Alcance y navegación

Ocho vistas exclusivas del rol activo Jefe de cocina, con sede actual validada tanto en rutas como en escrituras. El panel vive en `/dashboard`; el resto en `/jefe-cocina`. Las entradas antiguas de servicios, menús, platillos, cocina, inventario e incidencias conducen a las vistas nuevas cuando este rol está activo. Otros roles conservan sus controladores, vistas y hojas de estilos. El jefe no accede a empleados, proveedores, caja, reportes administrativos, gestión de página pública ni escrituras de inventario.

La identidad se reutilizó del espacio de almacén, con selectores aislados bajo `.chef-workspace` y una hoja independiente `public/css/chef-workspace.css`. Ninguna vista de almacén, recepción o gerente fue rediseñada. Se utilizan las fotografías existentes de cocina industrial, desayuno, comida y cena. No se recibieron imágenes de referencia adjuntas en el mensaje.

## Vistas implementadas

| Vista | Funcionalidad |
| --- | --- |
| Panel principal | Seis indicadores reales, próximos servicios, barras semanales, distribución circular, menú publicado del día, comandas, alertas de insumos, incidencias recientes y accesos rápidos. |
| Gestión de servicios | Tarjetas, filtros por nombre/fecha/operación/aprobación, portada por tipo de servicio, alta y edición con vista previa, observaciones de rechazo y acciones existentes de inicio/finalización. |
| Catálogo de platillos | Tarjetas fotográficas, categorías existentes, buscador, detalle con receta y usos, alta/edición y carga real de fotografía. Las recetas existentes no se eliminan al editar la ficha. |
| Menú por servicio | Catálogo y selección en dos columnas, varios platillos por categoría, búsqueda local, precio por servicio, disponibilidad, publicación, portada independiente y preparación del especial del chef. Reutiliza el guardado existente sin duplicar platillos ni eliminar el historial de menús. |
| Generador de menús | Periodos diario/semanal/quincenal/mensual/personalizado, fechas, servicio/tipo, días incluidos, tres plantillas, título/comedor/color/fotos/precios, vista administrativa y vista de selección publicable. Fechas sin menú se muestran sin inventar platillos. |
| Comandas | Tablero de partidas reales enlazadas a Mesero, con servicio, mesa, fotos, cantidades, observaciones y hora. Columnas `pendiente`, `preparando`, `listo`, `entregado`; otros estados registrados se conservan visibles. Personal de cocina mantiene las transiciones y entregas. |
| Consulta de insumos | Existencias/unidades/mínimos/caducidades reales por sede, filtros e indicadores. Sin botones ni permisos para ajustar stock o crear pedidos. |
| Incidencias | Consulta de cocina sin incidencias ligadas a recepción, búsqueda/estados, alta con responsable real y estado Pendiente. Soporte preparado para servicio y evidencia; el autor puede reemplazar su propia evidencia en una incidencia abierta, sin cambiar su estado o respuesta. |

La campana existente consulta un feed exclusivo: aprobaciones y rechazos, servicios próximos, comandas pendientes, incidencias y stock bajo/agotado. El marcado como leído se separa por usuario, rol y sede en la sesión.

## Aprobación y publicación

Los servicios nuevos se guardan con `estado=Programado` y `estado_aprobacion=Pendiente`, ignorando intentos de aprobar desde el formulario. Solo las rutas existentes del gerente aprueban o rechazan. La operación de servicios conserva sus transiciones originales. La edición de servicios pendientes/rechazados mantiene la restricción existente cuando ya tienen menús, atenciones o grupos.

El menú puede guardarse como borrador de un servicio pendiente. `MenuController::store` sigue resolviendo la publicación bajo bloqueo del servicio; un servicio sin aprobar no publica aunque se envíe `publicado=1`. Si se habilita disponibilidad global del catálogo, un platillo desactivado no se publica desde el configurador nuevo ni se incluye en la vista para compartir del generador. Los precios se muestran en MXN.

## Soporte real y cambios de base pendientes

Se consultaron únicamente metadatos del esquema existente y las categorías actuales. No se ejecutaron migraciones ni se modificaron registros reales durante este desarrollo. La tabla `platillos` ya tiene `imagen_url`: la fotografía del platillo está operativa sin cambios de esquema. Las categorías actuales son Desayunos, Comidas y Bebidas; se reutilizan y se ofrecen sugerencias de categorías sin insertar datos automáticamente.

Las siguientes migraciones independientes se prepararon en el plan inicial. Las de imágenes `000003`, `000006` y `000007` ya están aplicadas con autorización explícita, según el registro de activación al final. Las demás continúan pendientes:

| Archivo en `database/migrations` | Cambio aditivo |
| --- | --- |
| `2026_11_15_000003_add_chef_service_media.php` | `servicios.cover_url` y `servicios.menu_cover_url`, nullable. |
| `2026_11_15_000004_add_chef_dish_metadata.php` | `platillos.precio_referencia` y `platillos.disponible`, nullable; los datos históricos no se declaran disponibles automáticamente. |
| `2026_11_15_000005_add_menu_chef_special.php` | `menus.especial_chef`, nullable. |
| `2026_11_15_000006_add_kitchen_incident_media.php` | `incidencias.evidencia_url`, nullable. |
| `2026_11_15_000007_create_kitchen_menu_designs.php` | Nueva tabla de configuraciones y rutas de imágenes por usuario y sede, con unicidad de esa pareja. |
| `2026_11_15_000008_add_chef_optional_service_metadata.php` | Opcional y ajena al almacenamiento de imágenes: `servicios.descripcion` e `incidencias.id_servicio`, nullable. Separada de las migraciones de medios antes de solicitar su ejecución. |

Requieren autorización específica y un respaldo actualizado de `ar_menu_equipo` fuera del repositorio, verificado antes de cualquier ejecución. No se debe ejecutar un `migrate` general. Sus reversiones automáticas se bloquean para conservar imágenes, metadatos e historial. Las autorizaciones anteriores para proveedores no aplican a estas migraciones.

Hasta que se habiliten los campos, las cargas correspondientes se identifican expresamente como vista previa local y los archivos no se envían; el servidor rechaza intentos de cargar imágenes sin soporte. No se ocultan esas limitaciones ni se simula persistencia. La disponibilidad global y el precio de referencia se muestran solo cuando existen sus columnas; antes se usan los valores reales del menú por servicio. La persistencia de configuraciones/fondos del generador se activa al disponer de su tabla.

## Almacenamiento y exportación

Las cargas habilitadas usan el mismo disco público de Laravel que proveedores, bajo `cocina/<campo>/`, con validación de archivo real JPG/JPEG/PNG/WEBP, dimensiones mínimas y máximo de 4 MB. Los campos son independientes: sin archivo nuevo no se sobrescribe la ruta actual. Las operaciones fallidas eliminan solo los archivos nuevos de ese intento, sin borrar fotos históricas. Las URLs locales respetan `/restaurante_industrial/public` mediante `asset`, evitando el fallo de `APP_URL` sin subdirectorio.

Imprimir y Guardar PDF usan la impresión nativa del navegador, con hoja de impresión del menú y sin navegación o controles administrativos. No se instaló ninguna dependencia. No hay una descarga PDF binaria del servidor; para un motor PDF dedicado habría que acordar la dependencia antes de instalarla.

La página pública existente (`PublicSiteController` y el editor del gerente) publica contenido administrado por el gerente y no tiene una integración autorizada de menús de cocina. No se añadió un botón de publicación que pueda dar una falsa confirmación ni se concedió acceso al editor. La vista para compartir del generador filtra los datos, pero no publica contenido externo por sí misma.

## Verificación

Pruebas nuevas en SQLite `:memory:` y disco público falso: las ocho vistas y formularios, separación por sede y rol activo, denegación de inventario/caja/proveedores, aprobación y publicación, fotos independientes y conservación al cerrar sesión, protección de recetas, tres plantillas, fechas, disponibilidad y persistencia de diseño. No usan migraciones reales ni datos de producción. No se hizo commit ni push.

Resultado final: cinco pruebas específicas de cocina y una comprobación de las siete vistas de almacén, todas aprobadas; 257 verificaciones en total. No se ejecutó la suite general ni se repitieron inspecciones visuales.

## Captura circular de horarios

Crear y editar servicio usan el componente compartido `time-field.blade.php` y un único diálogo circular `time-dialog.blade.php`. Los estilos y la interacción están centralizados en `chef-workspace.css` y `chef-workspace.js`, sin librerías adicionales. El flujo permite elegir hora, minutos exactos y a. m./p. m., cancelar sin cambiar valores, usar teclado y arrastrar la aguja desde el reloj. El foco regresa al campo al cerrar; Escape cancela. Sin soporte de diálogo o sin JavaScript permanece la captura nativa como alternativa.

Los campos conservan sus nombres `inicio_hora` y `fin_hora` y envían `HH:mm`. Solo su presentación y la vista previa del formulario usan 12 horas, como `01:45 p. m.`. Se valida la selección obligatoria y el final estrictamente posterior al inicio con mensajes junto al campo, preservando la regla existente del servidor sin modificar controladores ni datos.

Verificación específica: una prueba con SQLite en memoria (19 comprobaciones) y una pasada de interacción con Chrome sin interfaz visible usando HTML de esa misma sede ficticia. Se comprobaron tamaño móvil, formato, flujo circular, minutos exactos por teclado, cancelación, campos enviados, errores de rango, mediodía y medianoche. No se ejecutaron migraciones ni pruebas sobre datos reales.

## Habilitación pendiente del almacenamiento de cocina

Una revisión posterior de solo lectura confirmó en `ar_menu_equipo` que existen `platillos.imagen_url`, el disco público escribible y el enlace `public/storage`, pero siguen ausentes `servicios.cover_url`, `servicios.menu_cover_url`, `incidencias.evidencia_url` y la tabla `kitchen_menu_designs`. No había archivos bajo `cocina/`. El formulario omite el nombre de los archivos sin soporte y advierte que son vista previa; por eso una selección local no reemplaza la portada guardada.

Se revisó el flujo existente: formularios multipart, validación de archivos reales hasta 4 MB, almacenamiento en disco público, persistencia de rutas en transacciones, conservación al omitir un archivo, sustitución independiente, limpieza de nuevos archivos cuando falla la transacción y URLs que respetan el subdirectorio. Las vistas consultan los mismos campos y los avisos se retiran automáticamente cuando el esquema está habilitado. No hace falta cambiar estilos, colores, reloj circular o permisos.

Se requiere autorizar **únicamente** las migraciones `000003`, `000006` y `000007` indicadas arriba. Para limitar su alcance a imágenes se separaron `descripcion` e `id_servicio` en `000008`, que no se propone ejecutar. Tampoco se requieren `000004` (precio/disponibilidad) ni `000005` (especial del chef). La foto individual del platillo no necesita migración.

Respaldo actualizado fuera del repositorio: `C:\Users\johan\Backups\NUTRILIADO\ar_menu_equipo_chef_media_20261115_210936.sql`, 150 192 bytes y 61 tablas, marca de finalización y presencia de las tablas de cocina verificadas. SHA-256: `4e926933f4a3db7a3b92debbe64e723a4599e427738b0dcd54a0e0dfeb5f44fb`; archivo `.sha256` adjunto. No se ejecutó ninguna migración ni se modificaron registros reales. Las pruebas previamente exitosas no se repitieron.

Tras recibir la autorización, ejecutar cada archivo requerido por su `--path`, verificar la base activa y comparar los registros y rutas preexistentes antes y después. No ejecutar un `migrate` general. Solo después de aplicar el esquema se podrá confirmar la habilitación en la instalación real y retirar efectivamente los avisos mediante la detección existente.

## Revisión integral de las seis cargas y paquete único pendiente

Se volvió a consultar `ar_menu_equipo` sin alterar datos. Continúan faltando las dos columnas de portadas en servicios, la columna de evidencia en incidencias y la tabla de diseños. La foto de platillo conserva su columna existente. Se comprobó una escritura y lectura temporal a través del disco público y `public/storage`; el archivo temporal propio se retiró. No se eliminaron imágenes existentes.

El respaldo anterior `ar_menu_equipo_chef_media_20261115_210936.sql` conserva sus 150 192 bytes y coincide con su SHA-256. Para cubrir los datos actuales se generó y verificó además `C:\Users\johan\Backups\NUTRILIADO\ar_menu_equipo_chef_all_images_20261115_214219.sql`, 150 252 bytes, 61 tablas y marca de finalización. SHA-256: `79a01315ab672eebc478fd60ea433659cdf31d686bbccac6e5bc7a889537d593`, conservado también en el archivo `.sha256` adjunto.

Se añadió una única comprobación integral, con SQLite de archivo y almacenamiento local físico en un directorio de pruebas independiente, sin el disco público falso. No ejecuta los archivos de migración pendientes. Comprueba las seis rutas (servicio, platillo, menú, logo del generador, fondo del generador y evidencia), reemplazo de cada imagen conservando los archivos anteriores, ausencia de sobrescritura cuando no llega un archivo nuevo, configuración del diseño, cierre de sesión, nueva conexión PDO a la misma base aislada, reconstrucción del adaptador de almacenamiento y recuperación en las vistas sin avisos de previsualización. Pasó 87 comprobaciones; no se repitió la suite general.

La prueba confirma que el mecanismo de guardado existente funciona con el esquema completo. La instalación real **sigue pendiente de habilitación**, por lo que no se declara resuelta y no se retiran sus advertencias todavía. La autorización solicitada abarca conjuntamente y exclusivamente estos tres archivos:

```sh
php artisan migrate --path=database/migrations/2026_11_15_000003_add_chef_service_media.php --path=database/migrations/2026_11_15_000006_add_kitchen_incident_media.php --path=database/migrations/2026_11_15_000007_create_kitchen_menu_designs.php
```

No incluye precio/disponibilidad, especial del chef ni metadatos opcionales. Las migraciones evitan duplicar columnas o tabla si ya existen. Tras autorizar se debe volver a verificar la base activa, aplicar únicamente ese paquete y comprobar los registros, imágenes existentes, URLs públicas, configuración y eliminación efectiva de los avisos. No se cambian diseños, colores, reloj, permisos ni reglas de aprobación.

## Activación autorizada de las seis cargas en la instalación real

Se recibió autorización expresa para las migraciones `000003`, `000006` y `000007`. Antes de ejecutarlas se confirmó `SELECT DATABASE() = ar_menu_equipo`, se revisó su carácter aditivo (rutas nullable y nueva tabla sin cambios de datos) y se volvió a comprobar el SHA-256 del respaldo `ar_menu_equipo_chef_all_images_20261115_214219.sql`. Se registraron huellas de los datos preexistentes y de los 41 archivos públicos existentes para comparar después.

Se ejecutó una sola intervención con el comando de tres `--path` indicado arriba. La tabla de migraciones confirma exclusivamente esos tres nuevos registros; no se ejecutaron `000004`, `000005`, `000008` ni ninguna otra migración. La base pasó de 61 a 62 tablas por la nueva `kitchen_menu_designs`.

La verificación indispensable de la instalación real confirmó:

- `servicios.cover_url` y `servicios.menu_cover_url` existen y permiten NULL.
- `platillos.imagen_url` permanece intacto y permite NULL.
- `incidencias.evidencia_url` existe y permite NULL.
- `kitchen_menu_designs.logo_url` y `background_url` existen y permiten NULL; la tabla admite la configuración del diseño mediante `settings`.
- Los seis campos de archivo se renderizan con su nombre de envío y sin aviso de vista previa pendiente.
- Se escribió y recuperó una imagen temporal independiente en cada directorio de carga mediante Laravel, con URLs de la instalación real que respondieron HTTP 200 y entregaron los mismos bytes. Solo se retiraron esas seis imágenes temporales propias.
- Los registros de las 60 tablas de negocio preexistentes y los 41 archivos públicos preexistentes conservan sus huellas. No se insertaron datos ni imágenes de prueba en registros reales.

Se distinguió además el bloqueo de edición por estado del servicio de la disponibilidad del almacenamiento: un menú finalizado/cancelado mantiene su campo deshabilitado sin afirmar que falta esquema. No se alteraron permisos, aprobación, diseño, colores ni reloj circular.

Las 87 comprobaciones aisladas previas no se repitieron. La persistencia de las seis cargas queda habilitada; las selecciones locales hechas antes de habilitar los campos deben seleccionarse y guardarse de nuevo. La configuración, logotipo y fondo del generador se guardan en la nueva tabla por usuario y sede. No se hizo commit ni push.
