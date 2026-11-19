# Turno de caja · presentación compacta

El cambio se aplica exclusivamente a `/caja?modulo=turno`. Los estilos dependen de `[data-cr-module="turno"]`. Conserva apertura, validaciones, cálculo de efectivo esperado y acceso al proceso original de cierre. Las consultas nuevas son únicamente de lectura y respetan la sede activa.

Incluye cabecera compacta, barra de estado, cuatro indicadores, apertura y auditoría, movimientos derivados de los registros, gráfica SVG mixta con dos ejes y detalles al pasar el cursor, distribución por método y corte parcial imprimible. Reutiliza el mecanismo SVG/CSS existente; no se encontró una librería externa de gráficas instalada. La impresión clona el resumen para el navegador, sin enviar solicitudes ni cerrar el turno.

Fondo inicial y cambio no cuentan como cobros adicionales. Los ingresos en efectivo muestran lo recibido y su cambio aparece como egreso; el esperado resulta del fondo más recibido menos cambio. Las ventas y la distribución por método usan el importe neto cobrado. Tarjeta y transferencia se identifican como no efectivo.

La diferencia permanece pendiente hasta que existan efectivo contado y diferencia registrados; los turnos cerrados usan su arqueo persistido. Sin turno activo se presenta el último cerrado y el formulario de nueva apertura. Una consulta opcional `turno=<folio>` solo admite turnos de la sede activa.

Limitaciones existentes: no hay almacenamiento de observaciones, horario de cierre esperado, movimientos manuales de ajuste/retiro ni registro independiente de emisión de tickets. No se simulan estas funciones ni se añaden permisos. El indicador de tickets cuenta comprobantes disponibles por pago.

Comprobación: `CashierTurnoPresentationTest` en SQLite en memoria (27 aserciones), vista sin apertura, turno abierto, arqueo cerrado con diferencia y rechazo de otro sitio. Navegador local, solo Turno de caja a 1440/390 px, gráfica mixta y acción de imprimir sin solicitudes operativas. Sin migraciones, cambios en la base real, commit ni push.
