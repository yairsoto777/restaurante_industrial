
@extends('layouts.app')

@section('title', 'Panel principal')

{{-- DISEÑO VISUAL DEL DASHBOARD --}}
@push('styles')
    @vite(['resources/css/dashboard_visual.css'])
@endpush

@section('content')

{{-- ======================================================
     DASHBOARD GLOBAL - SUPERADMINISTRADOR
     SE CONSERVA LA FUNCIONALIDAD DE GITHUB
====================================================== --}}

@if($globalDashboard ?? false)

    <div class="dashboard-heading">

        <div>
            <span class="section-kicker">
                Administración global
            </span>

            <h1>Dashboard global</h1>

            <p>
                Consulta clientes, gerentes y el estado general
                de la plataforma sin intervenir en la
                operación diaria.
            </p>
        </div>

        <a
            class="button primary"
            href="{{ route('admin.users.create') }}"
        >
            Crear gerente
        </a>

    </div>

    {{-- INDICADORES GLOBALES --}}

    <section
        class="kpi-grid"
        aria-label="Indicadores globales"
    >

        @foreach($globalKpis as $kpi)

            <article class="kpi-card">

                <div class="kpi-icon {{ $kpi['tone'] }}">
                    <x-icon :name="$kpi['icon']"/>
                </div>

                <div class="kpi-copy">
                    <span>{{ $kpi['label'] }}</span>

                    <strong>
                        {{ number_format($kpi['value']) }}
                    </strong>

                    <small>{{ $kpi['detail'] }}</small>
                </div>

            </article>

        @endforeach

    </section>

    {{-- DIRECTORIO DE GERENTES --}}

    <section class="panel">

        <div class="panel-head">

            <div>
                <span class="section-kicker">
                    Directorio
                </span>

                <h2>Clientes / Gerentes</h2>

                <p>
                    Responsables y empresas bajo
                    administración global.
                </p>
            </div>

            <a href="{{ route('admin.users.index') }}">
                Administrar gerentes
            </a>

        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Gerente</th>
                        <th>Correo</th>
                        <th>Empresa / sede</th>
                        <th>Estado</th>
                        <th>Empleados</th>
                        <th>Rol</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($managers as $manager)

                        @php
                            $site = $manager->sites->first();

                            $employeeCount = $site
                                ? $site->users
                                    ->reject(
                                        fn($user) =>
                                            $user->isManager() ||
                                            $user->isSuperAdministrator()
                                    )
                                    ->count()
                                : 0;
                        @endphp

                        <tr>

                            <td>
                                <strong>
                                    {{ $manager->name }}
                                </strong>
                            </td>

                            <td>
                                {{ $manager->correo }}
                            </td>

                            <td>
                                {{ $site?->nombre ?? 'Pendiente de configuración' }}
                            </td>

                            <td>
                                <span class="badge">
                                    {{ $site ? 'Activo' : 'Configuración pendiente' }}
                                </span>
                            </td>

                            <td>
                                {{ $employeeCount }}
                            </td>

                            <td>
                                {{ $manager->roleLabel() }}
                            </td>

                            <td>

                                @if($site)

                                    <a
                                        class="button"
                                        href="{{ route('admin.companies.show', $site) }}"
                                    >
                                        Ver empresa
                                    </a>

                                @else

                                    <span>Sin empresa</span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="7">
                                No hay gerentes registrados.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>

{{-- ======================================================
     DASHBOARD OPERATIVO
     GERENTES, ADMINISTRADORES Y PERSONAL OPERATIVO
====================================================== --}}

