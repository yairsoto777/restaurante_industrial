# Integración del Jefe de almacén

Esta matriz describe el contrato actualmente disponible. Todas las consultas deben recibir la sede activa y filtrar por su identificador.

| Vista | Tablas / consulta | Operación de origen | Permiso | Prueba |
|---|---|---|---|---|
| Pedidos | `pedidos`, `detalle_pedidos`, `proveedores` vía `ReceptionOrderController` | Jefe de almacén o recepción crea el pedido | `Jefe de almacen` | `RoleAccessTest`, `ReceptionCaptureTest` |
| Recepciones | `recepciones`, `recepcion_detalles`, `lotes` | Recepción registra mercancía | Consulta almacén; captura según rol | `ReceptionCaptureTest` |
| Inventario/Kardex | `insumo_sedes`, `inventarios_finales`, `ubicacion_inventarios`, `lotes` | Movimientos confirmados | Consulta por almacén; mutaciones actuales pertenecen a cocina | `InventoryController` tests existentes |
| Incidencias | `incidencias` enlazada opcionalmente a `recepcion_detalles` | Personal registra incidencia | Consulta por almacén | `ReceptionCaptureTest` |
| Requisiciones | `requisiciones` si existe | Cocina registra; almacén autoriza | Consulta condicionada | Estado vacío hasta contrato externo |
| Transferencias, conteos, ajustes y cierres | Sin tablas/operaciones persistidas en este corte | Dependencia externa | No habilitado | Pendiente de contrato |

No se insertan datos demostrativos en `ar_menu`. La prueba integral solicitada requiere primero el contrato persistido de requisiciones, transferencias, autorización de recepción, conteos, ajustes y seguimiento de incidencias; implementarlo aquí sin modificar cocina/recepción implicaría inventar una segunda fuente de verdad.
