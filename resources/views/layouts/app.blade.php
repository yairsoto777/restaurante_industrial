
<!DOCTYPE html>
<html lang="es" class="{{ auth()->user()->isManager() ? 'manager-workspace' : '' }} {{ auth()->user()->hasRole('Recepcion de insumos') ? 'reception-workspace' : '' }} {{ auth()->user()->hasRole('Jefe de almacen') ? 'warehouse-workspace' : '' }} {{ auth()->user()->hasRole('Jefe de cocina') ? 'chef-workspace' : '' }}">

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

    @if(auth()->user()->isSuperAdministrator())
    <link rel="stylesheet" href="{{ asset('css/superadmin-workspace.css') }}?v={{ filemtime(public_path('css/superadmin-workspace.css')) }}">
    <script src="{{ asset('js/superadmin-workspace.js') }}?v={{ filemtime(public_path('js/superadmin-workspace.js')) }}" defer></script>
    @endif
    {{-- ESTILOS PARTICULARES DE CADA VISTA --}}
    @stack('styles')
    @if(auth()->user()->hasRole('Jefe de cocina'))
        <link rel="stylesheet" href="{{ asset('css/chef-workspace.css') }}?v={{ filemtime(public_path('css/chef-workspace.css')) }}">
        <script src="{{ asset('js/chef-workspace.js') }}?v={{ filemtime(public_path('js/chef-workspace.js')) }}" defer></script>
    @endif
    <link rel="stylesheet" href="{{ asset('css/role-workspace.css') }}?v=20261114">
    @if(auth()->user()->hasRole('Jefe de almacen'))
        <link rel="stylesheet" href="{{ asset('css/warehouse-workspace.css') }}?v=20261115-status-colors">
        <script src="{{ asset('js/warehouse-workspace.js') }}?v=20261114" defer></script>
    @endif
    @if(auth()->user()->hasRole('Recepcion de insumos'))
        <link rel="stylesheet" href="{{ asset('css/reception-workspace.css') }}?v=20261114">
    @endif
    @if(auth()->user()->isManager())
        <link rel="stylesheet" href="{{ asset('css/manager.css') }}?v=20261113">
        @if(request()->routeIs('manager.employees.*'))
            <link rel="stylesheet" href="{{ asset('css/manager-users.css') }}?v=20261113">
        @endif
        <link rel="stylesheet" href="{{ asset('css/manager-finish.css') }}?v=20261114">
        <link rel="stylesheet" href="{{ asset('css/manager-polish.css') }}?v=20261114">
        <link rel="stylesheet" href="{{ asset('css/manager-corporate.css') }}?v=20261114-dashboard-roles">
    @endif

    @if(auth()->user()->hasRole('Capitan de meseros') || auth()->user()->hasRole('Mesero') || auth()->user()->hasRole('Cajero'))
        <link rel="stylesheet" href="{{ asset('css/operations.css') }}?v=20261112">
        <script src="{{ asset('js/operations.js') }}?v=20261112" defer></script>
    @endif
    @if(auth()->user()->hasRole('Capitan de meseros'))
        <link rel="stylesheet" href="{{ asset('css/captain-visual.css') }}?v=20261113">
        <script src="{{ asset('js/captain-visual.js') }}?v=20261113" defer></script>
        <link rel="stylesheet" href="{{ asset('css/captain-workspace.css') }}?v=20261116-incidents">
        <script src="{{ asset('js/captain-workspace.js') }}?v={{ filemtime(public_path('js/captain-workspace.js')) }}" defer></script>
    @endif
    @if(auth()->user()->hasRole('Mesero') && !auth()->user()->hasRole('Capitan de meseros') && !auth()->user()->hasRole('Cajero'))
        <link rel="stylesheet" href="{{ asset('css/waiter-visual.css') }}?v=20261113">
        <link rel="stylesheet" href="{{ asset('css/waiter-workspace.css') }}?v={{ filemtime(public_path('css/waiter-workspace.css')) }}">
        <script src="{{ asset('js/waiter-workspace.js') }}?v={{ filemtime(public_path('js/waiter-workspace.js')) }}" defer></script>
    @endif

    @if(auth()->user()->hasRole('Cajero') && !auth()->user()->hasRole('Capitan de meseros'))
        <link rel="stylesheet" href="{{ asset('css/cashier-visual.css') }}?v=20261113">
        <link rel="stylesheet" href="{{ asset('css/cashier-workspace.css') }}?v={{ filemtime(public_path('css/cashier-workspace.css')) }}">
        <script src="{{ asset('js/cashier-workspace.js') }}?v={{ filemtime(public_path('js/cashier-workspace.js')) }}" defer></script>
    @endif
    <link rel="stylesheet" href="{{ asset('css/site-brand.css') }}?v={{ filemtime(public_path('css/site-brand.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard-banners.css') }}?v={{ filemtime(public_path('css/dashboard-banners.css')) }}">
