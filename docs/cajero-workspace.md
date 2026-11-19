# Cajero · rediseño de seis vistas

Entrada: `/caja?modulo=panel|turno|pendientes|historial|tickets|cierre`. La ruta existente `/caja/historial` abre el historial de cobros para el rol activo Cajero. Los turnos históricos siguen accesibles desde Turno de caja y Cierre de caja. El dashboard del Cajero redirige a su panel específico.

## Alcance

Plantillas en `resources/views/cash/workspace*`, iconos en `resources/views/components/cashier/icon.blade.php`, CSS `public/css/cashier-workspace.css` y JS `public/js/cashier-workspace.js`. Todos los selectores visuales dependen de `.ops-cashier`; el script requiere su workspace. El layout compartido solo añade recursos, navegación, nombre de sede y tratamiento de avisos bajo condiciones de Cajero. Las plantillas anteriores de Caja se conservan para los demás roles.

`CashierPresentation` aporta consultas de lectura, filtros, paginación, indicadores y respuestas AJAX. No reemplaza los cálculos ni las transacciones de abrir, cobrar y cerrar. Se conserva el permiso y alcance por sede de CashierController. Las respuestas JSON evitan consumir mensajes flash antes de la navegación y muestran validaciones dentro del formulario. No se habilitan acciones administrativas nuevas.

## Vistas

- Panel: indicadores diarios, distribución de importes por método, cobros por hora, turno y movimientos recientes.
- Turno: apertura con fondo inicial, responsable real, resumen de cobros y consulta de turnos anteriores.
- Pendientes: cuentas compactas con concepto, mesa, zona e importes; modal con subtotal registrado, total, método, recibido y cambio. El servidor conserva su cálculo y validación originales.
- Historial: filtros de fechas, método y búsqueda, sumas del periodo y paginación.
- Tickets: consulta de comprobantes por pago, vista previa, reimpresión y guardado PDF mediante el navegador. No se simula un registro de emisiones: los indicadores cuentan tickets disponibles por pagos existentes.
- Cierre: métodos, fondo, recibido, cambio, esperado, efectivo contado y diferencia preliminar; confirmación explícita y arqueo persistido por el controlador existente.

## Limitaciones existentes

La estructura actual de `caja_turnos` no almacena observaciones de apertura/cierre. No se añadió un campo que descarte notas ni se prepararon/ejecutaron migraciones para una ampliación ajena al rediseño. Si en otra instalación existe `observaciones`, el resumen del turno puede consultarla. Capturar y guardar notas requeriría una ampliación posterior autorizada.

Los tickets siguen siendo administrativos, sin CFDI ni integración fiscal nueva. Guardar PDF usa el diálogo de impresión del navegador, sin servicio externo ni documentos ficticios. El backend y los detalles históricos del ticket se conservan; no se introdujeron anulaciones, nuevos métodos o cambios de tarifas.

## Validación

`tests/Feature/CashierWorkspaceTest.php`: SQLite en memoria, seis vistas, navegación, apertura/cobro/ticket/cierre, efectivo insuficiente, cálculo de cambio y arqueo, filtros y aislamiento de sedes. `tests/Browser/cashier-workspace.mjs`: HTML de ese fixture, seis vistas y ticket a 1440/390 px, modal de cobro, campos por método, cambio, validación conservando valores y confirmación de cierre. Sin solicitudes operativas a la instalación real.

No se modificó la base de datos de la instalación ni se ejecutaron migraciones, commit o push.
