# Recepción de insumos: diseño y fotografías

Los ajustes se limitan a la recepción del Jefe de almacén. La hoja de estilos se carga únicamente en esa vista. Se conservan las acciones, sus permisos, la captura histórica y los movimientos de inventario.

Las tarjetas muestran las cinco cantidades, unidad, categoría disponible, resultado, lote, caducidad, observaciones e incidencias. La barra usa lo entregado como denominador; una entrega de cero no genera porcentajes. El faltante procede exclusivamente del dato almacenado, sin reconstruir cantidades históricas.

## Catálogo y permisos actuales

El catálogo pertenece a Inventario (`InventoryController`). Sus rutas de registro y edición contemplan Jefe de cocina y Jefe de almacén, con validación de sede. Sin embargo, la lista de rutas autorizadas del espacio activo de almacén (`WarehouseWorkspace::allows`) no incluye esas operaciones: su Inventario actual es de consulta. No se ampliaron permisos en este ajuste de diseño.

La consulta de solo lectura del esquema configurado encontró estas columnas en `insumos`: `id_insumo`, `nombre`, `id_unidad_base` y `caducidad`. No existen `imagen_url` ni `categoria`. No se modificó el esquema ni se ejecutaron migraciones.

## Modificación necesaria para fotografías

Para habilitar fotografías se necesita una modificación futura del esquema, revisada y autorizada por separado: añadir `insumos.imagen_url` nullable para guardar la ruta de la imagen de catálogo. La categoría requeriría también su campo o relación de catálogo. Después se debe agregar carga y edición de imágenes en Inventario, validando archivo, formato, tamaño, sede y rol activo, y autorizar específicamente esa operación al Jefe de almacén. Los archivos deben guardarse en el almacenamiento de catálogo y la base conservar únicamente su ruta.

Recepciones, captura de recepción e inventario de almacén ya consultan opcionalmente `imagen_url`; esta vista reutiliza el mismo resolvedor `WarehouseWorkspace::image`. Las demás vistas autorizadas podrán consultar ese mismo campo al habilitar la función, sin duplicar imágenes por recepción o pedido. No se extendieron otras vistas ni roles.

La fotografía de catálogo identifica el producto. Las evidencias de mercancía dañada pertenecen a la recepción o incidencia y nunca deben alimentar `imagen_url`. Si falta fotografía, esta recepción muestra un icono de caja; también lo conserva si falla la carga de una imagen.
