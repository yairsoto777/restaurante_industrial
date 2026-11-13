# Rediseño de operación de NUTRILIADO

Fecha: 12 de noviembre de 2026.

## Alcance entregado

- Capitán: panel principal compartido, selección de sede, panel del capitán, servicio en vivo, mesas y zonas, asignaciones, atenciones, comidas, grupos, incidencias y reportes. Cuadrícula de mesas por zona, detalles y edición mediante los modales originales; programación, ocupación, seguimiento y reportes utilizan exclusivamente las colecciones existentes.
- Mesero: panel principal, sedes, servicios, Mi operación, mesas y zonas e incidencias. Tarjetas de mesas asignadas, selección que enfoca el formulario original de atención, creación de mesa desplegable, tarjetas de servicios y filtros del historial de servicios ofrecidos.
- Cajero: panel principal, sedes, Caja e incidencias; también historial de turnos y ticket. Indicadores financieros existentes, tarjetas de cuentas que abren el cobro original, directorio completo, filtros y resumen del historial. El ticket conserva la impresión y todos sus importes.

El tema utiliza azul marino y azul institucional, verde para operaciones completadas, ámbar para pendientes, rojo para alertas y diferencias, y gris para estados neutrales. El estado físico de una mesa se muestra por separado de su ocupación. No se representa una distribución física inventada del comedor.

Las vistas compartidas incorporan bloques nuevos únicamente para los roles solicitados. Los estilos y el JavaScript complementario se cargan solo para Capitán, Mesero y Cajero. Se conservaron los controladores, modelos, rutas, consultas, permisos y scripts originales. No se ejecutó el script masivo de edición durante esta continuación.

## Archivos originales modificados

1. `resources/views/layouts/app.blade.php`
2. `resources/views/dashboard.blade.php`
3. `resources/views/sites/select.blade.php`
4. `resources/views/capitan/index.blade.php`
5. `resources/views/capitan/mesas/index.blade.php`
6. `resources/views/mesero/index.blade.php`
7. `resources/views/mesero/services.blade.php`
8. `resources/views/mesero/tables.blade.php`
9. `resources/views/modules/incidents.blade.php`
10. `resources/views/cash/index.blade.php`
11. `resources/views/cash/history.blade.php`
12. `resources/views/cash/ticket.blade.php`

## Archivos nuevos creados o completados

- `public/css/operations.css`
- `public/js/operations.js`
- `resources/views/components/ops/status.blade.php`
- `resources/views/components/ops/metric.blade.php`
- `resources/views/components/ops/filter.blade.php`
- `resources/views/components/ops/empty.blade.php`
- `resources/views/components/ops/dashboard-actions.blade.php`
- `resources/views/capitan/partials/overview.blade.php`
- `resources/views/capitan/partials/context.blade.php`
- `resources/views/capitan/partials/follow-up.blade.php`
- `resources/views/capitan/partials/analytics.blade.php`
- `resources/views/capitan/partials/table-grid.blade.php`
- `resources/views/capitan/partials/live-context.blade.php`
- `resources/views/mesero/partials/assigned-tables.blade.php`
- `resources/views/mesero/partials/service-cards.blade.php`
- `resources/views/modules/partials/incident-cards.blade.php`
- `resources/views/cash/partials/accounts.blade.php`
- `tests/Feature/OperationsPresentationTest.php`
- `tests/Browser/operations-smoke.mjs`
- `tests/Browser/operations-contracts.mjs`
- Este documento.

## Validación

- `php artisan route:list --except-vendor --no-ansi`: rutas existentes verificadas antes de integrarlas.
- `php artisan view:cache --no-ansi`: compilación Blade correcta.
- `php -l` sobre 145 vistas compiladas: sin errores.
- `npm run build`: compilación Vite correcta, sin dependencias adicionales.
- `node --check` de los scripts complementarios: correcto.
- `php vendor/phpunit/phpunit/phpunit tests/Feature/OperationsPresentationTest.php --no-progress`: 6 pruebas, 147 aserciones correctas. Rutas de los tres roles con datos y estados vacíos, formularios de registro/edición, acceso denegado entre roles, aislamiento visual de otros roles y flujo de atención → servicio ofrecido → Caja → pago con cambio → cierre de turno.
- Las pruebas utilizan únicamente SQLite `:memory:`, con comprobación explícita de la conexión antes de crear los datos temporales. No usan migraciones, seeders ni MySQL.
- `node tests/Browser/operations-contracts.mjs`: comparación con la copia local anterior al rediseño. Se conservaron 21 formularios, 43 contratos de campos y los 5 scripts inline originales. Los 32 controladores, las rutas y `resources/js/app.js` permanecen idénticos.
- `node tests/Browser/operations-smoke.mjs`: Chrome sin interfaz sobre 24 páginas locales exportadas de las pruebas, a 1440, 768 y 390 px. Sin excepciones JavaScript ni desbordamientos de página. Se comprobaron filtros, edición de mesa con observaciones, selección de mesa, menú móvil, apertura de cobro, total, cambio y cierre con Escape.
- Capturas de escritorio y móvil y resultados del navegador en `storage/app/private/ui-preview/`. Son datos aislados de prueba; no se incorporan a las pantallas reales ni a la base de producción.

## Límites y problemas previos

- No se modificó ni se escribió información en MySQL, ni se ejecutaron migraciones o comandos Git durante el rediseño.
- No se realizaron cobros ni registros de prueba sobre cuentas reales. La comprobación del flujo financiero fue íntegramente en memoria.
- Las consultas del capitán conservan su límite original de 100 atenciones, comidas e incidencias; los filtros locales lo indican. Las tarjetas de servicios del mesero y de incidencias compartidas reflejan la página actual del paginador.
- El módulo original del capitán entrega grupos, pero no una colección de entregas. Se conserva su alcance sin añadir consultas ni simular entregas. La ocupación de mesas en la administración física se consulta mediante el acceso a Servicio en vivo.
- Los paneles principales conservan sus indicadores originales. El cajero recibe acceso prioritario al panel financiero de Caja; no se añadieron consultas financieras al controlador del dashboard.
- En la comprobación anterior al rediseño ya fallaban dos pruebas ajenas a este alcance de `RoleAccessTest`: texto esperado en búsqueda de Recepción y permiso de registro inicial de Almacén. No se modificaron esos módulos ni sus permisos.
- El navegador verificó páginas renderizadas con datos temporales, sin una sesión de producción. Queda la revisión de aceptación con las cuentas y dispositivos habituales del usuario.
