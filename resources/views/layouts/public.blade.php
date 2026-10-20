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

</body>
</html>
