# Rediseño visual del Mesero

## Pantallas terminadas

- **Panel principal:** banner local de cocina/comedor, indicadores originales sin fotografías, accesos rápidos, programación en tarjetas con fotos distintas, actividad reciente, ocupación y gráficas existentes.
- **Servicios:** tarjetas fotográficas con fecha, horario, capacidad, estado y atenciones; filtros, historial y paginación originales conservados. En curso/finalizado se representan en verde, programado en azul y cancelado en rojo.
- **Mi operación:** encabezado compacto, indicadores con iconos, mesas gráficas con número, zona, capacidad, estado físico y ocupación separados. Seleccionar una mesa conserva el enfoque del formulario original. Creación desplegable, registro/edición de servicios ofrecidos, finalización y envío a Caja se conservan completos. Se añade seguimiento visual de hasta cuatro atenciones y se mantiene el historial completo recibido.
- **Mesas y zonas:** zonas asignadas destacadas y antepuestas visualmente, meseros asignados, cantidad de mesas y capacidad, figuras de mesas con estado físico y listado original desplegable.
- **Incidencias:** indicadores originales de sede, resumen por estados de la página actual con alcance explícito, tarjetas con descripción/usuario/fecha/respuesta/cierre, formulario, filtros e historial originales.
- **Selección de sede:** tarjeta compacta, fotografía exclusivamente en el encabezado, nombre, ubicación, capacidad, identificación de sede activa y botón original alineado.

## Aislamiento

`public/css/waiter-visual.css` solo se carga para Mesero, mediante la clase `ops-waiter`, sin aplicarse a Capitán ni Cajero. No se modificaron sus archivos de vistas ni sus estilos. Los cambios en layout, dashboard e incidencias compartidos están condicionados al Mesero. El JavaScript operativo original no se modificó ni se añadió JavaScript de negocio.

Se reutilizaron, sin modificar sus fuentes, los componentes existentes `ops.metric`, `ops.status`, `ops.empty`, `ops.filter`, `capitan.table-symbol` y `capitan.service-photo`.

## Imágenes

Se reutilizaron exclusivamente los cinco recursos ya presentes en `public/img/ui/`:

- `captain-dining.webp`: banner principal y encabezado de sede.
- `captain-kitchen.webp`: ambiente de servicio.
- `service-breakfast.jpg`, `service-lunch.jpg`, `service-dinner.jpg`: programación según nombre del servicio, con alternancia para nombres genéricos.

No hubo descargas, dependencias nuevas ni fotografías detrás de estadísticas. Se mantienen proporciones, alturas uniformes y encuadres específicos para desayuno y cena. Los créditos existentes siguen en `public/img/ui/README.md`.

## Archivos de presentación

- Nuevo: `public/css/waiter-visual.css`.
- `resources/views/mesero/index.blade.php`
- `resources/views/mesero/services.blade.php`
- `resources/views/mesero/tables.blade.php`
- `resources/views/mesero/partials/assigned-tables.blade.php`
- `resources/views/mesero/partials/service-cards.blade.php`
- Nuevos: `resources/views/mesero/partials/attention-summary.blade.php`, `dashboard-services.blade.php`, `incident-summary.blade.php`, `incident-cards.blade.php`.
- Integración condicional: `resources/views/layouts/app.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/modules/incidents.blade.php`.

Se ampliaron las comprobaciones en `tests/Feature/OperationsPresentationTest.php`, `tests/Browser/operations-smoke.mjs` y `tests/Browser/operations-contracts.mjs`.

## Verificaciones

- Blade compila correctamente.
- 9 pruebas y 213 aserciones aprobadas, incluyendo rutas del Mesero, formularios, filtros, tres fotografías distintas, aislamiento de los otros roles y flujo original de atención → servicio ofrecido → Caja → pago → cierre.
- Pruebas exclusivamente con SQLite `:memory:` y comprobación de conexión previa; sin migraciones ni acceso a MySQL.
- Chrome: siete páginas de prueba (las seis opciones del menú y una consulta con desayuno/comida/cena) a 1440, 768 y 390 px. Imágenes cargadas; indicadores sin fotografías; sede compacta; sin excepciones JavaScript ni desbordamientos de página. Navegación móvil, selección de mesa y filtros originales comprobados.
- Conservación comprobada de 21 formularios, 43 contratos de campos y cinco scripts inline originales mediante la comparación disponible desde el inicio del rediseño.
- 94 archivos protegidos de vistas de Capitán/Caja, modelos, controladores, rutas y migraciones siguen idénticos a sus hashes al comenzar esta tarea. `resources/js/app.js` permanece intacto.
- Capturas y resultados locales en `storage/app/private/ui-preview/`; utilizan datos aislados de prueba y no registros de producción.

## Alcance de datos conservado

El controlador actual de **Mesas y zonas** entrega el directorio de toda la sede. Se conserva ese alcance autorizado y se destacan primero las zonas asignadas; no se ocultan registros ni se cambian permisos desde Blade. **Mi operación** continúa recibiendo únicamente las mesas de las zonas asignadas según su controlador original.

El dashboard compartido no recibe una consulta de mesas/atenciones personales; por eso esos indicadores se muestran en Mi operación y no se inventan en el inicio. Los conteos por estado de incidencias se refieren expresamente a la página actual; los cuatro indicadores globales originales permanecen disponibles.

No se realizaron cobros ni registros de prueba sobre la base real, ni se ejecutaron migraciones, seeders o comandos Git. Queda la aceptación con las sesiones y dispositivos habituales del usuario.
