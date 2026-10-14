<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña · Gestión Industrial</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
<main class="auth-shell auth-shell-login">
    <section class="auth-visual">
        <div class="auth-brand"><span>GI</span><strong>Gestión Industrial</strong></div>
        <div class="auth-message">
            <span class="auth-kicker">Acceso seguro</span>
            <h1>Recupera el acceso a tu cuenta.</h1>
            <p>Protegemos la operación de restaurantes y comedores industriales con procesos de acceso seguros.</p>
        </div>
        <div class="auth-pattern" aria-hidden="true"><i></i><i></i><i></i></div>
    </section>

    <section class="auth-form-panel">
        <div class="auth-form-wrap">
            <span class="auth-kicker">Seguridad de la cuenta</span>
            <h2>Recuperar contraseña</h2>
            <p class="auth-subtitle">Ingresa el correo asociado a tu cuenta. Te enviaremos una contraseña temporal.</p>

            @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

            <form method="POST" action="{{ route('password.send-temporary') }}" class="auth-form">
                @csrf
                <label>Correo electrónico<input type="email" name="correo" value="{{ old('correo') }}" placeholder="nombre@empresa.com" autocomplete="email" required autofocus></label>
                <button class="auth-primary" type="submit">Enviar contraseña temporal</button>
            </form>

            <div class="auth-switch"><a href="{{ route('login') }}">Volver al inicio de sesión</a></div>
        </div>
    </section>
</main>
</body>
</html>
