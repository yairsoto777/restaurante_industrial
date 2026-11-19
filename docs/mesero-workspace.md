# Mesero · workspace

Las siete vistas se encuentran en `/mesero?modulo=panel|servicios|mesas|atenciones|menu|historial|incidencias`. Comparten el layout administrativo y tienen navegación, estilos y scripts propios. No se rediseñaron los demás roles.

- Servicios y menú publicados se consultan desde jornadas aprobadas accesibles por asignación o atención propia histórica. Las mesas operativas requieren asignación activa a esa jornada y sede; no hay altas de mesas ni asignación de zonas desde Mesero.
- Las aperturas exigen jornada en curso, mesa físicamente disponible, capacidad válida y ausencia de cualquier atención incompatible, incluso de otra jornada. La mesa se bloquea en la transacción.
- Servicio ofrecido conserva el registro existente por atención, precio manual del flujo existente, cálculo validado en servidor y envío a cuenta pendiente de Caja. No modifica el catálogo, registra pagos ni libera automáticamente mesas. Las confirmaciones bloquean doble envío; el servidor conserva sus bloqueos y validación de duplicados.
- Incidencias propias con zona/mesa opcionales, validadas según asignación y jornada; evidencia JPG/PNG/WEBP de hasta 4 MB almacenada en disco público mediante el mecanismo existente. No se habilitan cierres ni resolución. El tipo elegido se valida y se conserva como prefijo explícito de la descripción; la estructura existente no tiene una columna independiente de clasificación.
- Fotografías de empleado provienen del perfil registrado por Gerencia; la ruta nueva entrega exclusivamente la fotografía del usuario autenticado en su sede. Portadas, zonas y platillos reutilizan las imágenes existentes. Las ilustraciones adaptan el espacio entre sillas para capacidades grandes.
- Comedor en vivo recibe las atenciones e incidencias mediante las tablas compartidas y su actualización existente. Las categorías y estados mostrados proceden de los registros reales.

## Pendiente de autorización

`database/migrations/2026_11_16_000002_add_waiter_attention_observations.php`: agrega únicamente `atenciones.observaciones` TEXT nullable, sin eliminar ni actualizar registros históricos. Mientras no exista, el campo aparece deshabilitado y el backend rechaza observaciones para evitar descartarlas silenciosamente. Al autorizar y ejecutar exclusivamente esta migración se habilita automáticamente su almacenamiento y visualización durante la atención.

Base confirmada: `ar_menu_equipo`. Respaldo externo verificado de 64 tablas: `C:/xampp/backups/NUTRILIADO/ar_menu_equipo-before-waiter-20261116-145357.sql`. SHA-256: `e53712f6c2d4a0179e0c061cfe1819d6e053c2b39e5efb837d0ffddf9f3de306`. Se comprobó exportación completa, definición de todas las tablas y hash; no se realizó restauración sobre la instalación.

## Integración pendiente de comandas

Existen tablas y seguimiento de preparación en Cocina, pero no un flujo completo de creación y envío de comandas desde Mesero. El menú queda de consulta. Para habilitarlo posteriormente faltan alta transaccional por atención, validación del menú de jornada y cantidades, envío/recepción idempotentes, notificaciones y definición de cómo conviven comandas e importes del Servicio ofrecido. No hay botones de envío simulado.

## Comprobaciones aisladas

`tests/Feature/WaiterWorkspaceTest.php`: SQLite en memoria; vistas, alcance de asignación, validaciones de apertura, notas, evidencia persistente, cálculo y envío único a Caja. `tests/Browser/waiter-workspace.mjs`: HTML de ese fixture, siete vistas a 1440 y 390 px, navegación, panel lateral, contador de comensales, conservación de datos en validación y limpieza de mesa al cambiar de zona. Sin solicitudes operativas a producción.

No se ejecutaron migraciones, seeders, commit ni push. Las imágenes de referencia mencionadas no estuvieron disponibles como adjuntos en esta sesión; se reutilizaron los recursos locales y el lenguaje visual de Capitán.
