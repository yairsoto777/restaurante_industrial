<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta · Gestión Industrial</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
<main class="auth-shell auth-shell-register">
    <section class="auth-visual">
        <div class="auth-brand"><span>GI</span><strong>Gestión Industrial</strong></div>
        <div class="auth-message">
            <span class="auth-kicker">Tu siguiente oportunidad</span>
            <h1>Crea tu cuenta y conoce nuestros puestos.</h1>
            <p>Consulta los servicios y vacantes disponibles antes de solicitar un puesto en la sede que prefieras.</p>
        </div>
        <div class="auth-feature"><strong>Registro sencillo</strong><span>Tu sede y puesto se asignarán únicamente cuando una solicitud sea aprobada.</span></div>
        <div class="auth-pattern" aria-hidden="true"><i></i><i></i><i></i></div>
    </section>

    <section class="auth-form-panel">
        <div class="auth-form-wrap auth-form-wide">
            <span class="auth-kicker">Nuevo acceso</span>
            <h2>Crear cuenta</h2>
            <p class="auth-subtitle">Registra un nuevo usuario para acceder al sistema.</p>

            @if($errors->any())
                <div class="alert error"><strong>Revisa la información:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="auth-form auth-form-grid">
                @csrf
                <label>Nombre<input name="nombre" value="{{ old('nombre') }}" maxlength="100" autocomplete="given-name" required></label>
                <label>Apellido paterno<input name="apellido_p" value="{{ old('apellido_p') }}" maxlength="100" autocomplete="family-name" required></label>
                <label><span>Apellido materno <small>(opcional)</small></span><input name="apellido_m" value="{{ old('apellido_m') }}" maxlength="100" autocomplete="additional-name"></label>
                <label>Correo electrónico<input type="email" name="correo" value="{{ old('correo') }}" maxlength="150" autocomplete="email" required></label>
                <label>Contraseña<input type="password" name="password" minlength="8" autocomplete="new-password" required><small>Mínimo 8 caracteres.</small></label>
                <label>Confirmar contraseña<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required><small>Repite la contraseña.</small></label>
                <button class="auth-primary auth-full" type="submit">Crear cuenta</button>
            </form>

            <div class="auth-switch"><span>¿Ya tienes una cuenta?</span><a href="{{ route('login') }}">Iniciar sesión</a></div>
        </div>
    </section>
</main>
</body>
</html>
