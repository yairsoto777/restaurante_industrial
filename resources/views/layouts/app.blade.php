
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'Panel principal') · Gestión Industrial
    </title>

    {{-- ESTILOS Y JAVASCRIPT GENERALES --}}
    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    {{-- ESTILOS PARTICULARES DE CADA VISTA --}}
    @stack('styles')

</head>

<body class="app-body">

@php
    /*
    |--------------------------------------------------------------------------
    | USUARIO AUTENTICADO Y PERMISOS
    |--------------------------------------------------------------------------
    */

    $authenticatedUser = auth()->user();

    $isCommonUser = $authenticatedUser->isCommonUser();

    $isManager = $authenticatedUser->isManager();

    $isSuperAdministrator =
        $authenticatedUser->isSuperAdministrator();

    $canAdminister =
        $authenticatedUser->canAdminister();

    $isKitchen =
        $authenticatedUser->hasRole('Personal de cocina') ||
        $authenticatedUser->hasRole('Jefe de cocina');

    $isWarehouse =
        $authenticatedUser->hasRole('Recepcion de insumos') ||
        $authenticatedUser->hasRole('Jefe de almacen');

    $isService =
        $authenticatedUser->hasRole('Mesero') ||
        $authenticatedUser->hasRole('Capitan de meseros');

    $isCashier =
        $authenticatedUser->hasRole('Cajero');

    /*
    |--------------------------------------------------------------------------
    | CAPITÁN DE MESEROS
    |--------------------------------------------------------------------------
    */

    $isCaptain =
        $authenticatedUser->hasRole('Capitan de meseros');
@endphp


