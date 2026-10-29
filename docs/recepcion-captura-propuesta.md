# Captura de recepciones: propuesta pendiente de aprobación

## Activación

La captura está deshabilitada en `config/receptions.php`. No se ha aplicado ninguna migración a MySQL.
Antes de activar, revisar este contrato, autorizar y aplicar por separado la migración
`2026_10_28_000001_add_reception_idempotency_key.php`, y luego habilitar `capture_enabled`.
No habilitar en producción para pruebas manuales: usar una base de desarrollo aislada.

La inspección de solo lectura de `ar_menu` no devolvió filas en la agrupación por estado de
`pedidos` ni `recepciones`. Los campos son varchar sin catálogo/check; no se infiere un catálogo existente.

## Estados propuestos (no son estados históricos verificados)

| Entidad | Valor | Tratamiento propuesto |
|---|---|---|
| Pedido | Programado | Único estado habilitado para captura por ahora |
| Recepción | Pendiente de revisión | Estado de captura; reserva cantidades aceptadas, sin stock |
| Recepción | Confirmada | Sigue consumiendo pendiente; futura confirmación por almacén |
| Recepción | Anulada | No consume pendiente |
| Recepción | Rechazada por almacén | Libera toda la cantidad aceptada provisional |

Un estado diferente o nulo en recepciones del detalle bloquea nuevas capturas (409), para evitar sobrecaptura.
Las incidencias nuevas usan `Pendiente`, ya utilizado en el código de incidencias de cocina.
El resultado de revisión es texto libre obligatorio (máximo 50 caracteres); no cambia el estado de la recepción.

## Cantidades y evidencia

Se trabaja en milésimas enteras en el servidor, con un máximo de tres decimales.
Pendiente = max(0, solicitada - aceptada acumulada de estados que consumen pendiente).
Entregada no puede exceder pendiente; aceptada no puede exceder entregada.
Rechazada = entregada - aceptada. Faltante de entrega de esta captura = max(0, pendiente previo - entregada).
Un faltante parcial exige incidencia, aunque el proveedor tenga previsto completar después.
Se exige lote/caducidad para toda cantidad entregada y al menos una entrega positiva por recepción.
Se utiliza `unidades.permite_fracciones`: unidades enteras exigen entradas enteras, igual que Inventario;
las fraccionables conservan hasta tres decimales. Una unidad inexistente bloquea la captura.
La mercancía aceptada no puede caducar antes del día de recepción en America/Mexico_City;
puede caducar ese mismo día. La mercancía totalmente rechazada puede documentarse vencida con incidencia.
El cliente no puede enviar `id_insumo` ni `id_lote`: se usa el insumo del detalle y un lote creado en servidor.
Para un código ya registrado del mismo insumo/proveedor se exige la misma caducidad. No se asume que los
códigos sean únicos entre productos distintos y no se consolidan lotes automáticamente.
No se identificó en los modelos/migraciones revisados una vigencia mínima específica por tipo de insumo;
no se aplica una vigencia universal.
Los detalles sin cantidad solicitada bloquean captura; no se interpreta null como cero.

No se agregó una columna para faltante: se conserva una instantánea decimal dentro de
`recepcion_detalles.observaciones`, como JSON versionado con `version`, `captura` y `faltante_entrega`.
`captura` conserva los valores normalizados para detectar reutilización de una clave con datos distintos.
La vista muestra las observaciones humanas; mantiene lectura de texto histórico sin JSON.
Revisar este contrato de almacenamiento con el equipo; si se necesita filtrar faltantes por SQL,
conviene proponer una columna dedicada en otra migración, con autorización independiente.

## Idempotencia y concurrencia

El formulario genera un UUID aleatorio firmado/cifrado por Laravel, vinculado a usuario, sede y pedido.
El servidor verifica ese vínculo y normaliza el UUID a minúsculas. El mismo formulario conserva su token
tras errores de validación. No depende de deshabilitar botones ni de la sesión para detectar reintentos.
Cada POST bloquea la fila del pedido y realiza toda la escritura en una transacción con reintento de deadlocks.
Los reenvíos equivalentes redirigen al mismo recurso; reutilizar la clave con otros valores devuelve 409.
Una colisión con otra operación devuelve 409; la restricción única es la defensa final en la base.
Las claves históricas null no impiden migrar y no se usan para nuevas capturas.
No hay endpoints de modificación, confirmación, anulación o eliminación de recepciones.

## Dependencias con Jefe de almacén

- Aprobar los nombres de estados y las transiciones; confirmar si rechazo de almacén es total o parcial.
- Todo cambio de estado que libere/reserve cantidades debe bloquear la misma fila de pedido y ser transaccional.
- Una futura modificación de cantidades del pedido debe respetar ese bloqueo y sus recepciones existentes.
- La confirmación deberá generar entradas exactamente una vez, exclusivamente por cantidades aceptadas,
  y guardar responsable de confirmación: el esquema actual tiene fecha, pero no un campo específico para ese usuario.
- No habilitar cambios de estado de recepciones ya confirmadas sin definir reversión autorizada del inventario.
- Revisar obligatoriedad de caducidad para productos no perecederos y vigencia mínima de lotes.
- Los lotes se crean como evidencia de recepción, incluso de mercancía rechazada. Tener lote no implica existencia.

## Pruebas y límites

Las pruebas construyen tablas efímeras en SQLite `:memory:` sin ejecutar migraciones y eliminan
las conexiones MySQL de la configuración de los casos de captura. No escriben en `ar_menu`.
Cubren reenvíos, conflicto de payload, cantidades, parciales, estados liberadores/desconocidos,
autorización y sedes, incidencias y saldo/historial intactos.
SQLite en memoria no reproduce los bloqueos InnoDB ni concurrencia real entre conexiones;
esa validación debe hacerse posteriormente en MySQL aislado, nunca con datos reales.
