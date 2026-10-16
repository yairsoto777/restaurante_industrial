<!DOCTYPE html>
<html class="public-root" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#071c3c">
    <meta name="description" content="@yield('meta_description', 'Plataforma de gestión integral para comedores industriales y operaciones alimentarias.')">
    <title>@yield('title', 'Gestión integral para comedores industriales') · {{ config('app.name', 'Gestión Industrial') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="public-page">
    <a class="public-skip-link" href="#contenido-principal">Saltar al contenido</a>

    <header class="public-header" data-public-header>
        <div class="public-container public-header-inner">
            <a class="public-brand" href="{{ route('home') }}#inicio" aria-label="Gestión Industrial, ir al inicio">
                <span class="public-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none">
                        <path d="M8 23V13M16 23V7M24 23V11" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        <path d="M5 25.5h22" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".65"/>
                    </svg>
                </span>
                <span class="public-brand-copy">
                    <strong>Gestión Industrial</strong>
                    <small>Operación alimentaria</small>
                </span>
            </a>

            <button class="public-menu-toggle" type="button" aria-expanded="false" aria-controls="public-navigation" aria-label="Abrir menú" data-public-nav-toggle>
                <span></span><span></span><span></span>
            </button>

            <nav class="public-navigation" id="public-navigation" aria-label="Navegación principal" data-public-nav>
                <a href="{{ route('home') }}#inicio">Inicio</a>
                <a href="{{ route('home') }}#soluciones">Soluciones</a>
                <a href="{{ route('home') }}#beneficios">Beneficios</a>
                <a href="{{ route('home') }}#como-funciona">Cómo funciona</a>
                <a href="{{ route('home') }}#contacto">Contacto</a>
                <a class="public-login-link" href="{{ route('login') }}">Iniciar sesión</a>
            </nav>
        </div>
    </header>

    <main id="contenido-principal">
        @yield('content')
    </main>

    <footer class="public-footer">
        <div class="public-container public-footer-grid">
            <div>
                <a class="public-brand public-brand-footer" href="{{ route('home') }}#inicio">
                    <span class="public-brand-mark" aria-hidden="true">
                        <svg viewBox="0 0 32 32" fill="none">
                            <path d="M8 23V13M16 23V7M24 23V11" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            <path d="M5 25.5h22" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".65"/>
                        </svg>
                    </span>
                    <span class="public-brand-copy"><strong>Gestión Industrial</strong><small>Operación alimentaria</small></span>
                </a>
                <p>Una base digital para coordinar sedes, equipos y servicios de alimentación.</p>
            </div>
            <div class="public-footer-links" aria-label="Enlaces del pie de página">
                <a href="{{ route('home') }}#soluciones">Soluciones</a>
                <a href="{{ route('home') }}#contacto">Solicitar información</a>
                <a href="{{ route('login') }}">Acceso a clientes</a>
            </div>
        </div>
        <div class="public-container public-footer-bottom">
            <span>© {{ date('Y') }} {{ config('app.name', 'Gestión Industrial') }}.</span>
            <span>Plataforma para operaciones alimentarias.</span>
        </div>
    </footer>
</body>
</html>
