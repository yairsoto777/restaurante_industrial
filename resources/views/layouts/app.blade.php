<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel') · Restaurante Industrial</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span>RI</span><strong>Restaurante<br>Industrial</strong></a>
        <nav>
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
            @if(auth()->user()->isAdministrator())
                <p>Administración</p>
                <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Usuarios</a>
                <a class="{{ request()->routeIs('admin.sites.*') ? 'active' : '' }}" href="{{ route('admin.sites.index') }}">Sedes</a>
                <a class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}" href="{{ route('admin.settings') }}">Configuración</a>
            @else
                <p>Operación</p>
                <a class="{{ request()->routeIs('manager.sites') ? 'active' : '' }}" href="{{ route('manager.sites') }}">Mis sedes</a>
                @foreach(['recepcion' => 'Recepción', 'inventario' => 'Inventario', 'servicios' => 'Servicios', 'menu' => 'Menú', 'incidencias' => 'Incidencias', 'reportes' => 'Reportes'] as $key => $label)
                    <a class="{{ request()->route('module') === $key ? 'active' : '' }}" href="{{ route('manager.module', $key) }}">{{ $label }}</a>
                @endforeach
            @endif
        </nav>
        <div class="profile">
            <div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button title="Cerrar sesión">Salir</button></form>
        </div>
    </aside>
    <main>
        <header><div><small>Panel de control</small><h1>@yield('title')</h1></div><span class="role">{{ auth()->user()->roleLabel() }}</span></header>
        @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert error"><strong>Revisa la información:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
