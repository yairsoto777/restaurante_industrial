# Catálogo de insumos para Jefe de almacén

## Diagnóstico de Puerto Esmeralda

La consulta de solo lectura encontró la sede 7, Marisquería Puerto Esmeralda, sin asignaciones en `insumo_sedes`. Distribuidora Océano Fresco existe en el catálogo compartido de proveedores y la unidad Kilogramos (`kg`, id 1) permite fracciones. El bloqueo de Nuevo pedido se debía a la falta de insumos asignados, no al proveedor.

El proyecto ya tenía `Ingredient` y `Unit`, además de alta/asignación en `InventoryController` autorizadas para Jefe de cocina y Jefe de almacén. La interfaz nueva de Almacén omitía los formularios y su lista de acceso bloqueaba esos endpoints. La función anterior de alta también exigía una entrada inicial, impropia para el catálogo de abastecimiento.

## Corrección sin migraciones

- Nuevo acceso **Abastecimiento → Catálogo de insumos**, `/almacen/catalogo-insumos`.
- Registrar insumo y Asignar del catálogo usan los endpoints existentes `inventory.ingredients.store` e `inventory.assignments.store`.
- Solo la rama del Jefe de almacén delega al nuevo `WarehouseIngredientController`. Los flujos de otros roles se conservan.
- Se valida el rol y la asignación a la sede activa. La sede no se acepta del formulario.
- La existencia inicial es cero. No se insertan lotes, entradas ni movimientos. La asignación de un catálogo existente no modifica el insumo global ni el stock de otras sedes.
- Se rechazan nombres duplicados y asignaciones repetidas sin sobrescribir cantidades.
- Nuevo pedido ofrece un acceso directo al catálogo si faltan insumos y se habilita al contar con proveedor e insumos con unidad válida.
- No se habilitaron movimientos manuales ni captura de entregas para Jefe de almacén. Se mantienen las rutas de Recepción y revisión/confirmación existentes.

La tabla real `insumos` contiene `id_insumo`, `nombre`, `id_unidad_base` y `caducidad`. No hay columnas de categoría, descripción o estado ni relación con `categorias` (catálogo de platillos). No se guardan estos datos en columnas ajenas ni se muestran controles ficticios. Ampliarlos requiere un cambio de esquema autorizado; no es necesario para registrar productos y realizar pedidos. La vinculación a la sede se muestra separadamente como tal.

## Demostración

Con Rodrigo y la sede Puerto Esmeralda activa: **Abastecimiento → Catálogo de insumos → Registrar insumo**. Registrar individualmente camarón mediano, filete de pescado, pulpo fresco y calamar fresco, seleccionando **Kilogramos (kg)** en cada formulario. Si alguno ya existe en el catálogo, usar **Asignar del catálogo** en lugar de duplicarlo. Después abrir **Pedidos de insumos → Nuevo pedido** y elegir Distribuidora Océano Fresco.

## Comprobación

Una prueba aislada en SQLite en memoria, sin ejecutar migraciones: `WarehouseIngredientCatalogTest::test_catalog_zero_stock_assignment_and_order_flow_are_isolated`. Pasaron 44 comprobaciones: formulario, unidades, alta con cero, asignación, duplicados, sede activa, permisos y pedido sin movimientos ni lotes. Las consultas de la base real fueron solo de lectura; no se registraron los productos de la demostración ni se alteraron registros existentes.
