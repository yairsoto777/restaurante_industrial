<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Solicitud aprobada | Gestión Industrial</title></head>
<body style="margin:0;background:#eef3f9;font-family:Arial,sans-serif;color:#17243d">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef3f9;padding:32px 16px"><tr><td align="center">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden">
<tr><td style="padding:24px 32px;background:#092a5a;color:#fff;font-size:20px;font-weight:bold">Gestión Industrial</td></tr>
<tr><td style="padding:34px 32px">
<h1 style="margin:0 0 20px;font-size:25px;color:#101e37">¡Tu solicitud ha sido aprobada!</h1>
<p>Hola {{ $contact->nombre }},</p>
<p>Tu solicitud para formar parte de <strong>Gestión Industrial</strong> fue aprobada por nuestro equipo administrativo.</p>
<p>Tu cuenta de gerente se creó correctamente.</p>
<p><strong>Información de tu cuenta:</strong></p>
<p style="line-height:1.7;margin-bottom:24px">Nombre: {{ $contact->fullName() }}<br>Empresa: {{ $contact->empresa ?: 'No registrada' }}<br>Correo electrónico: {{ $contact->correo }}<br>Rol: Gerente de sede</p>
<p><strong>Contraseña temporal</strong></p>
<p style="margin:24px 0;padding:17px;background:#edf5ff;border-left:4px solid #1681ff;border-radius:7px;font-family:Consolas,monospace;font-size:22px;font-weight:bold;letter-spacing:2px;text-align:center">{{ $temporaryPassword }}</p>
<p>Esta contraseña es válida durante 30 minutos y solo permite un primer acceso. Después de iniciar sesión, establece tu contraseña personal.</p>
<p style="margin-bottom:0;color:#64728a">Si tú no solicitaste esta cuenta, puedes ignorar este correo o contactar al administrador.</p>
</td></tr></table>
</td></tr></table>
</body>
</html>
