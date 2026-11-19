# Jefe de almacén

Las siete pantallas utilizan la sede y el rol activos. Los estilos de `public/css/warehouse-workspace.css` están bajo `.warehouse-workspace` y no se aplican a Gerente ni a Recepción.

## Flujo

- Se reutilizan el controlador, las rutas y validaciones de pedidos. Guardar conserva `Programado` y no genera entradas.
- `/pedidos/nuevo?insumo=ID` abre el formulario lateral con un insumo de la sede precargado; el pedido exige confirmación manual.
- `/recepciones` revisa capturas existentes. El faltante proviene de `faltante_entrega`; los registros históricos sin ese dato lo indican expresamente.
- Se conectaron `ReceptionOrderController::confirm` y `returnForCorrection`. La confirmación bloquea recepción y saldos, rechaza movimientos previos e ingresa solo cantidades aceptadas. Requiere ubicación de la sede, lote coincidente y caducidad vigente.
- La devolución utiliza `Rechazada por almacén`, ya declarado en `receptions.released_states`, conserva el historial y no genera movimientos.
- La confirmación se ofrece únicamente con el esquema completo y ubicaciones registradas; no se ejecutan migraciones.
- El indicador de pedidos recibidos exige todas las cantidades aceptadas y confirmadas por partida. No confunde una entrega parcial con un pedido completo ni inventa estados en la base de datos.

## Límites del esquema

- `proveedores` es un catálogo global sin `id_sede`. Se mantiene su registro y edición; todas sus relaciones operativas se filtran por sede. No se presume que esté activo cuando no tiene ese dato.
- No se agregan campos de contacto, horarios ni estados. Se presentan únicamente los campos disponibles.
- No se muestra valor del inventario por falta de costos completos y confiables. Los importes de pedidos se muestran solo con precios para todas sus partidas, en MXN.
- Las gráficas cuentan insumos o movimientos y no suman unidades incompatibles.
- Existencias bajas: stock positivo menor o igual al punto de reorden. Agotado: stock menor o igual a cero. Los saldos o mínimos sin definir se identifican expresamente.
- Caducidades: lotes con saldo positivo de la sede dentro de los siguientes 30 días.
- Ubicaciones: las registradas en el historial de movimientos. La existencia resultante se muestra solo cuando está almacenada; no se calcula un saldo hipotético.
- Los avisos reutilizan la campana y el marcado por sesión, usuario, sede y rol; no se crea una tabla de notificaciones.

## Fotografías

Recursos locales de Pexels, utilizados como decoración y no como fotografías de una sede específica:

- `warehouse-logistics.jpg`: foto 4481259, https://www.pexels.com/photo/4481259/. Archivo: https://images.pexels.com/photos/4481259/pexels-photo-4481259.jpeg.
- `warehouse-food.jpg`: foto 2646367, https://www.pexels.com/photo/2646367/. Archivo: https://images.pexels.com/photos/2646367/pexels-photo-2646367.jpeg.

No se recibieron las siete imágenes de referencia en el contexto; se siguió la descripción escrita y la identidad Oxford de los roles anteriores.

## Pruebas

`php artisan test --filter=test_warehouse_workspace_`

La prueba define su esquema en SQLite `:memory:`, retira las conexiones externas y no ejecuta migraciones. Incluye las siete vistas, rol activo, sede, pedidos, confirmación, devolución, duplicidad y notificaciones. No utiliza Chrome.
