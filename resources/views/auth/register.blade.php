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
            <span class="auth-kicker">Una plataforma, múltiples sedes</span>
            <h1>Conecta a tu equipo con cada operación.</h1>
            <p>Gestiona el acceso a restaurantes y comedores industriales con roles y sedes claramente definidos.</p>
        </div>
        <div class="auth-feature"><strong>Administración segura</strong><span>Usuarios vinculados a su rol y sede desde el primer acceso.</span></div>
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

            @if($roles->isEmpty() || $sites->isEmpty())
                <div class="alert warning">El registro requiere al menos un rol permitido y una sede disponibles.</div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="auth-form auth-form-grid">
                @csrf
                <label>Nombre<input name="nombre" value="{{ old('nombre') }}" maxlength="100" required></label>
                <label>Apellido paterno<input name="apellido_p" value="{{ old('apellido_p') }}" maxlength="100" required></label>
                <label>Apellido materno<input name="apellido_m" value="{{ old('apellido_m') }}" maxlength="100"></label>
                <label>Correo electrónico<input type="email" name="correo" value="{{ old('correo') }}" maxlength="150" autocomplete="email" required></label>
                <label>Contraseña<input type="password" name="password" minlength="8" autocomplete="new-password" required><small>Mínimo 8 caracteres.</small></label>
                <label>Confirmar contraseña<input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required></label>
                <label>Rol<select name="id_rol" required><option value="">Selecciona un rol</option>@foreach($roles as $role)<option value="{{ $role->id_rol }}" @selected((string) old('id_rol') === (string) $role->id_rol)>{{ $role->nombre }}</option>@endforeach</select></label>
                <label>Sede<select name="id_sede" required><option value="">Selecciona una sede</option>@foreach($sites as $site)<option value="{{ $site->id_sede }}" @selected((string) old('id_sede') === (string) $site->id_sede)>{{ $site->nombre }}</option>@endforeach</select></label>
                <label class="auth-full">Estado<select disabled><option>Activo</option><option>Inactivo</option></select><small>La tabla usuarios no cuenta actualmente con una columna de estado.</small></label>
                <button class="auth-primary auth-full" type="submit" @disabled($roles->isEmpty() || $sites->isEmpty())>Crear cuenta</button>
            </form>

            <div class="auth-switch"><span>¿Ya tienes una cuenta?</span><a href="{{ route('login') }}">Iniciar sesión</a></div>
        </div>
    </section>
</main>
</body>
</html>
