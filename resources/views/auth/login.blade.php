
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Iniciar sesión · Gestión Industrial</title>

    @vite([
        'resources/css/app.css',
        'resources/css/nutriliado-login.css'
    ])
</head>

<body class="auth-page nu-login-page">

<main class="auth-shell auth-shell-login">

    <!-- VIDEO DE FONDO -->
    <div class="nu-login-video-scene" aria-hidden="true">
        <video
            class="nu-login-cover-video"
            autoplay
            muted
            loop
            playsinline
            preload="metadata"
            disablepictureinpicture
            disableremoteplayback
            controlslist="nodownload noplaybackrate nofullscreen noremoteplayback"
            tabindex="-1"
        >
            <source
                src="{{ asset('videos/login-cocina.mp4') }}"
                type="video/mp4"
            >
        </video>
    </div>

    <!-- CAPA OSCURA -->
    <div class="nu-login-video-overlay" aria-hidden="true"></div>

    <!-- BLOQUE CENTRAL -->
    <div class="nu-login-center">

        <!-- TARJETA IZQUIERDA: CHEF -->
        <figure class="nu-login-chef-card">

            <div class="nu-login-chef-media">

                <img
                    src="https://images.unsplash.com/photo-1761095596765-c8abe01d3aea?auto=format&fit=crop&w=1200&q=85"
                    alt="Chef emplatando cuidadosamente un platillo en una cocina profesional"
                    loading="eager"
                    decoding="async"
                    referrerpolicy="no-referrer"
                >

                <figcaption>

                    <span class="nu-login-chef-kicker">
                        NUTRILIADO / EXCELENCIA OPERATIVA
                    </span>

                    <strong>
                        Cada detalle cuenta.
                    </strong>

                    <span>
                        La mejor experiencia comienza detrás de cada servicio.
                    </span>

                </figcaption>

            </div>

        </figure>

        <!-- TARJETA DERECHA: FORMULARIO -->
        <section
            class="auth-form-panel"
            aria-labelledby="nu-login-heading"
        >

            <div class="auth-form-wrap">

                <span class="auth-kicker">
                    Acceso al sistema
                </span>

                <h2 id="nu-login-heading">
                    Bienvenido
                </h2>

                <p class="auth-subtitle">
                    Ingresa tus credenciales para continuar.
                </p>

                <!-- MENSAJES DE LARAVEL -->
                @if(session('status'))
                    <div class="alert success">
                        {{ session('status') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <!-- FORMULARIO LOGIN -->
                <form
                    method="POST"
                    action="{{ route('login.store') }}"
                    class="auth-form"
                >
                    @csrf

                    <!-- CORREO -->
                    <label>
                        Correo electrónico

                        <input
                            type="email"
                            name="correo"
                            value="{{ old('correo') }}"
                            placeholder="nombre@empresa.com"
                            autocomplete="email"
                            required
                            autofocus
                        >
                    </label>

                    <!-- CONTRASEÑA CON OJITO -->
                    <label class="nu-password-label">

                        Contraseña

                        <span class="nu-password-field">

                            <input
                                type="password"
                                id="nu-login-password"
                                name="password"
                                placeholder="Ingresa tu contraseña"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                type="button"
                                class="nu-password-toggle"
                                id="nu-password-toggle"
                                aria-label="Mostrar contraseña"
                                aria-pressed="false"
                                aria-controls="nu-login-password"
                                title="Mostrar contraseña"
                            >

                                <!-- OJO ABIERTO -->
                                <svg
                                    class="nu-eye-open"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="21"
                                    height="21"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>

                                <!-- OJO CERRADO -->
                                <svg
                                    class="nu-eye-closed"
                                    xmlns="http://www.w3.org/2000/svg"
                                    width="21"
                                    height="21"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                    hidden
                                >
                                    <path d="M3 3l18 18"/>
                                    <path d="M10.6 5.2A10 10 0 0 1 12 5c6.5 0 10 7 10 7a15.6 15.6 0 0 1-3.1 3.8"/>
                                    <path d="M6.1 6.8C3.5 8.6 2 12 2 12s3.5 7 10 7a10 10 0 0 0 4.1-.9"/>
                                    <path d="M10 10a3 3 0 0 0 4 4"/>
                                </svg>

                            </button>

                        </span>

                    </label>

                    <!-- RECUPERAR CONTRASEÑA -->
                    <a
                        class="auth-forgot"
                        href="{{ route('password.forgot') }}"
                    >
                        ¿Olvidaste tu contraseña?
                    </a>

                    <!-- MANTENER SESION -->
                    <label class="checkbox auth-check">

                        <input
                            type="checkbox"
                            name="remember"
                            value="1"
                        >

                        Mantener sesión iniciada

                    </label>

                    <!-- INICIAR SESION -->
                    <button
                        class="auth-primary"
                        type="submit"
                    >
                        Iniciar sesión
                    </button>

                </form>

                <!-- REGRESAR AL INICIO -->
                <div class="auth-switch">
                    <a href="{{ route('home') }}">
                        Volver al inicio
                    </a>
                </div>

            </div>

        </section>

    </div>

</main>

<!-- SCRIPT EXCLUSIVO DEL OJITO -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const passwordInput = document.getElementById('nu-login-password');
    const toggleButton = document.getElementById('nu-password-toggle');

    if (!passwordInput || !toggleButton) {
        return;
    }

    const eyeOpen = toggleButton.querySelector('.nu-eye-open');
    const eyeClosed = toggleButton.querySelector('.nu-eye-closed');

    toggleButton.addEventListener('click', function () {

        const mostrar = passwordInput.type === 'password';

        passwordInput.type = mostrar ? 'text' : 'password';

        eyeOpen.hidden = mostrar;
        eyeClosed.hidden = !mostrar;

        toggleButton.setAttribute(
            'aria-label',
            mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
        );

        toggleButton.setAttribute(
            'title',
            mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña'
        );

        toggleButton.setAttribute(
            'aria-pressed',
            String(mostrar)
        );

    });

});
</script>

</body>
</html>
