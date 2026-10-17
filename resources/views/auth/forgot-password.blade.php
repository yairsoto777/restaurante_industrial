
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recuperar contraseña · Gestión Industrial</title>

    @vite([
        'resources/css/app.css',
        'resources/css/nutriliado-forgot.css'
    ])
</head>

<body class="auth-page nu-forgot-page">

<main class="auth-shell auth-shell-login">

    <!-- VIDEO DE FONDO -->
    <div class="nf-video-scene" aria-hidden="true">
        <video
            class="nf-video"
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

    <!-- CAPA OSCURA SOBRE EL VIDEO -->
    <div class="nf-video-overlay" aria-hidden="true"></div>

    <!-- DOS TARJETAS CENTRADAS -->
    <div class="nf-layout">

        <!-- TARJETA IZQUIERDA: CANDADO DIGITAL -->
        <figure class="nf-chef-card">

            <img
                src="https://cdn.prod.website-files.com/67f792d86539c03c1cbbca3e/6821bef670bffd9d34ef8790_67dbe0c71ee3963a8ebe21c1_cybersecurity-concept-illustration.jpeg"
                alt="Candado digital azul luminoso que representa la seguridad y recuperación de contraseñas de NUTRILIADO"
                loading="eager"
                decoding="async"
                style="object-fit: cover; object-position: center;"
            >

            <!-- TEXTO SOBRE LA IMAGEN -->
            <figcaption>

                <span class="nf-eyebrow">
                    NUTRILIADO / ACCESO SEGURO
                </span>

                <strong>
                    Recupera tu acceso con confianza.
                </strong>

                <span>
                    Tu información protegida.
                    Tu operación siempre conectada.
                </span>

            </figcaption>

        </figure>

        <!-- TARJETA DERECHA: FORMULARIO -->
        <section class="auth-form-panel nf-form-panel">

            <div class="auth-form-wrap nf-form-box">

                <span class="auth-kicker">
                    Seguridad de la cuenta
                </span>

                <h2>
                    Recuperar contraseña
                </h2>

                <p class="auth-subtitle">
                    Ingresa el correo asociado a tu cuenta.
                    Te enviaremos una contraseña temporal.
                </p>

                <!-- MENSAJE DE CONFIRMACION -->
                @if(session('status'))
                    <div class="alert success">
                        {{ session('status') }}
                    </div>
                @endif

                <!-- MENSAJES DE ERROR -->
                @if($errors->any())
                    <div class="alert error">
                        {{ $errors->first() }}
                    </div>
                @endif

                <!-- FORMULARIO ORIGINAL -->
                <form
                    method="POST"
                    action="{{ route('password.send-temporary') }}"
                    class="auth-form"
                >
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

                    <button
                        class="auth-primary"
                        type="submit"
                    >
                        Enviar contraseña temporal
                    </button>

                </form>

                <!-- VOLVER AL LOGIN -->
                <div class="auth-switch">

                    <a href="{{ route('login') }}">
                        Volver al inicio de sesión
                    </a>

                </div>

            </div>

        </section>

    </div>

</main>

</body>
</html>
