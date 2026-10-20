<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar cuenta · Gestión Industrial</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
<main class="auth-shell auth-shell-login">
    <section class="auth-visual"><div class="auth-brand"><span>GI</span><strong>Gestión Industrial</strong></div><div class="auth-message"><span class="auth-kicker">Acceso seguro</span><h1>Activa tu cuenta.</h1><p>Establece una contraseña personal para comenzar a administrar tu comedor.</p></div><div class="auth-pattern" aria-hidden="true"><i></i><i></i><i></i></div></section>
    <section class="auth-form-panel"><div class="auth-form-wrap">
        <span class="auth-kicker">Primer acceso</span><h2>Establecer contraseña</h2><p class="auth-subtitle">Crea una contraseña segura para tu cuenta de gerente.</p>
        @if($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.activation.store') }}" class="auth-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label>Correo electrónico<input type="email" name="correo" value="{{ old('correo', $correo) }}" required autocomplete="email"></label>
            <label>Contraseña nueva<span style="display:block;position:relative"><input id="activation-password" type="password" name="password" minlength="12" required autocomplete="new-password" style="padding-right:42px"><button type="button" class="button compact" data-toggle-password="activation-password" aria-label="Mostrar contraseña" aria-pressed="false" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);padding:4px 7px;line-height:1">◉</button></span></label>
            <label>Confirmar contraseña<span style="display:block;position:relative"><input id="activation-password-confirmation" type="password" name="password_confirmation" minlength="12" required autocomplete="new-password" style="padding-right:42px"><button type="button" class="button compact" data-toggle-password="activation-password-confirmation" aria-label="Mostrar contraseña" aria-pressed="false" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);padding:4px 7px;line-height:1">◉</button></span></label>
            <button class="auth-primary" type="submit">Activar cuenta</button>
        </form>
    </div></section>
</main>
<script>
document.querySelectorAll('[data-toggle-password]').forEach((button) => button.addEventListener('click', (event) => {
    const toggle = event.currentTarget;
    const input = document.getElementById(toggle.dataset.togglePassword);
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    toggle.textContent = visible ? '○' : '◉';
    toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    toggle.setAttribute('aria-pressed', String(visible));
}));
</script>
</body>
</html>
