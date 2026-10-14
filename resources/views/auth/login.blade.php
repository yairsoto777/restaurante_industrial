<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · Gestión Industrial</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
<main class="auth-shell auth-shell-login">
    <section class="auth-visual">
        <div class="auth-brand"><span>GI</span><strong>Gestión Industrial</strong></div>
        <div class="auth-message">
            <span class="auth-kicker">Operación centralizada</span>
            <h1>Control inteligente para restaurantes y comedores industriales.</h1>
            <p>Administra usuarios, sedes y operaciones desde una plataforma segura y confiable.</p>
        </div>
        <div class="auth-pattern" aria-hidden="true"><i></i><i></i><i></i></div>
    </section>

    <section class="auth-form-panel">
        <div class="auth-form-wrap">
            <span class="auth-kicker">Acceso al sistema</span>
            <h2>Bienvenido</h2>
            <p class="auth-subtitle">Ingresa tus credenciales para continuar.</p>

            @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif

            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf
                <label>Correo electrónico<input type="email" name="correo" value="{{ old('correo') }}" placeholder="nombre@empresa.com" autocomplete="email" required autofocus></label>
                <label>Contraseña<input type="password" name="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" required></label>
                <label class="checkbox auth-check"><input type="checkbox" name="remember" value="1"> Mantener sesión iniciada</label>
                <button class="auth-primary" type="submit">Iniciar sesión</button>
            </form>

            <div class="auth-switch"><span>¿No tienes una cuenta?</span><a href="{{ route('register') }}">Registrarse</a></div>
        </div>
    </section>
</main>
</body>
</html>