</head>

<body class="app-body {{ auth()->user()->isSuperAdministrator() ? 'superadmin-workspace' : '' }} {{ auth()->user()->hasRole('Cajero') && !auth()->user()->hasRole('Capitan de meseros') ? 'ops-cashier' : '' }} {{ auth()->user()->hasRole('Capitan de meseros') || auth()->user()->hasRole('Mesero') || auth()->user()->hasRole('Cajero') ? 'ops-ui' : '' }} {{ auth()->user()->hasRole('Capitan de meseros') ? 'ops-captain' : '' }} {{ auth()->user()->hasRole('Mesero') && !auth()->user()->hasRole('Capitan de meseros') && !auth()->user()->hasRole('Cajero') ? 'ops-waiter' : '' }}">

@php
    /*
    |--------------------------------------------------------------------------
    | USUARIO AUTENTICADO Y PERMISOS
    |--------------------------------------------------------------------------
    */

    $authenticatedUser = auth()->user();

    $isCommonUser = $authenticatedUser->isCommonUser();

    $isManager = $authenticatedUser->isManager();
    $isReception = $authenticatedUser->hasRole('Recepcion de insumos');
    $isWarehouseChief = $authenticatedUser->hasRole('Jefe de almacen');
    $isKitchenChief = $authenticatedUser->hasRole('Jefe de cocina');
    $isKitchenStaff = $authenticatedUser->hasRole('Personal de cocina');

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

        <div class="sidebar-brand {{ $isKitchenStaff ? 'kp-brand' : '' }}">

            @php
                $sidebarSite = $isSuperAdministrator ? null : $currentSite;
                $sidebarName = $sidebarSite?->nombre ?? 'NUTRILIADO';
                $sidebarLogo = \App\Support\ChefWorkspace::image($sidebarSite?->logo_url, 'images/logo.png');
            @endphp
            <div class="site-brand-custom">
                <div class="site-brand-logo">
                    <img src="{{ $sidebarLogo }}" alt="" data-fallback="{{ asset('images/logo.png') }}"
                        onerror="this.onerror=null; this.src=this.dataset.fallback;">
                </div>
                <strong>{{ $sidebarName }}</strong>
                <small>{{ $isSuperAdministrator ? 'Administración general' : 'Operación alimentaria' }}</small>
            </div>

        </div>


        {{-- ==================================================
             NAVEGACIÓN PRINCIPAL
        =================================================== --}}

        <nav
            class="sidebar-nav"
            aria-label="Navegación principal"
        >
            @php
                [$sidebarRole, $sidebarIcon] = match (true) {
                    $isSuperAdministrator => ['Superadministrador', 'layers'],
                    $isKitchenChief => ['Jefe de cocina', 'utensils'],
                    $isKitchenStaff => ['Personal de cocina', 'utensils'],
                    $isWarehouseChief => ['Jefe de almacén', 'warehouse'],
                    $isReception => ['Recepción de insumos', 'box'],
                    $isCaptain => ['Capitán de meseros', 'users'],
                    $authenticatedUser->hasRole('Mesero') => ['Mesero', 'users'],
                    $isCashier => ['Cajero', 'cash'],
                    $isManager => ['Gerente de Sede', 'building'],
                    default => [$authenticatedUser->roleLabel(), 'users'],
                };
            @endphp
            <div class="sidebar-role">
                <x-icon :name="$sidebarIcon"/>
                <div>
                    <strong>{{ $sidebarRole }}</strong>
                    @if($isSuperAdministrator)
                        <small>Control corporativo</small>
                    @else
                        <small>Sede: {{ $sidebarSite?->nombre ?? 'Sin sede' }}</small>
                    @endif
                </div>
            </div>

            @if($isSuperAdministrator)
                @include('superadmin.navigation')
            @elseif($isKitchenChief)
                @include('chef.workspace.navigation')
            @elseif($isKitchenStaff)
                @include('kitchen.production.navigation')
            @elseif($isWarehouseChief)
                @include('almacen.workspace.navigation')
            @elseif($isReception)
                @include('receptions.workspace.navigation')
            @elseif($isCaptain)
                @include('capitan.partials.navigation')
            @elseif($authenticatedUser->hasRole('Mesero'))
                @include('mesero.workspace.navigation')
            @elseif($isCashier)
                @include('cash.workspace.navigation')
            @else

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
                        <b>Gestión de usuarios</b>
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

                @include('capitan.partials.navigation')
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

                        @if($authenticatedUser->hasRole('Mesero'))
                            <a class="nav-link {{ request()->routeIs('waiter.*') ? 'active' : '' }}" href="{{ route('waiter.index') }}">
                                <x-icon name="plates"/><b>Mi operación</b>
                            </a>
                        @endif

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

            @if($canAdminister && !$isManager && !$isSuperAdministrator)

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
                @include('auth.role-dropdown')

                {{-- NOTIFICACIONES --}}

                @if($isSuperAdministrator)
                    @include('manager.notifications', ['notificationFeed' => 'superadmin.notifications.index', 'notificationRead' => 'superadmin.notifications.read'])
                @elseif(auth()->user()->isManager())
                    @include('manager.notifications')
                @elseif($isReception)
                    @include('manager.notifications', ['notificationFeed' => 'receptions.notifications.index', 'notificationRead' => 'receptions.notifications.read'])
                @elseif($isWarehouseChief)
                    @include('manager.notifications', ['notificationFeed' => 'warehouse.notifications.index', 'notificationRead' => 'warehouse.notifications.read'])
                @elseif($isKitchenChief)
                    @include('manager.notifications', ['notificationFeed' => 'chef.notifications.index', 'notificationRead' => 'chef.notifications.read'])
                @elseif($isKitchenStaff)
                    @include('manager.notifications', ['notificationFeed' => 'kitchen.notifications.index', 'explicitReview' => true])
                @elseif($isCaptain)
                    @include('manager.notifications', ['notificationFeed' => 'capitan.notifications.index', 'notificationRead' => 'capitan.notifications.read'])
                @elseif($authenticatedUser->hasRole('Mesero'))
                    @include('manager.notifications', ['notificationFeed' => 'waiter.notifications.index', 'notificationRead' => 'waiter.notifications.read'])
                @else
                <button
                    class="notification-button"
                    type="button"
                    aria-label="Notificaciones"
                >
                    <x-icon name="bell"/>
                </button>
                @endif


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

        <main class="page-content" id="main-content">

            {{-- MENSAJES DEL SISTEMA --}}

            @if(session('status') && !$isSuperAdministrator && !($isCashier && request()->routeIs('cash.*')))

                <div class="alert success">
                    {{ session('status') }}
                </div>

            @endif


            {{-- ERRORES DE VALIDACIÓN --}}

            @if($errors->any() && !$isSuperAdministrator && !($isCashier && request()->routeIs('cash.*')))

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

            @if(($isCaptain || $isService || $isCashier) && $errors->any())
                <script type="application/json" id="ops-validation-errors">@json($errors->messages())</script>
            @endif

            @yield('content')

        </main>

    </div>

</div>


{{-- SCRIPTS PARTICULARES DE CADA VISTA --}}
@stack('scripts')

</body>
</html>
