# Identidad visual de proveedores de almacén

El directorio, sus formularios y el detalle usan el mismo componente de imagen. Los logos mantienen su proporción con `object-fit: contain`; las iniciales del nombre sirven como alternativa cuando falta la imagen o no carga. La hoja de estilos y el script de previsualización se incluyen únicamente en Proveedores del Jefe de almacén.

La carga acepta JPG, JPEG, PNG y WEBP de hasta 4 MB, con validación del contenido y dimensiones en el servidor. Se guarda en el disco público bajo `proveedores/`. La previsualización local permite volver a la imagen actual; editar sin elegir archivo conserva su ruta. Si falla el guardado del proveedor, se elimina el nuevo archivo. Los archivos anteriores no se borran automáticamente, para conservar referencias compartidas.

## Compatibilidad del esquema

El código revisado no define un campo de imagen de proveedores. No se consultó ni modificó la base real para esta tarea. La aplicación detecta en tiempo de ejecución `proveedores.logo_url` y, como alternativa, `proveedores.imagen_url`; reutiliza cualquiera de ellos sin cambiar el esquema.

Si ninguno existe, los formularios permiten seleccionar una imagen solo como vista previa local e indican expresamente que no se guardará. El archivo no se envía y el registro o edición de los datos habituales continúa disponible. El servidor también rechaza archivos enviados directamente cuando no existe soporte.

Para activar la persistencia en una instalación sin soporte se necesita añadir en una intervención posterior autorizada un campo nullable de texto, preferentemente `proveedores.logo_url`, y verificar que el disco público es escribible y está publicado mediante `public/storage`. No se ejecutaron migraciones ni se creó ese enlace en esta tarea. No hace falta añadir un modelo: este módulo usa el constructor de consultas de Laravel.

Las pruebas usan exclusivamente SQLite en memoria y almacenamiento falso. Cubren ausencia de columna, carga, visualización, conservación, reemplazo, limpieza tras duplicados y rechazo de archivos inválidos. No cambian la base real ni ejecutan migraciones.

## Corrección de persistencia — 15 de noviembre de 2026

Se verificó el esquema real mediante una consulta de metadatos de solo lectura: `proveedores` tiene únicamente `id_proveedor`, `nombre`, `telefono` y `correo`. La falta de la columna de ruta es la causa de que la selección se limite a la vista previa. El directorio público tiene permisos de escritura y `public/storage` apunta correctamente a `storage/app/public`. Los límites de carga de PHP CLI son de 40 MB; la aplicación mantiene su máximo de 4 MB.

Se preparó la migración independiente `database/migrations/2026_11_15_000001_add_logo_url_to_proveedores.php`, pendiente de autorización explícita antes de ejecutarla. Solo añade `logo_url` nullable; no modifica los registros existentes y reutiliza un campo compatible si ya existe. Su reversión automática se bloquea para no eliminar rutas guardadas.

Una vez autorizada, ejecutar exclusivamente:

```sh
php artisan migrate --path=database/migrations/2026_11_15_000001_add_logo_url_to_proveedores.php
```

El formulario detectará el campo automáticamente, enviará el archivo y dejará de mostrar la advertencia de vista previa. No hace falta ampliar permisos: las rutas y el controlador ya exigen Jefe de almacén activo en su sede; puede editar la identidad compartida de un proveedor aunque sus pedidos pertenezcan a otra sede. Sus relaciones operativas siguen filtradas por sede.

Se verificaron con SQLite en memoria y almacenamiento falso el registro, reemplazo, conservación sin archivo nuevo, persistencia tras cerrar sesión e ingresar de nuevo, rechazo de archivos inválidos o mayores de 4 MB y bloqueo de edición bajo otro rol activo. La migración preparada no se ejecutó en la base real.

## Activación autorizada — 15 de noviembre de 2026

Después de la autorización explícita se confirmó `SELECT DATABASE() = ar_menu_equipo`. Se generó y comprobó el respaldo externo `C:\Users\johan\Backups\NUTRILIADO\ar_menu_equipo_20261115_165853.sql`: 149 602 bytes, 61 tablas y marca de finalización del volcado. Su SHA-256 es `ba1d9c88bc46d9e97be8ee3eff490d53c4afb5526d80adb158cca319e174f774`, conservado también en el archivo `.sha256` adjunto. Se utilizó el cliente MariaDB compatible con el servidor.

Se ejecutó exclusivamente el comando de migración indicado arriba. La comprobación posterior confirmó `logo_url varchar(2048) NULL`, las mismas 61 tablas y los mismos cuatro proveedores. Una comprobación temporal de escritura y lectura con el disco público de Laravel y el enlace `public/storage` terminó correctamente; su archivo temporal se eliminó.

La aplicación detecta ahora `logo_url`: el formulario envía la imagen y oculta automáticamente el aviso de vista previa, sin cambios adicionales de diseño, permisos o sesiones. Las pruebas previas no se repitieron. No se ejecutaron otras migraciones, no se eliminaron registros y no se hizo commit ni push.

## Corrección de URL en instalación bajo subdirectorio

El diagnóstico de GARIS confirmó que el archivo había llegado al controlador: `logo_url` contenía su ruta, el archivo existía en el disco público y era accesible mediante el enlace `public/storage`. El fallo era de presentación: la URL del disco, basada en `APP_URL=http://localhost`, omitía `/restaurante_industrial/public`. La URL antigua respondió HTTP 404 y la misma imagen con el prefijo de instalación respondió HTTP 200 `image/jpeg` (20 463 bytes).

