<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel principal') · Gestión Industrial</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-body">
@php
    $authenticatedUser = auth()->user();
    $isCommonUser = $authenticatedUser->isCommonUser();
    $isManager = $authenticatedUser->isManager();
    $canAdminister = $authenticatedUser->canAdminister();
@endphp
<div class="app-shell" data-app-shell>
    <aside class="app-sidebar" id="app-sidebar">
        <div class="sidebar-brand"><span>GI</span><div><strong>Gestión Industrial</strong><small>Operación alimentaria</small></div></div>
        <nav class="sidebar-nav" aria-label="Navegación principal">
            <div class="nav-section"><span>Inicio</span>
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><x-icon name="home"/><b>Panel principal</b></a>
            </div>

            @if($isCommonUser)
            <div class="nav-section"><span>Oportunidades</span>
                <a class="nav-link {{ request()->routeIs('user.services.*') ? 'active' : '' }}" href="{{ route('user.services.index') }}"><x-icon name="calendar"/><b>Servicios disponibles</b></a>
                <a class="nav-link {{ request()->routeIs('user.applications.create') ? 'active' : '' }}" href="{{ route('user.applications.create') }}"><x-icon name="group"/><b>Solicitar puesto</b></a>
                <a class="nav-link {{ request()->routeIs('user.applications.index') ? 'active' : '' }}" href="{{ route('user.applications.index') }}"><x-icon name="layers"/><b>Mis solicitudes</b></a>
            </div>
            @else
            <div class="nav-section"><span>Administración</span>
                <a class="nav-link {{ request()->routeIs('sites.select*', 'manager.sites') ? 'active' : '' }}" href="{{ route('sites.select') }}"><x-icon name="building"/><b>Selección de sede</b></a>
                @if($canAdminister)
                    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><x-icon name="users"/><b>Usuarios y permisos</b></a>
                    <a class="nav-link {{ request()->routeIs('admin.sites.*') ? 'active' : '' }}" href="{{ route('admin.sites.index') }}"><x-icon name="building"/><b>Gestión de sedes</b></a>
                    @if($authenticatedUser->isSuperAdministrator())
                        <a class="nav-link {{ request()->routeIs('admin.contacts.*') ? 'active' : '' }}" href="{{ route('admin.contacts.index') }}"><x-icon name="layers"/><b>Contactos</b></a>
                    @endif
                @endif
            </div>
            @endif

            @if($isManager)
            <div class="nav-section"><span>Personal</span>
                <a class="nav-link {{ request()->routeIs('manager.employees.*') ? 'active' : '' }}" href="{{ route('manager.employees.index') }}"><x-icon name="users"/><b>Empleados</b></a>
            </div>
            @endif

            @if($canAdminister || $isManager)
            <div class="nav-section"><span>Recepción / Inventario</span>
                <a class="nav-link {{ request()->routeIs('receptions.*') ? 'active' : '' }}" href="{{ route('receptions.index') }}"><x-icon name="box"/><b>Recepción de insumos</b></a>
                @if($canAdminister)<a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}"><x-icon name="warehouse"/><b>Inventario de insumos</b></a>@endif
            </div>
            <div class="nav-section"><span>Servicios</span>
                <a class="nav-link {{ request()->routeIs('services.index') ? 'active' : '' }}" href="{{ route('services.index') }}"><x-icon name="calendar"/><b>Gestión de servicios</b></a>
                <a class="nav-link {{ request()->routeIs('services.live') ? 'active' : '' }}" href="{{ route('services.live') }}"><x-icon name="activity"/><b>Servicio en vivo</b></a>
            </div>
            @endif

            @if($canAdminister)
            <div class="nav-section"><span>Operación</span>
                <a class="nav-link {{ request()->routeIs('tables.*') ? 'active' : '' }}" href="{{ route('tables.index') }}"><x-icon name="map"/><b>Mesas y zonas</b></a>
                <a class="nav-link {{ request()->routeIs('groups.*') ? 'active' : '' }}" href="{{ route('groups.index') }}"><x-icon name="group"/><b>Grupos y entregas</b></a>
            </div>
            <div class="nav-section"><span>Menú</span>
                <a class="nav-link {{ request()->routeIs('menus.*') ? 'active' : '' }}" href="{{ route('menus.index') }}"><x-icon name="utensils"/><b>Menú por servicio</b></a>
                <a class="nav-link {{ request()->routeIs('dishes.*') ? 'active' : '' }}" href="{{ route('dishes.index') }}"><x-icon name="dish"/><b>Platillos</b></a>
            </div>
            <div class="nav-section"><span>Control</span>
                <a class="nav-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}" href="{{ route('incidents.index') }}"><x-icon name="alert"/><b>Incidencias</b></a>
            </div>
            @endif
            @if($canAdminister || $isManager)
            <div class="nav-section"><span>Reportes</span>
                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><x-icon name="chart"/><b>Reportes</b></a>
            </div>
            @endif
        </nav>
        <div class="sidebar-footer"><span class="system-dot"></span><div><strong>Sistema operativo</strong><small>Conexión activa</small></div></div>
    </aside>

    <div class="sidebar-overlay" data-sidebar-close></div>
    <div class="app-main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-label="Abrir menú"><span></span><span></span><span></span></button>
                @if($isCommonUser)
                <div class="site-context-note"><x-icon name="group"/><div><small>Estado de cuenta</small><strong>Sin puesto operativo asignado</strong></div></div>
                @else
                <form method="POST" action="{{ route('sites.select.update') }}" class="site-selector">@csrf
                    <x-icon name="building"/><div><small>Sede actual</small>
                    <select name="id_sede" onchange="this.form.submit()" aria-label="Seleccionar sede" {{ $availableSites->isEmpty() ? 'disabled' : '' }}>
                        @forelse($availableSites as $site)<option value="{{ $site->id_sede }}" @selected($currentSite?->id_sede === $site->id_sede)>{{ $site->nombre }}</option>@empty<option>Sin sedes disponibles</option>@endforelse
                    </select></div>
                </form>
                @endif
            </div>
            <div class="topbar-right">
                <button class="notification-button" type="button" aria-label="Notificaciones"><x-icon name="bell"/></button>
                <details class="user-menu">
                    <summary><span class="avatar">{{ mb_strtoupper(mb_substr(auth()->user()->nombre ?: 'U', 0, 1)) }}{{ mb_strtoupper(mb_substr(auth()->user()->apellido_p ?: '', 0, 1)) }}</span><span class="user-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></span><x-icon name="chevron"/></summary>
                    <div class="user-dropdown"><div><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->correo }}</span></div><hr><span class="disabled-link">Perfil próximamente</span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Cerrar sesión</button></form></div>
                </details>
            </div>
        </header>
        <main class="page-content">
            @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="alert error"><strong>Revisa la información:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
