# Caja: fase 1

## Alcance y política

- Se conserva `GET /caja` (`cash.index`).
- `POST /caja/aperturas` (`cash.open`) abre el turno del usuario autenticado.
- `GET /caja/turnos/{cashSession}` (`cash.sessions.show`) consulta un turno autorizado.
- Una caja abierta por Cajero y sede. Distintos cajeros pueden tener turnos simultáneos en la misma sede; el mismo cajero puede operar en distintas sedes si tiene asignación Cajero en ambas.
- Cajero: consulta sus turnos de la sede activa y abre el suyo. Gerente: supervisa turnos de las sedes en las que es gerente. Administrador: supervisa la sede seleccionada conforme al alcance global existente. Gerentes/administradores solo abren si además tienen rol Cajero en esa sede. No se amplía el acceso del Superadministrador.
- La sede procede de SiteContext. El navegador no puede escoger responsable, sede, estado ni fecha de apertura.

## Persistencia y concurrencia

No se requieren migraciones para esta fase. Se usan `cortes_caja` y `bitacora`, sin timestamps convencionales. El fondo admite 0 a 9999999999.99, máximo dos decimales, sin floats.

La apertura bloquea `usuarios.id_usuario` con `SELECT ... FOR UPDATE`, revalida la asignación de sede y busca el corte abierto con lectura bloqueante antes de insertar. La fila del usuario existe incluso en la primera apertura y serializa solicitudes concurrentes. Se reintentan deadlocks hasta tres veces. Esto protege todas las aperturas de este endpoint; cualquier futuro escritor debe respetar el mismo protocolo. No garantiza integridad frente a SQL externo que lo omita.

La inserción del corte y su bitácora son obligatorias y atómicas. Si falla la auditoría, se revierte la apertura. No se escriben cuentas, comandas ni pagos. Las lecturas del panel no crean aperturas ni bitácoras.

## Totales

El panel lee pagos asociados al corte y comprueba cuenta → atención → servicio → sede, porque no hay FK físicas. Los vínculos inválidos o de otra sede no se exponen. Solo se suman estados `confirmado` (ignorando espacios y mayúsculas); los demás aparecen sin sumarse. Métodos reconocidos para el estimado: efectivo, tarjeta, transferencia, otros. No se modifica el contenido histórico.

Total de cobros: suma de importes confirmados. Efectivo esperado: fondo + confirmados en efectivo. Con pagos ajenos/huérfanos, métodos desconocidos, importes no positivos o fuera de rango, el panel exige conciliación y no presenta un efectivo esperado definitivo. Sin pagos, el total real es cero y el esperado equivale al fondo.

## Cierre y siguientes fases

El cierre NO está habilitado y no existe endpoint de cierre. No se captura efectivo contado ni se cambia estado. Aún faltan el escritor de pagos con bloqueo del corte, idempotencia persistente, correcciones/reversas y el contrato de Meseros/Menús. Sin ese protocolo no se garantiza que no se añadan movimientos a un corte cerrado. La pantalla explica el límite sin simular un cierre.

No se implementan cobros, cuentas por cobrar, detalle de consumo, generación de cuentas ni historial financiero completo. Las correcciones de apertura sin movimientos se incorporan en la fase 1.1 descrita abajo. Tampoco se modifica el esquema de menús.

## Pruebas aisladas

- SQLite en memoria: `C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit --configuration phpunit.cash.xml`.
- MariaDB/InnoDB, incluyendo dos procesos de apertura concurrentes: `C:\xampp\php\php.exe tests/Cash/run-mariadb.php`.

CashTestCase impone la conexión antes de arrancar Laravel, ignora cachés de configuración/rutas y comprueba el destino antes de DDL. No ejecuta migraciones ni seeders. SQLite solo acepta `:memory:`. MariaDB solo acepta un nombre aleatorio `gi_cash_test_` seguido de 16 caracteres hexadecimales y autorización explícita del runner.

El runner utiliza la conexión local sin seleccionar una base de negocio, crea una base temporal nueva, pasa credenciales por entorno sin imprimirlas, ejecuta únicamente la suite Caja y elimina exclusivamente esa base generada al terminar. Requiere privilegios CREATE/DROP DATABASE. Nunca utiliza `base_comedor` como destino de pruebas. Los registros de prueba no se muestran en el producto.

La prueba concurrente retiene el bloqueo del usuario en el proceso padre mientras dos procesos ejecutan la apertura y comprueba una apertura y una bitácora. SQLite omite esa prueba porque no demuestra bloqueos InnoDB.

Verificación inicial de la fase 1: SQLite, 30 pruebas (29 correctas, una omitida por requerir InnoDB), 123 aserciones; MariaDB/InnoDB, 30 correctas, 134 aserciones. Se comprobaron también login real de un usuario de prueba aislado, CSRF con el middleware activo, renderizado con el manifiesto Vite existente, rollback por fallo de auditoría y dos aperturas concurrentes.

Durante esa verificación inicial, la instancia habitual de XAMPP estaba detenida. La ejecución MariaDB utilizó un servidor temporal independiente, con su propio directorio de datos y puerto, y una base de pruebas con el prefijo indicado. Esa instancia no contenía `base_comedor`; la base temporal se eliminó y el servidor se apagó. No se abrió ningún turno en la base real durante las pruebas.

