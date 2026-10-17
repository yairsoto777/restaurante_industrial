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
            <source src="{{ asset('videos/login-cocina.mp4') }}" type="video/mp4">
        </video>
    </div>

    <div class="nu-login-video-overlay" aria-hidden="true"></div>

    <!-- BLOQUE CENTRAL -->
    <div class="nu-login-center">

        <!-- TARJETA IMAGEN CHEF -->
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
                    <span class="nu-login-chef-kicker">NUTRILIADO / EXCELENCIA OPERATIVA</span>
                    <strong>Cada detalle cuenta.</strong>
                    <span>La mejor experiencia comienza detrás de cada servicio.</span>
                </figcaption>
            </div>
        </figure>

        <!-- TARJETA FORMULARIO -->
        <section class="auth-form-panel" aria-labelledby="nu-login-heading">
            <div class="auth-form-wrap">
                <span class="auth-kicker">Acceso al sistema</span>

                <h2 id="nu-login-heading">Bienvenido</h2>

                <p class="auth-subtitle">
                    Ingresa tus credenciales para continuar.
                </p>

                @if(session('status'))
                    <div class="alert success">{{ session('status') }}</div>
                @endif

                @if($errors->any())
                    <div class="alert error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                    @csrf

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

                    <label>
                        Contraseña
                        <input
                            type="password"
                            name="password"
                            placeholder="Ingresa tu contraseña"
                            autocomplete="current-password"
                            required
                        >
                    </label>

                    <a class="auth-forgot" href="{{ route('password.forgot') }}">
                        ¿Olvidaste tu contraseña?
                    </a>

                    <label class="checkbox auth-check">
                        <input type="checkbox" name="remember" value="1">
                        Mantener sesión iniciada
                    </label>

                    <button class="auth-primary" type="submit">
                        Iniciar sesión
                    </button>
                </form>

                <div class="auth-switch">
                    <a href="{{ route('home') }}">Volver al inicio</a>
                </div>
            </div>
        </section>

    </div>
</main>

</body>
</html>