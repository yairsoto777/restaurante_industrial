<!DOCTYPE html>
<html class="public-root" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a0b0e">
    <meta name="description" content="@yield('meta_description', 'NUTRILIADO: gestión de comedores industriales y restaurantes.')">
    <title>@yield('title', 'NUTRILIADO')</title>
    @vite(['resources/css/app.css', 'resources/css/nutriliado-public.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="public-page nutriliado-public">
    <a class="public-skip-link" href="#contenido-principal">Saltar al contenido</a>
    <header class="public-header" data-public-header>
        <div class="public-container public-header-inner">
            <a href="{{ route('home') }}#inicio" class="public-brand" aria-label="NUTRILIADO, ir al inicio">
                <img class="nu-logo" src="{{ asset('images/logo.png') }}" alt="NUTRILIADO" width="210" height="78">
            </a>
            <button class="public-menu-toggle" type="button" aria-controls="public-navigation" aria-expanded="false" aria-label="Abrir menú" data-public-nav-toggle>
                <span></span><span></span><span></span>
            </button>
            <nav class="public-navigation" id="public-navigation" aria-label="Navegación principal" data-public-nav>
                <a href="{{ route('home') }}#inicio">Inicio</a>
                <a href="{{ route('home') }}#vision">Nuestra visión</a>
                <a href="{{ route('home') }}#soluciones">Soluciones</a>
                <a href="{{ route('home') }}#experiencia">Experiencia</a>
                <a href="{{ route('home') }}#beneficios">Beneficios</a>
                <a href="{{ route('home') }}#contacto">Contacto</a>
                <a class="public-login-link" href="{{ route('login') }}">Acceder <span aria-hidden="true">↗</span></a>
            </nav>
        </div>
    </header>

    <main id="contenido-principal">@yield('content')</main>

    <footer class="public-footer">
        <div class="public-container nu-footer-content">
            <a class="public-brand public-brand-footer" href="{{ route('home') }}#inicio" aria-label="Volver al inicio">
                <img class="nu-logo" src="{{ asset('images/logo.png') }}" alt="NUTRILIADO" width="190" height="70" loading="lazy">
            </a>
            <p>Organiza tus sedes, conecta a tu equipo y mejora cada servicio.</p>
            <small>© {{ date('Y') }} NUTRILIADO · Todos los derechos reservados.</small>
        </div>
    </footer>

    {{-- JS exclusivo para el formulario. El menú móvil ya funciona con resources/js/app.js. --}}
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-nu-contact-form]');
        if (!form) return;
        const apellidos = form.querySelector('[name="apellidos"]');
        const empresa = form.querySelector('[name="empresa"]');
        const telefono = form.querySelector('[name="telefono"]');
        const preferencia = form.querySelector('[name="preferencia"]');
        const nombre = form.querySelector('[name="nombre_visible"]');
        const mensaje = form.querySelector('[name="mensaje_visible"]');
        const nombreCompleto = form.querySelector('[name="nombre"]');
        const mensajeCompleto = form.querySelector('[name="mensaje"]');
        const actualizarTelefono = function () {
            const llamada = preferencia.value === 'llamada';
            telefono.required = llamada;
            telefono.closest('label').querySelector('small').textContent = llamada ? 'Obligatorio para solicitar una llamada.' : 'Opcional si prefieres correo.';
        };
        preferencia.addEventListener('change', actualizarTelefono);
        actualizarTelefono();
        form.addEventListener('submit', function () {
            nombreCompleto.value = (nombre.value.trim() + ' ' + apellidos.value.trim()).trim().slice(0, 100);
            mensajeCompleto.value = [
                'Empresa: ' + empresa.value.trim(),
                'Apellidos: ' + apellidos.value.trim(),
                'Medio preferido: ' + (preferencia.value === 'llamada' ? 'Llamada telefónica' : 'Correo electrónico'),
                'Teléfono: ' + (telefono.value.trim() || 'No proporcionado'),
                '',
                'Mensaje:',
                mensaje.value.trim()
            ].join('\n').slice(0, 3000);
        });
    });
    </script>
</body>
</html>
