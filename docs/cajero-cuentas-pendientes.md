# Cuentas pendientes · escritorio de cobro

Solo `/caja?modulo=pendientes`: encabezado compacto, filtros por fecha de apertura de la cuenta (con fecha de jornada como alternativa si falta), servicio/jornada, concepto/mesa/folio; orden por espera, importe o registro. Incluye cuentas de días anteriores sin alterar sus servicios ni registros.

Distribución 47/53 en escritorio, tabla con selección resaltada y acción fija al extremo derecho; formulario integrado y ticket térmico de vista previa. Los conceptos, cantidades y precios son de consulta. Servicio ofrecido conserva su flujo; el detalle histórico usa `comanda_detalles.precio_unitario`, no el precio actual del catálogo. La consulta del ticket de Cajero también conserva ese precio histórico sin cambiar importes de cobro ni el comportamiento de otros roles.

La espera se calcula exclusivamente con `servicios_ofrecidos.finalizado_en`, el envío real a Caja. Sin timestamp se muestra “Sin registro”; las urgencias cuentan únicamente esperas conocidas superiores a 20 minutos. El promedio y los indicadores se recalculan según los filtros. “Listas para cobrar” exige un turno abierto, además de las condiciones del query original de cuentas.

El script específico no se carga en otras vistas. Solo envía método, recibido y CSRF al endpoint existente. El servidor conserva sus validaciones por sede, turno, estado, totales, cambio y duplicados. La confirmación bloquea importes insuficientes y envíos simultáneos; los errores conservan la selección y la captura. Cancelar solo limpia la selección del navegador.

Después del pago se navega al estado actualizado del servidor, con mensaje de éxito y enlace al ticket cuyo identificador se guardó realmente. El botón del ticket permanece deshabilitado antes de ese momento. La vista previa no tiene folio definitivo, identifica la fecha de consulta y declara que no es un comprobante de pago. No existen impuestos, recargos ni productos inventados.

Limitaciones actuales: no hay método preferente previo al cobro, referencia bancaria persistente ni conexión con terminales/bancos. No se habilitaron campos o filtros ficticios para esas funciones. Las cuentas históricas sin fecha de envío no tienen espera estimada. Las fotos públicas existentes del empleado se reutilizan; cuando la fotografía no está disponible se muestran iniciales, sin acceder a rutas privadas de otros roles.

Archivos: `CashierPendingPresentation`, integración de lectura en `CashierPresentation`, vista `pendientes`, parcial `pending-account-panel`, CSS del workspace y `cashier-pending.js`. Se conserva el layout institucional y las demás vistas. Sin base de datos, migraciones, commit o push.

Verificación aislada: `CashierPendingPresentationTest` (SQLite en memoria), filtros históricos, esperas conocidas/desconocidas, detalle y precio histórico, cobro autoritativo y protección contra duplicados, actualización y enlace de ticket, pantalla vacía. Navegador `cashier-workspace.mjs --pending-only`: 1440/390 px, selección, métodos, cambio, captura insuficiente, bloqueo de doble envío, conservación de datos tras validación y cancelación. No se hicieron cobros reales.
