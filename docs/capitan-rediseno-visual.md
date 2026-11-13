# Capitán: acabado visual y revisión

## Resultado

Tema exclusivo del Capitán de meseros, identificado por la clase `ops-captain`. Se reutilizaron los componentes y parciales de operación anteriores, sin repetir el script masivo.

- Panel del capitán: banner con fotografía local, seis indicadores con fondos claros sin fotografías, programación con una imagen distinta para desayuno, comida y cena, ocupación, gráfica existente, seguimiento reciente y accesos rápidos.
- Servicio en vivo: tablero gráfico de todas las mesas consultadas, comensales, atención, mesero y comanda; filtros nuevos sincronizados con los controles existentes. Estado físico y ocupación permanecen separados. La ocupación por zona del 100% recibe señalización roja, conservando la cifra real y su significado.
- Mesas y zonas: cuadrícula gráfica prioritaria con número, zona, capacidad y estado físico, leyenda textual, filtros sincronizados y detalles. Los botones de edición reutilizan los modales existentes. Las figuras de mesas son símbolos decorativos, no un plano físico ni una distribución inventada.
- Asignaciones: tarjetas por zona, identificación de meseros mediante iniciales reales y fechas; el formulario y las acciones originales de asignar/retirar se mantienen.
- Atenciones y comidas: tarjetas de seguimiento, iconos y etiquetas con estado e identificadores; tablas completas y filtros existentes conservados.
- Grupos: tarjetas con servicio, horario y comensales previstos. Se conservan exclusivamente los datos entregados por el controlador; no se inventan entregas que la consulta actual no proporciona.
- Incidencias: contadores por estado, tarjetas con descripción, fecha y respuesta; formulario e historial originales.
- Reportes: indicadores, barras y resúmenes existentes con jerarquía visual consistente.
- Panel principal y selección de sede compartidos: acabado fotográfico y señalización aplicados mediante CSS exclusivo del Capitán, sin cambiar las vistas de otros roles.

Verde para disponible/completado, ámbar para pendientes y ocupación habitual, rojo para mantenimiento/alertas y gris para estados neutrales. Se mantienen las etiquetas de texto, los números y los iconos para no depender del color.

## Recursos reales y locales

Se revisaron `public/`, `resources/` y los recursos de almacenamiento. Solo existía el logotipo como imagen estática; se reutilizaron los videos locales del proyecto para obtener dos imágenes reales en WebP:

- `public/img/ui/captain-dining.webp`, 1280 × 720, del video `comedor-industrial.mp4`.
- `public/img/ui/captain-kitchen.webp`, 1600 × 900, del video `login-cocina.mp4`.

Ambos fotogramas corresponden al segundo 2 de los videos. En la corrección posterior se incorporaron tres fotografías reales descargadas y almacenadas localmente: `service-breakfast.jpg`, `service-lunch.jpg` y `service-dinner.jpg`. La procedencia y las licencias de Unsplash/Pexels están descritas en `public/img/ui/README.md`. Ninguna pantalla depende de servidores de imágenes externos.

## Corrección puntual de imágenes

- Estadísticas sin fotografías repetidas: fondos claros, degradados suaves, iconos y números.
- Programación con imágenes distintas por tipo de servicio y rotación visual para nombres genéricos. El componente `resources/views/components/capitan/service-photo.blade.php` selecciona únicamente la imagen decorativa, sin cambiar datos de servicios.
- Tarjetas con imagen superior de proporción uniforme y encuadre específico; información inferior con fecha, horario, capacidad y estado originales. La cuadrícula evita estirar una tarjeta aislada a todo el ancho.
- Selección de sede compacta: fotografía exclusivamente en el encabezado, cuerpo blanco, nombre, ubicación, capacidad y botón alineado. Se corrigió solo el CSS del capitán; la vista compartida y su formulario no se modificaron.
- Archivos visuales de esta corrección: `public/css/captain-visual.css`, `resources/views/capitan/partials/overview.blade.php`, el componente de fotografía y los tres JPEG locales. También se actualizaron los créditos y las comprobaciones.

## Archivos de presentación modificados

1. `resources/views/layouts/app.blade.php`: clase y assets condicionales exclusivos del Capitán.
2. `resources/views/capitan/index.blade.php`: inclusión del tablero y atributos de presentación.
3. `resources/views/capitan/mesas/index.blade.php`: señalización del estado físico.
4. `resources/views/capitan/partials/overview.blade.php`: acabado de indicadores y servicios.
5. `resources/views/capitan/partials/context.blade.php`: tarjetas y señalización de módulos.
6. `resources/views/capitan/partials/table-grid.blade.php`: representación gráfica y controles visuales.

## Archivos nuevos

- `resources/views/capitan/partials/live-floor.blade.php`
- `resources/views/components/capitan/table-symbol.blade.php`
- `public/css/captain-visual.css`
- `public/js/captain-visual.js`
- Las dos imágenes y su README en `public/img/ui/`.
- Este informe.

Se ampliaron `tests/Feature/OperationsPresentationTest.php` y `tests/Browser/operations-smoke.mjs` para comprobar assets, exclusividad por rol y los filtros nuevos.

## Verificaciones

- Compilación Blade correcta.
- 8 pruebas y 183 aserciones correctas en SQLite `:memory:`, sin migraciones ni conexión a MySQL. Incluyen rutas, imágenes locales válidas, archivos distintos para los tres tipos de servicio, datos de programación conservados y exclusividad de sus assets frente a Mesero, Cajero y Cocina.
- Comparación con las copias anteriores al rediseño: 21 formularios, 43 contratos de campos y 5 scripts inline originales conservados; los 32 controladores, las rutas y `resources/js/app.js` permanecen idénticos.
- Última revisión Chrome: 13 páginas del Capitán a 1440, 768 y 390 px, incluida programación con desayuno/comida/cena. Imágenes cargadas, estadísticas sin fotografías, tarjeta de sede compacta, sin excepciones JavaScript ni desbordamientos. Filtros y modales existentes conservados. Capturas adicionales de programación y selección de sede en escritorio y móvil.
- Capturas y resultados en `storage/app/private/ui-preview/`. Son páginas renderizadas por las rutas reales con datos de prueba aislados, sin operaciones sobre información de producción.

No se modificaron controladores, modelos, consultas, permisos, rutas, migraciones o datos. No se ejecutaron comandos Git ni el script masivo anterior. El JavaScript nuevo únicamente sincroniza presentación y filtros locales; no realiza solicitudes ni cambia estados de negocio.

La aceptación final con las cuentas reales y los dispositivos habituales corresponde a la revisión del usuario. Los límites de las consultas existentes y los dos fallos previos de pruebas de Recepción/Almacén descritos en `rediseno-operacion.md` permanecen fuera de este cambio.
