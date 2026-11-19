# Activación de cuentas generales

El panel Usuarios del sistema permite elegir Enviar enlace de activación o Asignar contraseña temporal para la cuenta existente. Se conservan sus identificadores, roles y sedes. Las cuentas exclusivamente operativas siguen a cargo de su gerente.

## Contraseña temporal

- El administrador puede escribir una contraseña de al menos 12 caracteres, con límite de 72 bytes compatible con bcrypt, o pulsar Generar contraseña segura para que el servidor cree una aleatoria de 20 caracteres al confirmar.
- Se almacena únicamente el hash de Laravel. No se envía correo por este método, no se registra la contraseña ni se guarda en la sesión o en los datos de validación.
- La respuesta de confirmación muestra la contraseña para entrega privada. Usa `Cache-Control: no-store`; no existe una consulta posterior de la contraseña y se limpia el campo al abandonar la página.
- Caduca en 30 minutos. Invalida los tokens de activación/recuperación anteriores y rota el token de «recordarme» del usuario afectado.
- Las sesiones de cuentas que usan el ciclo de activación se vinculan mediante HMAC al hash de contraseña. Una sesión con credenciales anteriores o sin esta vinculación debe autenticarse nuevamente. Esto funciona con el almacenamiento de sesiones en archivos actual, sin escanear ni modificar sesiones de otros usuarios.
- El middleware bloquea el acceso a cualquier otra ruta mientras falta definir la contraseña definitiva, incluidos el selector y los cambios de rol. Permite Cambiar contraseña y Cerrar sesión.
- La contraseña definitiva debe ser diferente de la temporal; se guarda con hash, se marca la temporal como utilizada, se eliminan tokens pendientes y se renueva la sesión actual. Las demás sesiones con el hash anterior se rechazan en su siguiente petición.

## Enlaces existentes

Se conserva el broker de Laravel, la caducidad configurada y el correo de activación existente. Si el correo está en modo `log`, el enlace continúa en el registro local y no se entrega a bandejas. Esta limitación no afecta al método de contraseña temporal.

## Demostración de Marea de Coral

1. Entrar como Superadministrador → Usuarios del sistema.
2. Buscar `gerente.marea@example.com` y pulsar Activar cuenta.
3. Verificar Alejandro Ramírez Torres y la sede Marea de Coral Centro.
4. Elegir Asignar contraseña temporal, escribir una propia o pulsar Generar contraseña segura y luego Confirmar activación.
5. Entregar la contraseña mostrada y el correo al usuario por un medio privado. Usarla dentro de 30 minutos.
6. El primer inicio de sesión llevará a Cambiar contraseña antes de permitir el acceso al rol.

La implementación y las pruebas no activaron ni modificaron esa cuenta real.

## Archivos y comprobación

- `SuperadminWorkspaceController`: selector de método, validación y asignación segura a la cuenta seleccionada.
- `AuthController`, `PasswordSession`, `EnsurePasswordSessionIsCurrent` y `bootstrap/app.php`: credenciales, vinculación de sesiones, expiración y cambio obligatorio.
- `EnsureUserIsActive`: permite el selector de roles propios después de terminar la activación, conservando la validación de asignaciones.
- Vistas `superadmin/partials/activation-form`, `superadmin/activation-result`, `superadmin/sections/usuarios` y `auth/change-password`; CSS de Superadministrador y cambio de contraseña; JavaScript de Superadministrador.
- `tests/Feature/SuperadminTemporaryActivationTest.php`: dos pruebas aisladas en SQLite en memoria sin migraciones. Cubren hash, generación, caducidad, validación sin guardar secretos en sesión, rotación de tokens/sesiones, cambio obligatorio, multirrol, enlaces y permisos.

No se ejecutaron migraciones, commit ni push. Se reutilizan `temporary_password_expires_at` y `temporary_password_used_at`.