@else

    {{-- ENCABEZADO DEL DASHBOARD --}}

    <div class="dashboard-heading">

        <div>

            <span class="section-kicker">
                Resumen operativo
            </span>

            <h1>
                Buen día,
                {{ auth()->user()->nombre ?: auth()->user()->name }}
            </h1>

            <p>
                Aquí tienes un resumen de la operación de
                {{ $currentSite?->nombre ?? 'las sedes disponibles' }}.
            </p>

        </div>

        {{-- LOGOTIPO Y FECHA DE LA SEDE --}}

        <div
            style="
                display:flex;
                align-items:center;
                gap:16px;
                position:relative;
                z-index:1;
            "
        >

            @if($siteLogoUrl ?? null)

                <img
                    src="{{ $siteLogoUrl }}"
                    alt="Logo de {{ $currentSite?->nombre }}"
                    style="
                        width:64px;
                        height:64px;
                        object-fit:contain;
                        border-radius:12px;
                    "
                >

            @else

                <div class="kpi-icon blue" aria-hidden="true">
                    <x-icon name="building"/>
                </div>

            @endif

            <div class="current-date">

                <x-icon name="calendar"/>

                <div>
                    <small>Fecha actual</small>

                    <strong>
                        {{ now()->format('d/m/Y') }}
                    </strong>
                </div>

            </div>

        </div>

    </div>

    {{-- VALIDACIÓN DE SEDE --}}

    @if(!$currentSite)

        <div class="alert warning">
            No hay una sede disponible para mostrar
            información operativa.
        </div>

    @endif

    {{-- ==================================================
         CONTACTOS COMERCIALES
    ================================================== --}}

    @if($contactSummary)

        <section class="panel contact-dashboard-card">

            <div>

                <span class="section-kicker">
                    Desarrollo comercial
                </span>

                <h2>Solicitudes de servicio</h2>

                <p>
                    Revisa los contactos recibidos desde
                    la landing y da seguimiento a cada solicitud.
                </p>

            </div>

            <div class="contact-dashboard-metrics">

                <span>
                    <strong>
                        {{ $contactSummary['new'] }}
                    </strong>
                    nuevos
                </span>

                <span>
                    <strong>
                        {{ $contactSummary['follow_up'] }}
                    </strong>
                    contactados
                </span>

                <span>
                    <strong>
                        {{ $contactSummary['total'] }}
                    </strong>
                    totales
                </span>

            </div>

            <a
                class="button primary"
                href="{{ route('admin.contacts.index') }}"
            >
                Abrir contactos
            </a>

        </section>

    @endif

    {{-- ==================================================
         TARJETAS DE ESTADÍSTICAS
    ================================================== --}}

    <section
        class="kpi-grid"
        aria-label="Indicadores principales"
    >

        @foreach($kpis as $kpi)

            <article class="kpi-card">

                <div class="kpi-icon {{ $kpi['tone'] }}">
                    <x-icon :name="$kpi['icon']"/>
                </div>

                <div class="kpi-copy">

                    <span>
                        {{ $kpi['label'] }}
                    </span>

                    <strong>
                        {{ number_format($kpi['value']) }}
                    </strong>

                    <small>
                        {{ $kpi['detail'] }}
                    </small>

                </div>

            </article>

        @endforeach

    </section>

    {{-- ==================================================
         PANELES OPERATIVOS
    ================================================== --}}

    <div class="dashboard-grid">

        {{-- ACTIVIDAD RECIENTE --}}

        <section class="dashboard-card activity-card">

            <div class="card-heading">

                <div>
                    <span class="section-kicker">
                        Movimientos
                    </span>

                    <h2>Actividad reciente</h2>
                </div>

            </div>

            @forelse($activity as $event)

                <div class="activity-item">

                    <span
                        class="activity-dot {{ $event['tone'] }}"
                    ></span>

                    <time>
                        {{ \Carbon\Carbon::parse($event['timestamp'])->format('H:i') }}
                    </time>

                    <div>

                        <strong>
                            {{ $event['title'] }}
                        </strong>

                        <span>
                            {{ \Illuminate\Support\Str::limit($event['detail'], 75) }}
                        </span>

                    </div>

                </div>

            @empty

                <div class="inline-empty">
                    <x-icon name="activity"/>
                    <p>No hay actividad reciente.</p>
                </div>

            @endforelse

        </section>

        {{-- OCUPACIÓN DE HOY --}}

        <section class="dashboard-card occupation-card">

            <div class="card-heading">

                <div>
                    <span class="section-kicker">
                        Capacidad
                    </span>

                    <h2>Ocupación de hoy</h2>
                </div>

            </div>

            <div class="occupation-content">

                <div
                    class="donut"
                    style="--value: {{ $occupation['percent'] }}"
                >

                    <div>

                        <strong>
                            {{ $occupation['percent'] }}%
                        </strong>

                        <span>ocupación</span>

                    </div>

                </div>

                @if($occupation['capacity'] > 0)

                    <div class="occupation-legend">

                        <p>
                            <span class="legend-dot blue"></span>

                            <b>
                                {{ $occupation['current'] }}
                            </b>

                            ocupación actual
                        </p>

                        <p>
                            <span class="legend-dot pale"></span>

                            <b>
                                {{ $occupation['available'] }}
                            </b>

                            capacidad disponible
                        </p>

                        <small>
                            Capacidad total:
                            {{ $occupation['capacity'] }}
                        </small>

                    </div>

                @else

                    <div class="occupation-legend">

                        <p>
                            Sin datos de ocupación
                        </p>

                        <small>
                            La sede no tiene capacidad configurada.
                        </small>

                    </div>

                @endif

            </div>

        </section>

        {{-- ==================================================
             ESTADO DE SERVICIOS
        ================================================== --}}

        <section class="dashboard-card services-card">

            <div class="card-heading">

                <div>

                    <span class="section-kicker">
                        Programación
                    </span>

                    <h2>Estado de servicios</h2>

                </div>

                @if(auth()->user()->canAdminister() || auth()->user()->isManager())

                    <a href="{{ route('services.index') }}">
                        Ver módulo
                        <x-icon name="chevron"/>
                    </a>

                @endif

            </div>

            <div class="services-list">

                @forelse($services as $service)

                    @php
                        $statusClass = match(
                            mb_strtolower($service->estado ?? '')
                        ) {
                            'en curso', 'activo' => 'live',
                            'finalizado', 'terminado' => 'done',
                            default => 'scheduled'
                        };
                    @endphp

                    <div class="service-row">

                        <div class="service-time">

                            <strong>
                                {{ $service->inicio_hora
                                    ? substr($service->inicio_hora, 0, 5)
                                    : '--:--' }}
                            </strong>

                            <span>
                                {{ $service->fin_hora
                                    ? substr($service->fin_hora, 0, 5)
                                    : '--:--' }}
                            </span>

                        </div>

                        <div class="service-name">

                            <strong>
                                {{ $service->nombre }}
                            </strong>

                            <span>
                                {{ (int) $service->ocupacion }}
                                /
                                {{ $service->capacidad ?? 'Sin capacidad' }}
                                comensales
                            </span>

                        </div>

                        <span class="service-status {{ $statusClass }}">
                            {{ $service->estado ?: 'Programado' }}
                        </span>

                    </div>

                @empty

                    <div class="inline-empty">
                        <x-icon name="calendar"/>
                        <p>No hay servicios programados.</p>
                    </div>

                @endforelse

            </div>

        </section>

        {{-- ==================================================
             GRÁFICA DE COMENSALES POR HORA
             EJE VERTICAL = NÚMERO DE COMENSALES
             EJE HORIZONTAL = HORA DEL DÍA
        ================================================== --}}

        <section class="dashboard-card chart-card">

            <div class="card-heading">

                <div>

                    <span class="section-kicker">
                        Actividad operativa
                    </span>

                    <h2>Comensales por hora</h2>

                </div>

                <span class="chart-date">
                    Hoy
                </span>

            </div>

            @if($hourlyDiners->isNotEmpty())

                @php
                    $maxDiners = max(
                        1,
                        (int) $hourlyDiners->max('total')
                    );

                    $chartMax = max(
                        4,
                        (int) ceil($maxDiners / 4) * 4
                    );
                @endphp

                <div class="dash-chart">

                    {{-- TÍTULO VERTICAL --}}

                    <div class="dash-y-title">
                        Número de comensales
                    </div>

                    <div class="dash-chart-main">

                        <div class="dash-chart-scroll">

                            <div class="dash-chart-body">

                                {{-- ESCALA VERTICAL --}}

                                <div
                                    class="dash-y-ticks"
                                    aria-hidden="true"
                                >

                                    <span>
                                        {{ $chartMax }}
                                    </span>

                                    <span>
                                        {{ $chartMax * 3 / 4 }}
                                    </span>

                                    <span>
                                        {{ $chartMax / 2 }}
                                    </span>

                                    <span>
                                        {{ $chartMax / 4 }}
                                    </span>

                                    <span>0</span>

                                </div>

                                {{-- ÁREA DE LAS BARRAS --}}

                                <div class="dash-plot">

                                    <div
                                        class="dash-horizontal-lines"
                                        aria-hidden="true"
                                    >
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>

                                    <div class="dash-bars">

                                        @foreach($hourlyDiners as $point)

                                            @php
                                                $total = (int) $point->total;

                                                $porcentaje = $total > 0
                                                    ? ($total / $chartMax) * 100
                                                    : 0;

                                                $hora = str_pad(
                                                    $point->hora,
                                                    2,
                                                    '0',
                                                    STR_PAD_LEFT
                                                ) . ':00';
                                            @endphp

                                            <div
                                                class="dash-bar-column"
                                                style="--bar-height: {{ $porcentaje }}%;"
                                                title="{{ $hora }} - {{ $total }} comensales"
                                            >

                                                <span class="dash-bar-number">
                                                    {{ $total }}
                                                </span>

                                                <div class="dash-bar-fill"></div>

                                                <span class="dash-bar-time">
                                                    {{ $hora }}
                                                </span>

                                            </div>

                                        @endforeach

                                    </div>

                                </div>

                            </div>

                        </div>

                        {{-- TÍTULO HORIZONTAL --}}

                        <div class="dash-x-title">
                            Hora del día
                        </div>

                    </div>

                </div>

                {{-- RESUMEN DE LA GRÁFICA --}}

                <div class="dash-chart-summary">

                    <span>
                        <span class="dash-summary-dot"></span>
                        Comensales registrados por hora
                    </span>

                    <strong>
                        Total:
                        {{ number_format($hourlyDiners->sum('total')) }}
                        comensales
                    </strong>

                </div>

            @else

                <div class="chart-empty">

                    <div class="chart-grid-lines"></div>

                    <x-icon name="chart"/>

                    <p>
                        No hay datos disponibles para hoy.
                    </p>

                </div>

            @endif

        </section>

    </div>

    {{-- ==================================================
         SEGUIMIENTO OPERATIVO
         SE CONSERVA LA FUNCIONALIDAD DE GITHUB
    ================================================== --}}

    <div class="dashboard-grid">

        {{-- PEDIDOS RECIENTES --}}

        <section class="dashboard-card">

            <div class="card-heading">

                <div>
                    <span class="section-kicker">
                        Seguimiento
                    </span>

                    <h2>Pedidos recientes</h2>
                </div>

            </div>

            @forelse($recentOrders as $order)

                <div class="service-row">

                    <div class="service-name">

                        <strong>
                            {{ $order->proveedor ?? 'Pedido #'.$order->id_pedido }}
                        </strong>

                        <span>
                            {{ $order->fecha_pedido ?? $order->fecha ?? $order->created_at ?? 'Sin fecha' }}
                        </span>

                    </div>

                    <span class="service-status scheduled">
                        {{ $order->estado ?? 'Registrado' }}
                    </span>

                </div>

            @empty

                <div class="inline-empty">

                    <x-icon name="box"/>

                    <p>Sin pedidos recientes.</p>

                </div>

            @endforelse

        </section>

        {{-- INCIDENCIAS RECIENTES --}}

        <section class="dashboard-card">

            <div class="card-heading">

                <div>

                    <span class="section-kicker">
                        Atención
                    </span>

                    <h2>Incidencias recientes</h2>

                </div>

            </div>

            @forelse($recentIncidents as $incident)

                <div class="service-row">

                    <div class="service-name">

                        <strong>
                            {{ \Illuminate\Support\Str::limit(
                                $incident->descripcion ?? 'Incidencia #'.$incident->id_incidencia,
                                65
                            ) }}
                        </strong>

                        <span>
                            {{ $incident->fecha ?? 'Sin fecha' }}
                        </span>

                    </div>

                    <span class="service-status scheduled">
                        {{ $incident->estado ?? 'Abierta' }}
                    </span>

                </div>

            @empty

                <div class="inline-empty">

                    <x-icon name="alert"/>

                    <p>Sin incidencias recientes.</p>

                </div>

            @endforelse

        </section>

    </div>

@endif

@endsection
