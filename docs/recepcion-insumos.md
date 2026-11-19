# Recepción de insumos

Entorno aislado para el rol activo de Recepción de insumos. Reutiliza pedidos, detalle_pedidos, recepciones, recepcion_detalles, lotes e incidencias existentes. No requiere migraciones.

- Panel principal: pedidos programados de la sede; indicadores, gráficas, actividad y últimas recepciones del empleado en esa sede. Un dato no disponible se identifica como tal; las gráficas sin registros muestran su estado vacío.
- Entregas programadas: consulta y filtros de pedidos existentes, sus partidas y observaciones; sin acciones de creación, edición o eliminación de pedidos.
- Registrar recepción: selección de un pedido habilitado, captura existente de cantidades y calidad, cálculo de rechazados/faltantes, lote y caducidad, explicación de diferencias, resumen y confirmación antes del envío. Conserva idempotencia y validación del servidor. No registra movimientos de inventario.
- Historial: registros propios con búsqueda, fecha, estado, paginación y comprobantes. Incluye cantidades solicitadas y seguimiento de incidencias. Solo una nueva captura corregida puede sustituir operativamente una recepción en los estados liberados configurados; no se edita el comprobante revisado.
- Incidencias: registro vinculado a partidas de recepciones propias y consulta de respuestas; no permite resolver ni cerrar incidencias. Usa el estado existente Pendiente.
- Notificaciones: reutilizan el componente y script actuales. Se generan a partir de pedidos programados para los próximos siete días, resultados de revisión propios y respuestas existentes a incidencias. Las lecturas se guardan en la sesión, separadas por usuario, sede y rol; no se crea un sistema de eventos ni se escribe a la base para marcar lecturas.
- Navegación y backend: solo los cinco apartados del rol, además de cambio de sede/rol, contraseña y cierre de sesión. Inventario, compras y revisión de Almacén quedan bloqueados con Recepción como rol activo. Los estilos nuevos se limitan a `.reception-workspace`.

## Límites de la estructura actual

- Evidencia fotográfica: el flujo de recepción no dispone de un almacenamiento o campo de adjuntos; queda pendiente. No se añaden controles que prometan guardar fotografías.
- Categorías: no hay categorías estructuradas de incidencias en este flujo. Daños, caducidad, faltantes, producto incorrecto y problemas de calidad se documentan en la descripción existente, sin crear categorías o estados nuevos.
- Horario de entrega: se muestra si el pedido dispone de `hora_entrega`; cuando no existe, se muestra únicamente la fecha registrada.
- Captura: conserva los requisitos existentes de activación y clave única de idempotencia. Si faltan, la consulta sigue disponible y el registro se informa como no habilitado; no se prepara ni altera el esquema automáticamente.
- Revisión de Almacén: el controlador incluye confirmación/devolución, pero esta instalación no registra sus rutas POST. Su habilitación corresponde al desarrollo del rol Jefe de almacén, fuera de este cambio. Recepción no obtiene acceso a esas acciones.
- Se reutiliza la fotografía alimentaria local existente como fondo discreto; no se proporcionaron imágenes adjuntas en este turno.

## Verificación

`ReceptionWorkspaceTest` usa exclusivamente SQLite en memoria y crea su esquema de prueba sin migraciones. Cubre las vistas, cifras reales de fixtures, filtros, notificaciones, lectura sin captura habilitada y separación por empleado/sede/rol. Se verificaron también casos existentes de captura que comprueban incidencias, validaciones y ausencia de movimientos de inventario.

`tests/Browser/reception-workspace.mjs` verifica siete pantallas exportadas del test a 1440, 768 y 390 px, ausencia de desbordamientos, navegación exclusiva y resumen/modal de captura sin enviar el formulario. Para exportar las vistas, ejecutar el test con `RECEPTION_UI_PREVIEW=1`; el script usa archivos de `storage/app/private/reception-preview` y no solicita páginas de la aplicación real.
