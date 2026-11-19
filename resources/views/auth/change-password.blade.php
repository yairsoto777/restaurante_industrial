<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Cambiar contraseña · NUTRILIADO</title>@vite(['resources/css/app.css'])<link rel="stylesheet" href="{{ asset('css/auth-password-change.css') }}"></head>
<body class="auth-page nu-password-change"><main class="auth-shell auth-shell-login">
<section class="auth-visual"><div class="auth-brand"><img src="{{ asset('images/logo.png') }}" alt="Logotipo NUTRILIADO"><strong>NUTRILIADO</strong></div><div class="auth-message"><span class="auth-kicker">SEGURIDAD DE TU CUENTA</span><h1>Tu acceso.<br>Tu contraseña.</h1><p>Define una contraseña personal para continuar a tu espacio de trabajo. Tus roles y sedes se conservan.</p></div><div class="auth-pattern" aria-hidden="true"><i></i><i></i><i></i></div></section>
<section class="auth-form-panel"><div class="auth-form-wrap"><span class="auth-kicker">PRIMER ACCESO SEGURO</span><h2>Cambiar contraseña</h2><p class="nu-password-note">Elige al menos 12 caracteres y una contraseña diferente de la temporal. Este paso es obligatorio antes de entrar al panel.</p>
@if($errors->any())<div class="alert error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('password.change.store') }}" class="auth-form">@csrf
<label>Nueva contraseña<input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
<label>Confirmar contraseña<input type="password" name="password_confirmation" minlength="12" maxlength="72" required autocomplete="new-password"></label>
<button class="auth-primary" type="submit">Guardar contraseña y continuar</button></form>
<form method="POST" action="{{ route('logout') }}" class="nu-password-logout">@csrf<button type="submit">Cerrar sesión</button></form>
</div></section></main></body></html>
