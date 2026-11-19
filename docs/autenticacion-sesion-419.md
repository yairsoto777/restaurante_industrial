# Corrección de 419 al cambiar de cuenta

El cierre de sesión ya ejecutaba correctamente `Auth::logout()`, `invalidate()` y `regenerateToken()`. Los formularios incluían `@csrf`. El fallo reproducido es enviar un formulario de login abierto antes de esa invalidación: su token pertenece a la sesión anterior y Laravel lo rechaza correctamente con 419.

Faltaba impedir la caché de las respuestas de autenticación y renovar el token de formularios antiguos, restaurados desde el historial o abiertos en otra pestaña. Además, esta instalación tiene `APP_NAME=Laravel`, sin `SESSION_COOKIE` explícita y con `SESSION_PATH=/`: el nombre predeterminado `laravel-session` era compartible con otros proyectos Laravel de localhost. La colisión es un riesgo de configuración identificado, no un conflicto entre aplicaciones observado durante la prueba.

## Cambios

- Middleware de `no-store`, `no-cache` y revalidación para autenticación y respuestas autenticadas; no cambia diseños ni permisos.
- El login conserva `@csrf`. Antes del POST, su script consulta el mismo GET `/login` con `Accept: application/json`, credenciales del mismo origen y sin caché. Solo recibe el token de la sesión vigente. No envía correo ni contraseña en ese GET. El POST sigue atravesando la validación CSRF normal.
- Si el navegador restaura el login mediante su caché de navegación (`pageshow.persisted`), se solicita de nuevo la página. Si falla la actualización del token, no se envían credenciales con un token antiguo: se vuelve al formulario.
- Cookie predeterminada exclusiva `nutriliado_session`; se respetan las configuraciones explícitas de cookie, dominio, ruta, HTTPS, HttpOnly y SameSite. La instalación actual usa sesiones en archivos, dominio del host, ruta `/`, HttpOnly y SameSite Lax. No se debilitaron esas opciones. El cambio de nombre inicia automáticamente una sesión independiente de la cookie genérica anterior; no exige eliminar cookies manualmente.
- Logout conserva invalidación y regeneración de token y responde con 303 hacia GET `/login`. El inicio conserva regeneración de sesión, limpieza de sede/rol anterior, selector multirrol y cambio obligatorio de contraseña.

## Verificación indispensable, aislada

`php artisan test tests/Feature/AuthenticationSessionTest.php`: 2 pruebas, 214 aserciones correctas. SQLite `:memory:`, sin migraciones y con sesiones en un directorio temporal exclusivo. La comprobación activa la validación CSRF real, que Laravel normalmente omite durante PHPUnit.

Se comprobaron los nueve roles operativos (Administrador, Gerente, Recepción, Personal de cocina, Mesero, Capitán, Cajero, Jefe de cocina y Jefe de almacén), cambio de cuenta sin borrar cookies, cambio del ID y token al cerrar sesión, rechazo del token anterior, reingreso con token actualizado, limpieza de sede/rol y selector multirrol. Login y logout sin CSRF válido siguen siendo rechazados.

`node tests/Browser/auth-session.mjs`: comprobación aislada de los eventos del formulario, actualización antes del POST, prevención de doble envío, restauración desde historial y fallo de red sin enviar credenciales. No se inició un navegador ni se accedió a cuentas reales.

Sin cambios de base de datos, migraciones, diseños, permisos o lógica de los roles; sin commit ni push.