El resolvedor compartido por las tarjetas, el detalle y el formulario ahora usa `asset('storage/...')` para el disco local, respetando la base de la petición web. Los discos remotos mantienen su propio generador de URL. No se cambió `APP_URL` para evitar afectar otros roles. El registro de GARIS no se modificó: su imagen ya almacenada se recupera con la URL correcta.

## Logotipo y portada independientes

Las tarjetas del directorio de almacén muestran dos columnas en escritorio, logotipo a la izquierda sin recorte, nombre destacado y portada con degradado hacia blanco. Si falta portada se utiliza `public/img/ui/warehouse-logistics.jpg`. Los contadores de pedidos y recepciones tienen fondos azul claro y lavanda; los datos de contacto tienen iconos. La etiqueta muestra el estado disponible o indica que no hay estado registrado.

Los formularios agrupan las dos cargas opcionales en «Identidad visual del proveedor», con previsualización y restauración independientes. Cada archivo admite JPG/JPEG/PNG/WEBP hasta 4 MB. El logotipo sigue usando `logo_url`; la portada usa `cover_url`, en el mismo disco público y en `proveedores/portadas`. Se conservan ambas rutas si no se envían archivos nuevos. Si falla el guardado, se eliminan únicamente los archivos nuevos de ese intento; no se borran imágenes existentes.

La consulta de metadatos confirmó que `ar_menu_equipo.proveedores` aún no tiene `cover_url`. Se preparó, sin ejecutar, `database/migrations/2026_11_15_000002_add_cover_url_to_proveedores.php`: añade exclusivamente `cover_url varchar(2048) NULL`. Requiere una nueva autorización del usuario; la autorización anterior de `logo_url` no se extiende a esta migración. Hasta entonces la portada seleccionada solo se previsualiza y el formulario informa que su persistencia está pendiente. El logotipo sigue guardándose normalmente.

Una prueba específica con SQLite en memoria y almacenamiento falso pasó 35 comprobaciones: campos opcionales, esquema sin portada, alta conjunta, conservación y reemplazo independientes, validación y limpieza de ambas imágenes ante errores. No se modificaron proveedores reales ni se ejecutaron migraciones en la base real.

## Respaldo previo a habilitar portada y ajustes puntuales

Una nueva consulta de metadatos confirmó que `ar_menu_equipo.proveedores` todavía no tiene `cover_url`. La migración `2026_11_15_000002_add_cover_url_to_proveedores.php` se revisó: añade únicamente `cover_url varchar(2048) NULL`, sin modificar filas. Sigue pendiente de autorización; no se ejecutó.

Se generó un respaldo actualizado fuera del repositorio: `C:\Users\johan\Backups\NUTRILIADO\ar_menu_equipo_cover_20261115_171841.sql`, 149 889 bytes y 61 tablas, con marca de finalización verificada. SHA-256: `ede6ced32b8afa70ed88107318eeefe1bcef9cf6cddf708a11632047fbaf4e6b`, también guardado en el archivo `.sha256` adjunto. Había cinco proveedores; no se alteraron sus registros.

El botón «Ver detalles» conservaba la clase `secondary`, cuyo color Oxford tenía prioridad `!important` sobre el blanco normal del estilo de tarjeta. Ahora tiene una clase específica, texto blanco centrado en negrita e icono de ojo blanco. Los dos campos de imagen tienen una fila de vista previa de 100 px, títulos y ayudas de igual estructura y una nota compartida, evitando los espacios causados por filas automáticas estiradas. Los mensajes de selección válida siguen en verde. Estos cambios no afectan el diseño de las tarjetas ni los permisos compartidos.

La persistencia de la portada y la retirada de su aviso se activarán automáticamente al autorizar y ejecutar exclusivamente la migración pendiente. El mecanismo de almacenamiento y la recuperación del fondo de tarjeta ya están implementados y mantienen independientes las rutas del logo y de la portada.

## Activación de portada autorizada

Con autorización explícita se confirmó nuevamente la base activa `ar_menu_equipo` y se ejecutó únicamente:

```sh
php artisan migrate --path=database/migrations/2026_11_15_000002_add_cover_url_to_proveedores.php
```

La revisión posterior confirmó `cover_url varchar(2048) NULL` como única columna añadida. Se compararon las huellas de todos los datos preexistentes de los cinco proveedores y de los archivos de logotipo: permanecieron sin cambios. No se ejecutaron otras migraciones.

El formulario detecta ya `cover_url`, envía `portada` y oculta el aviso de previsualización. Se verificó escritura y lectura del directorio de portadas mediante Laravel y el enlace público, eliminando exclusivamente el archivo temporal de comprobación. Los componentes recuperan los logotipos existentes y la portada genérica con URLs HTTP 200; todavía no hay portadas personalizadas guardadas, por lo que requieren una nueva selección y envío del formulario. No se escribieron imágenes de prueba ni rutas en los registros reales.

No se repitió la suite de pruebas. No se eliminaron registros, no se hizo commit ni push.

Después de editar se redirige al directorio con el proveedor seleccionado para consultar su información actualizada. La selección válida se muestra en verde; los errores conservan el rojo. Las referencias de CSS y JavaScript incluyen su fecha de modificación para renovar la caché del navegador. Una prueba de regresión específica reproduce el despliegue en subdirectorio y verifica las tres presentaciones, el campo de archivo y el formulario multipart.