## Verificación manual posterior

Iniciar sesión con el Cajero real, seleccionar su sede, abrir `/caja`, introducir fondo y abrir. Comprobar turno, responsable, fecha, fondo, cero cobros si no existen pagos y la fila correspondiente en MySQL. Recargar y reenviar no debe abrir un segundo turno. Probar supervisión gerencial y rechazo de acceso ajeno. No cerrar ni registrar pagos: fuera de esta fase.

## Trabajo colaborativo

Trabajar en `feature/modulo-cajero`. Proteger `resources/css/nutriliado-public.css`: es un cambio local previo, ajeno a esta fase. No mezclarlo en el commit del módulo. No se cambian layout, dependencias, Vite, menús, cocina, meseros ni servicios. No se ejecutan commits o push automáticamente.

## Fase 1.1: corrección y anulación de aperturas sin movimientos

- `GET /caja/turnos/{cashSession}/fondo/editar` (`cash.fund.edit`) muestra el formulario de corrección.
- `POST /caja/turnos/{cashSession}/fondo` (`cash.fund.update`) guarda fondo y efectivo esperado.
- `GET /caja/turnos/{cashSession}/anular` (`cash.cancel.confirm`) solicita motivo y confirmación explícita.
- `POST /caja/turnos/{cashSession}/anular` (`cash.cancel`) conserva el registro con estado `anulado`.

Solo el responsable con rol Cajero en la sede activa puede ejecutar estas acciones. La supervisión del gerente/administrador sigue siendo de lectura. La autorización se revalida dentro de la transacción después del bloqueo del usuario y del turno, en ese orden, compartido con la apertura.

`CashOpeningSafety` bloquea cualquier pago asociado, sin filtrar estado, cuenta ni sede; también pagos del responsable sin un corte válido, independientemente de su fecha. Se rechazan totales almacenados distintos de cero, importes inconsistentes, datos de arqueo o cierre registrado. Se inspeccionan estructuras financieras adicionales: se bloquean movimientos vinculados y estructuras cuya relación no pueda identificarse. Si la comprobación falla, la operación no se habilita. No se interpretan estados cancelados como ausencia de movimientos.

Los formularios llevan una revisión HMAC del estado del turno y del último ID de bitácora. El servidor consulta el estado actual bajo bloqueo y rechaza formularios obsoletos; no confía en montos anteriores enviados por el navegador. Esto evita sobrescrituras, anulaciones duplicadas y el problema de volver a un importe anterior después de otras correcciones.

El motivo es obligatorio (5 a 1000 caracteres). Cada cambio y su auditoría se guardan en una misma transacción. Acciones: `modificar_fondo_caja` y `anular_apertura_caja`. La bitácora contiene actor, sede, registro, fecha, valores anteriores y posteriores y motivo. Cualquier fallo en la inserción de auditoría revierte el cambio del turno.

Al anular se conserva el fondo, se marca `estado=anulado` y se registra el instante en `fecha_cierre` como fecha de anulación. No se captura arqueo ni diferencia, ni se realiza un corte financiero. La consulta de vigencia sigue filtrando exclusivamente `abierto`; la nueva apertura usa otro ID. Tanto el turno anulado como sus correcciones permanecen consultables en el historial por sede y responsable.

El texto `si` del caso reportado correspondía a observaciones de apertura: ahora aparece etiquetado y no se elimina. El área gris procedía de tres datos dentro de una cuadrícula de dos columnas con fondo gris: la fecha ocupa toda la fila, sin alterar el CSS global.

### Límites de concurrencia

Se bloquean el usuario, el turno y las consultas de movimientos, con reintentos de deadlock. Todo futuro escritor de pagos debe usar el mismo orden de bloqueo y releer el estado del turno antes de insertar; las pruebas incluyen ese contrato. No existen triggers nuevos ni una protección contra SQL externo que omita este protocolo. Esta entrega no introduce registro de pagos ni cierre financiero.

### Protección del caso real

El turno real #1 solo se consulta en una transacción de lectura para verificar estructura y diagnosticar las observaciones. Se compara su huella, incluida su bitácora, antes y después de las pruebas. No se corrige ni anula automáticamente. Los cambios reales deben realizarlos el Cajero desde el formulario después de revisar el motivo y la confirmación.

### Verificación de la fase 1.1

Suite ampliada: 60 pruebas. SQLite en memoria: 54 correctas, seis omitidas por requerir InnoDB, 377 aserciones. MariaDB/InnoDB en una base aleatoria independiente: 60 correctas, 446 aserciones. Se ejecutan también las 30 pruebas de regresión de la fase 1. No se ejecuta la suite de módulos ajenos.

Las pruebas nuevas cubren montos y motivos inválidos, límites decimales, permisos por rol/sede/propietario, pagos de cualquier estado, pagos huérfanos o sin corte válido, otras estructuras de movimientos, datos de arqueo, confirmación explícita, CSRF, persistencia, historial escapado, auditoría obligatoria y rollback. En procesos separados se prueban correcciones simultáneas, corrección contra anulación, doble anulación, apertura contra anulación y un escritor de pagos de prueba que respeta el protocolo de bloqueo. No se crea un endpoint de pagos.
