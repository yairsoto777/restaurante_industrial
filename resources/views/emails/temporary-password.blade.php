<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Recuperación de contraseña</title></head>
<body style="margin:0;background:#eef3f9;font-family:Arial,sans-serif;color:#17243d">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef3f9;padding:32px 16px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden">
            <tr><td style="padding:24px 32px;background:#092a5a;color:#fff;font-size:20px;font-weight:bold">Gestión Industrial</td></tr>
            <tr><td style="padding:34px 32px">
                <h1 style="margin:0 0 20px;font-size:25px;color:#101e37">Recuperación de acceso</h1>
                <p>Hola {{ $userName }},</p>
                <p>Recibimos una solicitud para recuperar el acceso a tu cuenta de Gestión Industrial.</p>
                <p>Tu contraseña temporal es:</p>
                <p style="margin:24px 0;padding:17px;background:#edf5ff;border-left:4px solid #1681ff;border-radius:7px;font-family:Consolas,monospace;font-size:22px;font-weight:bold;letter-spacing:2px;text-align:center">{{ $temporaryPassword }}</p>
                <p>Esta contraseña es válida durante 30 minutos y solamente puede utilizarse una vez. Inicia sesión y establece una contraseña personal.</p>
                <p style="margin-bottom:0;color:#64728a">Si tú no solicitaste este cambio, contacta al administrador.</p>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
