# Tickets · consulta administrativa compacta

Alcance exclusivo: `/caja?modulo=tickets`. Tabla paginada y seleccionable, orden por fecha o importe, filtros por fechas, estado existente, método, turno de la sede y búsqueda. Distribución 55/45 en escritorio; hasta cuatro comprobantes recientes y vista previa térmica en la misma pantalla. Los estilos dependen de `[data-cr-module="tickets"]` y el script propio se carga solo en esta vista.

`CashierTicketPresentation` incorpora únicamente consultas de lectura: selección de recibos, conceptos originales de Servicio ofrecido, precios históricos de `comanda_detalles` y cantidades de comensales. No modifica el endpoint de cobro ni la consulta independiente de tickets de otros módulos. El total proviene del pago registrado; no se recalculan importes cobrados ni se añaden impuestos, descuentos o recargos.

La cabecera muestra el turno filtrado, el activo o el último registrado. Los indicadores cuentan comprobantes disponibles, pagos confirmados, importe confirmado del periodo y comprobantes del turno. Los estados de los filtros proceden de los registros reales de la sede. Un turno de otra sede no se admite.

Logo y dirección provienen de la sede. RFC y referencia solo se muestran si están registrados. Cada comprobante conserva folios de pago y atención, fecha, mesa, cajero, cantidades, precios e importes originales. El recibido y cambio aparecen únicamente para efectivo. Los comprobantes no pagados mantienen visible su estado y su importe se identifica como registrado, no como pagado.

Imprimir, reimprimir y guardar como PDF clonan únicamente el ticket seleccionado para la impresión del navegador. Los anchos de contenido disponibles son 58 y 80 mm; debe elegirse el papel correspondiente en las opciones de la impresora. El estilo temporal elimina márgenes y se retira al terminar. La impresión conserva logo y conceptos, permite varias páginas cuando sea necesario y usa texto negro. No hay llamadas de escritura, folios nuevos ni generación de otro cobro. PDF usa “Guardar como PDF” del navegador, sin una descarga generada en servidor.

No existe registro de reimpresiones o entregas, mecanismo de envío ni verificación QR. Se muestran métricas alternativas reales, el envío permanece deshabilitado y no se agregan QR decorativos. No se habilitan anulación, reembolsos o permisos financieros nuevos.

Comprobaciones aisladas: `CashierTicketsPresentationTest`, SQLite en memoria (29 aserciones), filtros, orden, paginación, conceptos y precios históricos, protección por sede, estado vacío y pagos intactos antes/después de consultar. `cashier-workspace.mjs --tickets-only`: escritorio/móvil, selección sin navegación, mismo folio al imprimir/reimprimir/PDF, ancho térmico y envío deshabilitado. Sin solicitudes a la instalación real ni cobros reales.

Sin cambios de base de datos, migraciones, commit o push. Las demás vistas y roles conservan su presentación.