<div class="app-shell" data-app-shell>

    {{-- ======================================================
         BARRA LATERAL
    ======================================================= --}}

    <aside class="app-sidebar" id="app-sidebar">

        {{-- IDENTIDAD DEL SISTEMA --}}

        <div class="sidebar-brand">

            <span>GI</span>

            <div>
                <strong>Gestión Industrial</strong>
                <small>Operación alimentaria</small>
            </div>

        </div>


        {{-- ==================================================
             NAVEGACIÓN PRINCIPAL
        =================================================== --}}

        <nav
            class="sidebar-nav"
            aria-label="Navegación principal"
        >

            {{-- ==============================================
                 INICIO
            =============================================== --}}

            <div class="nav-section">

                <span>Inicio</span>

                <a
                    class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                    href="{{ route('dashboard') }}"
                >
                    <x-icon name="home"/>
                    <b>Panel principal</b>
                </a>

            </div>


            {{-- ==============================================
                 SUPERADMINISTRADOR
            =============================================== --}}

            @if($isSuperAdministrator)

                <div class="nav-section">

                    <span>Administración</span>

                    <a
                        class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                        href="{{ route('admin.users.index') }}"
                    >
                        <x-icon name="users"/>
                        <b>Clientes / Gerentes</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('admin.sites.*', 'admin.companies.*') ? 'active' : '' }}"
                        href="{{ route('admin.sites.index') }}"
                    >
                        <x-icon name="building"/>
                        <b>Gestión de sedes</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('admin.units.*') ? 'active' : '' }}"
                        href="{{ route('admin.units.index') }}"
                    >
                        <x-icon name="box"/>
                        <b>Catálogo de unidades</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('admin.contacts.*') ? 'active' : '' }}"
                        href="{{ route('admin.contacts.index') }}"
                    >
                        <x-icon name="layers"/>
                        <b>Contactos</b>
                    </a>

                </div>


                <div class="nav-section">

                    <span>Supervisión</span>

                    <a
                        class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}"
                        href="{{ route('admin.settings') }}"
                    >
                        <x-icon name="activity"/>
                        <b>Bitácora</b>
                    </a>

                    <a
                        class="nav-link"
                        href="{{ route('dashboard') }}#reportes-globales"
                    >
                        <x-icon name="chart"/>
                        <b>Reportes globales</b>
                    </a>

                </div>


            {{-- ==============================================
                 USUARIO COMÚN
            =============================================== --}}

            @elseif($isCommonUser)

                <div class="nav-section">

                    <span>Oportunidades</span>

                    <a
                        class="nav-link {{ request()->routeIs('user.services.*') ? 'active' : '' }}"
                        href="{{ route('user.services.index') }}"
                    >
                        <x-icon name="calendar"/>
                        <b>Servicios disponibles</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('user.applications.create') ? 'active' : '' }}"
                        href="{{ route('user.applications.create') }}"
                    >
                        <x-icon name="group"/>
                        <b>Solicitar puesto</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('user.applications.index') ? 'active' : '' }}"
                        href="{{ route('user.applications.index') }}"
                    >
                        <x-icon name="layers"/>
                        <b>Mis solicitudes</b>
                    </a>

                </div>


            {{-- ==============================================
                 ADMINISTRACIÓN GENERAL Y GERENTES
            =============================================== --}}

            @else

                <div class="nav-section">

                    <span>Administración</span>

                    @if($isManager)

                        <a
                            class="nav-link {{ request()->routeIs('manager.sites*') ? 'active' : '' }}"
                            href="{{ route('manager.sites') }}"
                        >
                            <x-icon name="building"/>
                            <b>Gestión de sede</b>
                        </a>

                        <a
                            class="nav-link {{ request()->routeIs('manager.site-page.*') ? 'active' : '' }}"
                            href="{{ route('manager.site-page.edit') }}"
                        >
                            <x-icon name="layers"/>
                            <b>Mi página web</b>
                        </a>

                    @else

                        <a
                            class="nav-link {{ request()->routeIs('sites.select*') ? 'active' : '' }}"
                            href="{{ route('sites.select') }}"
                        >
                            <x-icon name="building"/>
                            <b>Selección de sede</b>
                        </a>

                    @endif


                    @if($canAdminister)

                        <a
                            class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                            href="{{ route('admin.users.index') }}"
                        >
                            <x-icon name="users"/>
                            <b>Usuarios y permisos</b>
                        </a>

                        <a
                            class="nav-link {{ request()->routeIs('admin.sites.*') ? 'active' : '' }}"
                            href="{{ route('admin.sites.index') }}"
                        >
                            <x-icon name="building"/>
                            <b>Gestión de sedes</b>
                        </a>

                        <a
                            class="nav-link {{ request()->routeIs('admin.units.*') ? 'active' : '' }}"
                            href="{{ route('admin.units.index') }}"
                        >
                            <x-icon name="box"/>
                            <b>Catálogo de unidades</b>
                        </a>

                    @endif

                </div>

            @endif


            {{-- ==============================================
                 PERSONAL DEL GERENTE
            =============================================== --}}

            @if($isManager)

                <div class="nav-section">

                    <span>Personal</span>

                    <a
                        class="nav-link {{ request()->routeIs('manager.employees.*') ? 'active' : '' }}"
                        href="{{ route('manager.employees.index') }}"
                    >
                        <x-icon name="users"/>
                        <b>Empleados</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('manager.services.*') ? 'active' : '' }}"
                        href="{{ route('manager.services.index') }}"
                    >
                        <x-icon name="calendar"/>
                        <b>Gestión de servicios</b>
                    </a>

                </div>

            @endif


            {{-- ==============================================
                 RECEPCIÓN E INVENTARIO ADMINISTRATIVO
            =============================================== --}}

            @if(($canAdminister || $isManager) && !$isSuperAdministrator)

                <div class="nav-section">

                    <span>Recepción / Inventario</span>

                    <a
                        class="nav-link {{ request()->routeIs('receptions.*') ? 'active' : '' }}"
                        href="{{ route('receptions.index') }}"
                    >
                        <x-icon name="box"/>
                        <b>Recepción de insumos</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}"
                        href="{{ route('inventory.index') }}"
                    >
                        <x-icon name="warehouse"/>
                        <b>Inventario de insumos</b>
                    </a>

                </div>


                @if(!$isManager)

                    <div class="nav-section">

                        <span>Servicios</span>

                        <a
                            class="nav-link {{ request()->routeIs('services.index') ? 'active' : '' }}"
                            href="{{ route('services.index') }}"
                        >
                            <x-icon name="calendar"/>
                            <b>Gestión de servicios</b>
                        </a>

                        <a
                            class="nav-link {{ request()->routeIs('services.live') ? 'active' : '' }}"
                            href="{{ route('services.live') }}"
                        >
                            <x-icon name="activity"/>
                            <b>Servicio en vivo</b>
                        </a>

                    </div>

                @endif

            @endif


            {{-- =================================================
                 CAPITÁN DE MESEROS - ROL 7
                 MÓDULO ESPECIALIZADO
            ================================================== --}}

            @if(
                $isCaptain &&
                !$canAdminister &&
                !$isManager &&
                !$isSuperAdministrator
            )

                <div class="nav-section">

                    <span>Operación de piso</span>

                    {{-- PANEL DEL CAPITÁN --}}

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo', 'panel') === 'panel' ? 'active' : '' }}"
                        href="{{ route('capitan.index') }}"
                    >
                        <x-icon name="home"/>
                        <b>Panel del capitán</b>
                    </a>


                    {{-- SERVICIO EN VIVO --}}

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo') === 'servicios' ? 'active' : '' }}"
                        href="{{ route('capitan.index', ['modulo' => 'servicios']) }}"
                    >
                        <x-icon name="activity"/>
                        <b>Servicio en vivo</b>
                    </a>


                    {{-- MESAS Y ZONAS --}}

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo') === 'mesas' ? 'active' : '' }}"
                        href="{{ route('capitan.index', ['modulo' => 'mesas']) }}"
                    >
                        <x-icon name="map"/>
                        <b>Mesas y zonas</b>
                    </a>


                    {{-- ASIGNACIONES --}}

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo') === 'asignaciones' ? 'active' : '' }}"
                        href="{{ route('capitan.index', ['modulo' => 'asignaciones']) }}"
                    >
                        <x-icon name="users"/>
                        <b>Asignación de meseros</b>
                    </a>

                </div>


                {{-- SUPERVISIÓN DEL CAPITÁN --}}

                <div class="nav-section">

                    <span>Supervisión</span>

                    {{-- ATENCIONES --}}

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo') === 'atenciones' ? 'active' : '' }}"
                        href="{{ route('capitan.index', ['modulo' => 'atenciones']) }}"
                    >
                        <x-icon name="layers"/>
                        <b>Atenciones</b>
                    </a>


                    {{-- COMIDAS --}}

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo') === 'comandas' ? 'active' : '' }}"
                        href="{{ route('capitan.index', ['modulo' => 'comandas']) }}"
                    >
                        <x-icon name="utensils"/>
                        <b>Comidas</b>
                    </a>


                    {{-- GRUPOS --}}

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo') === 'grupos' ? 'active' : '' }}"
                        href="{{ route('capitan.index', ['modulo' => 'grupos']) }}"
                    >
                        <x-icon name="group"/>
                        <b>Grupos y entregas</b>
                    </a>


                    {{-- INCIDENCIAS --}}

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo') === 'incidencias' ? 'active' : '' }}"
                        href="{{ route('capitan.index', ['modulo' => 'incidencias']) }}"
                    >
                        <x-icon name="alert"/>
                        <b>Incidencias</b>
                    </a>

                </div>


                {{-- REPORTES DEL CAPITÁN --}}

                <div class="nav-section">

                    <span>Reportes</span>

                    <a
                        class="nav-link {{ request()->routeIs('capitan.index') && request('modulo') === 'reportes' ? 'active' : '' }}"
                        href="{{ route('capitan.index', ['modulo' => 'reportes']) }}"
                    >
                        <x-icon name="chart"/>
                        <b>Reportes de servicio</b>
                    </a>

                </div>

            @endif


            {{-- =================================================
                 OPERACIÓN DE EMPLEADOS
                 EXCLUYE AL CAPITÁN PARA EVITAR DUPLICADOS
            ================================================== --}}

            @if(
                !$isSuperAdministrator &&
                !$isManager &&
                !$canAdminister &&
                !$isCommonUser &&
                !$isCaptain
            )

                <div class="nav-section">

                    <span>Mi operación</span>


                    {{-- PERSONAL DE ALMACÉN --}}

                    @if($isWarehouse)

                        <a
                            class="nav-link {{ request()->routeIs('receptions.*') ? 'active' : '' }}"
                            href="{{ route('receptions.index') }}"
                        >
                            <x-icon name="box"/>
                            <b>Recepción</b>
                        </a>

                    @endif


                    {{-- INVENTARIO --}}

                    @if($isWarehouse || $isKitchen)

                        <a
                            class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}"
                            href="{{ route('inventory.index') }}"
                        >
                            <x-icon name="warehouse"/>
                            <b>Inventario</b>
                        </a>

                    @endif


                    {{-- JEFE DE COCINA: UNIDADES --}}

                    @if($authenticatedUser->hasRole('Jefe de cocina'))

                        <a
                            class="nav-link {{ request()->routeIs('admin.units.*') ? 'active' : '' }}"
                            href="{{ route('admin.units.index') }}"
                        >
                            <x-icon name="box"/>
                            <b>Administrar unidades</b>
                        </a>

                    @endif


                    {{-- SERVICIOS --}}

                    @if($isKitchen || $isService)

                        <a
                            class="nav-link {{ request()->routeIs('services.*') ? 'active' : '' }}"
                            href="{{ route('services.index') }}"
                        >
                            <x-icon name="calendar"/>
                            <b>Servicios</b>
                        </a>

                    @endif


                    {{-- GRUPOS Y ENTREGAS --}}

                    @if($isKitchen)

                        <a
                            class="nav-link {{ request()->routeIs('groups.*') ? 'active' : '' }}"
                            href="{{ route('groups.index') }}"
                        >
                            <x-icon name="group"/>
                            <b>Grupos y entregas</b>
                        </a>

                    @endif


                    {{-- MENÚ Y PLATILLOS --}}

                    @if($isKitchen)

                        <a
                            class="nav-link {{ request()->routeIs('menus.*') ? 'active' : '' }}"
                            href="{{ route('menus.index') }}"
                        >
                            <x-icon name="utensils"/>
                            <b>Menú por servicio</b>
                        </a>

                        <a
                            class="nav-link {{ request()->routeIs('dishes.*') ? 'active' : '' }}"
                            href="{{ route('dishes.index') }}"
                        >
                            <x-icon name="dish"/>
                            <b>Platillos</b>
                        </a>

                        @if($authenticatedUser->hasRole('Jefe de cocina'))

                            <a
                                class="nav-link {{ request()->routeIs('dishes.categories.*') ? 'active' : '' }}"
                                href="{{ route('dishes.categories.index') }}"
                            >
                                <x-icon name="layers"/>
                                <b>Administrar categorías</b>
                            </a>

                        @endif

                    @endif


                    {{-- MESAS PARA PERSONAL DE SERVICIO --}}

                    @if($isService)

                        <a
                            class="nav-link {{ request()->routeIs('tables.*') ? 'active' : '' }}"
                            href="{{ route('tables.index') }}"
                        >
                            <x-icon name="map"/>
                            <b>Mesas y zonas</b>
                        </a>

                    @endif


                    {{-- PEDIDOS --}}

                    @if($isWarehouse)

                        <a
                            class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}"
                            href="{{ route(auth()->user()->hasRole('Recepcion de insumos') && !auth()->user()->hasRole('Jefe de almacen') ? 'receptions.index' : 'orders.index') }}"
                        >
                            <x-icon name="box"/>
                            <b>Pedidos</b>
                        </a>

                    @endif


                    {{-- CAJA --}}

                    @if($isCashier)

                        <a
                            class="nav-link {{ request()->routeIs('cash.*') ? 'active' : '' }}"
                            href="{{ route('cash.index') }}"
                        >
                            <x-icon name="cash"/>
                            <b>Caja</b>
                        </a>

                    @endif


                    {{-- COCINA --}}

                    @if($isKitchen)

                        <a
                            class="nav-link {{ request()->routeIs('kitchen.*') ? 'active' : '' }}"
                            href="{{ route('kitchen.index') }}"
                        >
                            <x-icon name="dish"/>
                            <b>Operación de cocina</b>
                        </a>

                    @endif


                    {{-- INCIDENCIAS --}}

                    <a
                        class="nav-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}"
                        href="{{ route('incidents.index') }}"
                    >
                        <x-icon name="alert"/>
                        <b>Incidencias</b>
                    </a>

                </div>

            @endif


            {{-- =================================================
                 OPERACIÓN DE ADMINISTRADOR Y GERENTE
            ================================================== --}}

            @if(($canAdminister || $isManager) && !$isSuperAdministrator)

                <div class="nav-section">

                    <span>Operación</span>

                    <a
                        class="nav-link {{ request()->routeIs('tables.*') ? 'active' : '' }}"
                        href="{{ route('tables.index') }}"
                    >
                        <x-icon name="map"/>
                        <b>Mesas y zonas</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('groups.*') ? 'active' : '' }}"
                        href="{{ route('groups.index') }}"
                    >
                        <x-icon name="group"/>
                        <b>Grupos y entregas</b>
                    </a>

                </div>


                {{-- MENÚ --}}

                <div class="nav-section">

                    <span>Menú</span>

                    <a
                        class="nav-link {{ request()->routeIs('menus.*') ? 'active' : '' }}"
                        href="{{ route('menus.index') }}"
                    >
                        <x-icon name="utensils"/>
                        <b>Menú por servicio</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('dishes.*') ? 'active' : '' }}"
                        href="{{ route('dishes.index') }}"
                    >
                        <x-icon name="dish"/>
                        <b>Platillos</b>
                    </a>

                </div>


                {{-- CONTROL ADMINISTRATIVO --}}

                <div class="nav-section">

                    <span>Control</span>

                    <a
                        class="nav-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}"
                        href="{{ route('incidents.index') }}"
                    >
                        <x-icon name="alert"/>
                        <b>Incidencias</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}"
                        href="{{ route('orders.index') }}"
                    >
                        <x-icon name="box"/>
                        <b>Pedidos</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('requisitions.*') ? 'active' : '' }}"
                        href="{{ route('requisitions.index') }}"
                    >
                        <x-icon name="layers"/>
                        <b>Requisiciones</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('cash.*') ? 'active' : '' }}"
                        href="{{ route('cash.index') }}"
                    >
                        <x-icon name="cash"/>
                        <b>Caja</b>
                    </a>

                    <a
                        class="nav-link {{ request()->routeIs('kitchen.*') ? 'active' : '' }}"
                        href="{{ route('kitchen.index') }}"
                    >
                        <x-icon name="dish"/>
                        <b>Operación de cocina</b>
                    </a>

                </div>


                {{-- REPORTES GENERALES --}}

                <div class="nav-section">

                    <span>Reportes</span>

                    <a
                        class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}"
                        href="{{ route('reports.index') }}"
                    >
                        <x-icon name="chart"/>
                        <b>Reportes</b>
                    </a>

                </div>

            @endif

        </nav>


        {{-- ==================================================
             PIE DEL SIDEBAR
        =================================================== --}}

        <div class="sidebar-footer">

            <span class="system-dot"></span>

            <div>
                <strong>Sistema operativo</strong>
                <small>Conexión activa</small>
            </div>

        </div>

    </aside>


    {{-- ======================================================
         OVERLAY PARA MÓVILES
    ======================================================= --}}

    <div
        class="sidebar-overlay"
        data-sidebar-close
    ></div>


    {{-- ======================================================
         CONTENIDO PRINCIPAL
    ======================================================= --}}

    <div class="app-main">

        {{-- ==============================================
             BARRA SUPERIOR
        =============================================== --}}

        <header class="topbar">

            <div class="topbar-left">

                {{-- BOTÓN MENÚ MÓVIL --}}

                <button
                    class="mobile-menu"
                    type="button"
                    data-sidebar-toggle
                    aria-controls="app-sidebar"
                    aria-label="Abrir menú"
                >
                    <span></span>
                    <span></span>
                    <span></span>
                </button>


                {{-- CONTEXTO SUPERADMINISTRADOR --}}

                @if($isSuperAdministrator)

                    <div class="site-context-note">

                        <x-icon name="building"/>

                        <div>
                            <small>Contexto</small>
                            <strong>Administración global</strong>
                        </div>

                    </div>


                {{-- CONTEXTO USUARIO COMÚN --}}

                @elseif($isCommonUser)

                    <div class="site-context-note">

                        <x-icon name="group"/>

                        <div>
                            <small>Estado de cuenta</small>

                            <strong>
                                Sin puesto operativo asignado
                            </strong>
                        </div>

                    </div>


                {{-- SELECTOR DE SEDE --}}

                @else

                    <form
                        method="POST"
                        action="{{ route('sites.select.update') }}"
                        class="site-selector"
                    >
                        @csrf

                        <x-icon name="building"/>

                        <div>

                            <small>Sede actual</small>

                            <select
                                name="id_sede"
                                onchange="this.form.submit()"
                                aria-label="Seleccionar sede"
                                {{ $availableSites->isEmpty() ? 'disabled' : '' }}
                            >

                                @forelse($availableSites as $site)

                                    <option
                                        value="{{ $site->id_sede }}"
                                        @selected($currentSite?->id_sede === $site->id_sede)
                                    >
                                        {{ $site->nombre }}
                                    </option>

                                @empty

                                    <option>
                                        Sin sedes disponibles
                                    </option>

                                @endforelse

                            </select>

                        </div>

                    </form>

                @endif

            </div>


            {{-- ==============================================
                 PARTE DERECHA DE LA BARRA SUPERIOR
            =============================================== --}}

            <div class="topbar-right">

                {{-- NOTIFICACIONES --}}

                <button
                    class="notification-button"
                    type="button"
                    aria-label="Notificaciones"
                >
                    <x-icon name="bell"/>
                </button>


                {{-- MENÚ DE USUARIO --}}

                <details class="user-menu">

                    <summary>

                        <span class="avatar">
                            {{ mb_strtoupper(mb_substr($authenticatedUser->nombre ?: 'U', 0, 1)) }}{{ mb_strtoupper(mb_substr($authenticatedUser->apellido_p ?: '', 0, 1)) }}
                        </span>

                        <span class="user-copy">

                            <strong>
                                {{ $authenticatedUser->name }}
                            </strong>

                            <small>
                                {{ $authenticatedUser->roleLabel() }}
                            </small>

                        </span>

                        <x-icon name="chevron"/>

                    </summary>


                    <div class="user-dropdown">

                        <div>

                            <strong>
                                {{ $authenticatedUser->name }}
                            </strong>

                            <span>
                                {{ $authenticatedUser->correo }}
                            </span>

                        </div>

                        <hr>

                        <span class="disabled-link">
                            Perfil próximamente
                        </span>


                        {{-- CERRAR SESIÓN --}}

                        <form
                            method="POST"
                            action="{{ route('logout') }}"
                        >
                            @csrf

                            <button type="submit">
                                Cerrar sesión
                            </button>

                        </form>

                    </div>

                </details>

            </div>

        </header>


        {{-- ==================================================
             CONTENIDO DE CADA VISTA
        =================================================== --}}

        <main class="page-content">

            {{-- MENSAJES DEL SISTEMA --}}

            @if(session('status'))

                <div class="alert success">
                    {{ session('status') }}
                </div>

            @endif


            {{-- ERRORES DE VALIDACIÓN --}}

            @if($errors->any())

                <div class="alert error">

                    <strong>
                        Revisa la información:
                    </strong>

                    <ul>

                        @foreach($errors->all() as $error)

                            <li>
                                {{ $error }}
                            </li>

                        @endforeach

                    </ul>

                </div>

            @endif


            {{-- VISTAS DE LARAVEL --}}

            @yield('content')

        </main>

    </div>

</div>


{{-- SCRIPTS PARTICULARES DE CADA VISTA --}}
@stack('scripts')

</body>
</html>
