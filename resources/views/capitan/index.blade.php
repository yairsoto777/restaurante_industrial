

@extends('layouts.app')

@section('title', 'Capitán de meseros · NUTRILIADO')

@section('content')

{{-- Se carga aquí directamente; no depende de @stack('styles') en layouts.app --}}
@vite(['resources/css/capitan.css'])

@php

    $modulo = $modulo ?? 'panel';

    $primerNombre = mb_strtoupper(

        explode(' ', trim(auth()->user()->nombre ?? 'CAPITÁN'))[0]

    );

    $fotos = [

        'hero' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=1600&q=85',

        'servicios' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=650&q=80',

        'meseros' => 'https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=650&q=80',

        'comensales' => 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=650&q=80',

        'incidencias' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=650&q=80',

    ];

    $tituloModulo = [

        'panel' => 'Centro de operaciones',

        'servicios' => 'Servicio en vivo',

        'mesas' => 'Mesas y zonas',

        'asignaciones' => 'Asignación de meseros',

        'atenciones' => 'Atenciones',

        'comandas' => 'Comidas',

        'grupos' => 'Grupos y entregas',

        'incidencias' => 'Incidencias',

        'reportes' => 'Reportes de servicio',

    ][$modulo] ?? 'Centro de operaciones';

    $datosGrafica = collect($comensalesPorHora ?? [])

        ->map(fn ($dato) => [

            'hora' => (string) data_get($dato, 'hora', ''),

            'total' => (int) data_get($dato, 'total', 0),

        ])

        ->values();

    $maxGrafica = max(

        1,

        (int) $datosGrafica->max('total')

    );

    $totalComensalesHoy = $datosGrafica->sum('total');

    $serviciosHoy = $metricas['servicios'] ?? 0;

    $mesasTotales = $metricas['mesas'] ?? 0;

    $meserosTotales = $metricas['meseros'] ?? 0;

    $atencionesAbiertas = $metricas['atenciones'] ?? 0;

    $incidenciasAbiertas = $metricas['incidencias'] ?? 0;

@endphp

<div class="capitan-page">

    {{-- ENCABEZADO GENERAL --}}

    <div class="cp-page-heading">

        <div>

            <span class="cp-kicker">

                <span class="cp-live-dot"></span>

                NUTRILIADO / OPERACIÓN DE PISO

            </span>

            <h1>{{ $tituloModulo }}</h1>

            <p>

                {{ $sede->nombre }}

                <span class="cp-separator">/</span>

                Capitán de meseros

            </p>

        </div>

        <div class="cp-date">

            <span>FECHA DE OPERACIÓN</span>

            <strong>

                {{ now()->translatedFormat('d/m/Y') }}

            </strong>

        </div>

    </div>

    {{-- ================================================

         PANEL DEL CAPITÁN

    ================================================= --}}

    @if($modulo === 'panel')

        <section class="cp-hero">

            <img

                class="cp-hero-image"

                src="{{ $fotos['hero'] }}"

                alt="Cocina profesional"

                fetchpriority="high"

            >

            <div class="cp-hero-content">

                <span class="cp-hero-tag">

                    <span class="cp-live-dot"></span>

                    CONTROL OPERATIVO

                </span>

                <h2>

                    Buen día,<br>

                    <em>{{ $primerNombre }}</em>

                </h2>

                <p>

                    Cada servicio cuenta. Supervisa al equipo,

                    coordina las mesas y mantén una atención

                    de calidad en tu comedor industrial.

                </p>

                <div class="cp-hero-location">

                    <span class="cp-live-dot"></span>

                    {{ $sede->nombre }}

                </div>

            </div>

            <div class="cp-hero-number">

                <small>NUTRILIADO</small>

                <strong>01 / 09</strong>

            </div>

        </section>

        {{-- ESTADÍSTICAS CON FOTOGRAFÍAS --}}

        <section class="cp-metrics-section">

            <div class="cp-section-heading">

                <div>

                    <span class="cp-kicker">

                        VISIÓN GENERAL

                    </span>

                    <h2>La operación en números</h2>

                </div>

                <span class="cp-section-note">

                    Información de la sede actual

                </span>

            </div>

            <div class="cp-metrics">

                <article class="cp-metric">

                    <div class="cp-metric-photo">

                        <img

                            src="{{ $fotos['servicios'] }}"

                            alt=""

                            loading="lazy"

                        >

                    </div>

                    <div class="cp-metric-content">

                        <span>01 · SERVICIOS</span>

                        <strong>{{ $serviciosHoy }}</strong>

                        <p>Programados para hoy</p>

                    </div>

                    <div class="cp-metric-line"></div>

                </article>

                <article class="cp-metric">

                    <div class="cp-metric-photo">

                        <img

                            src="{{ $fotos['meseros'] }}"

                            alt=""

                            loading="lazy"

                        >

                    </div>

                    <div class="cp-metric-content">

                        <span>02 · PERSONAL</span>

                        <strong>{{ $meserosTotales }}</strong>

                        <p>Meseros de la sede</p>

                    </div>

                    <div class="cp-metric-line"></div>

                </article>

                <article class="cp-metric">

                    <div class="cp-metric-photo">

                        <img

                            src="{{ $fotos['comensales'] }}"

                            alt=""

                            loading="lazy"

                        >

                    </div>

                    <div class="cp-metric-content">

                        <span>03 · COMENSALES</span>

                        <strong>{{ $totalComensalesHoy }}</strong>

                        <p>Registrados hoy</p>

                    </div>

                    <div class="cp-metric-line"></div>

                </article>

                <article class="cp-metric">

                    <div class="cp-metric-photo">

                        <img

                            src="{{ $fotos['incidencias'] }}"

                            alt=""

                            loading="lazy"

                        >

                    </div>

                    <div class="cp-metric-content">

                        <span>04 · INCIDENCIAS</span>

                        <strong>{{ $incidenciasAbiertas }}</strong>

                        <p>Pendientes de seguimiento</p>

                    </div>

                    <div class="cp-metric-line"></div>

                </article>

            </div>

        </section>

{{-- ================================================
     GRÁFICA PROFESIONAL DE COMENSALES POR HORA
================================================ --}}

<section class="cp-chart-card cp-chart-professional">

    <div class="cp-chart-header">
        <div>
            <span class="cp-kicker">
                ACTIVIDAD DEL COMEDOR
            </span>

            <h2>Comensales por hora</h2>

            <p>
                Distribución de personas registradas
                según la hora de inicio de atención.
            </p>
        </div>

        <div class="cp-chart-total">
            <small>TOTAL DEL DÍA</small>
            <strong>{{ $totalComensalesHoy }}</strong>
            <span>comensales</span>
        </div>
    </div>

    @if($datosGrafica->isNotEmpty())

        @php
            $maximoReal = max(
                1,
                (int) $datosGrafica->max('total')
            );

            // Escala de cinco niveles.
            $maximoEje = max(
                4,
                (int) ceil($maximoReal / 4) * 4
            );

            $cantidadBarras = $datosGrafica->count();
        @endphp

        <div class="cp-graph-layout">

            <div class="cp-graph-y-title">
                Número de comensales
            </div>

            <div class="cp-graph-content">

                <div class="cp-graph-scroll">

                    <div
                        class="cp-graph-body"
                        style="--graph-count: {{ $cantidadBarras }};"
                    >

                        {{-- ESCALA VERTICAL --}}
                        <div class="cp-graph-y-scale">
                            <span>{{ $maximoEje }}</span>
                            <span>{{ $maximoEje * 3 / 4 }}</span>
                            <span>{{ $maximoEje / 2 }}</span>
                            <span>{{ $maximoEje / 4 }}</span>
                            <span>0</span>
                        </div>

                        {{-- ÁREA DE LA GRÁFICA --}}
                        <div class="cp-graph-plot">

                            {{-- LÍNEAS DE REFERENCIA --}}
                            <div class="cp-graph-lines">
                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>

                            {{-- COLUMNAS --}}
                            <div class="cp-graph-columns">

                                @foreach($datosGrafica as $punto)

                                    @php
                                        $cantidad = (int) $punto['total'];

                                        $altura = $cantidad > 0
                                            ? ($cantidad / $maximoEje) * 100
                                            : 0;
                                    @endphp

                                    <div
                                        class="cp-graph-column"
                                        style="--graph-height: {{ $altura }}%;"
                                        title="{{ $punto['hora'] }} - {{ $cantidad }} comensales"
                                    >

                                        <span class="cp-graph-number">
                                            {{ $cantidad }}
                                        </span>

                                        <div class="cp-graph-bar"></div>

                                        <span class="cp-graph-time">
                                            {{ $punto['hora'] }}
                                        </span>

                                    </div>

                                @endforeach

                            </div>

                        </div>

                    </div>

                </div>

                {{-- TÍTULO DEL EJE HORIZONTAL --}}
                <div class="cp-graph-x-title">
                    Hora del día
                </div>

            </div>

        </div>

    @else

        <div class="cp-chart-empty">
            <strong>
                Sin comensales registrados hoy
            </strong>

            <p>
                La actividad aparecerá cuando se registren
                atenciones para los servicios de esta fecha.
            </p>
        </div>

    @endif

</section>


        {{-- INFORMACIÓN COMPLEMENTARIA --}}

        <section class="cp-bottom-grid">

            <article class="cp-info-card">

                <span class="cp-kicker">

                    DISTRIBUCIÓN DEL COMEDOR

                </span>

                <h3>Control de mesas</h3>

                <div class="cp-info-number">

                    <strong>{{ $mesasTotales }}</strong>

                    <span>Mesas registradas<br>en esta sede</span>

                </div>

                <div class="cp-info-divider"></div>

                <p>

                    Consulta la distribución y disponibilidad

                    desde Mesas y zonas, en el menú lateral.

                </p>

            </article>

            <article class="cp-info-card cp-info-card-blue">

                <span class="cp-kicker">

                    SUPERVISIÓN DE SERVICIO

                </span>

                <h3>Atenciones abiertas</h3>

                <div class="cp-info-number">

                    <strong>{{ $atencionesAbiertas }}</strong>

                    <span>Atenciones activas<br>o pendientes</span>

                </div>

                <div class="cp-info-divider"></div>

                <p>

                    Supervisa las atenciones y el estado

                    del servicio desde el menú lateral.

                </p>

            </article>

        </section>

    {{-- ================================================

         SERVICIOS

    ================================================= --}}

    @elseif($modulo === 'servicios')

        <section class="cp-workspace">

            <div class="cp-workspace-heading">

                <div>

                    <span class="cp-kicker">PROGRAMACIÓN DIARIA</span>

                    <h2>Servicios del día</h2>

                    <p>

                        Consulta horarios, capacidad y estado

                        de los servicios programados.

                    </p>

                </div>

                <span class="cp-count">

                    {{ $servicios->count() }} servicios

                </span>

            </div>

            <div class="cp-table-container">

                <table class="cp-table">

                    <thead>

                        <tr>

                            <th>Servicio</th>

                            <th>Inicio</th>

                            <th>Fin</th>

                            <th>Capacidad</th>

                            <th>Estado</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($servicios as $servicio)

                            <tr>

                                <td class="cp-strong">

                                    {{ $servicio->nombre }}

                                </td>

                                <td>

                                    {{ $servicio->inicio_hora ?? '—' }}

                                </td>

                                <td>

                                    {{ $servicio->fin_hora ?? '—' }}

                                </td>

                                <td>

                                    {{ $servicio->capacidad ?? 0 }}

                                </td>

                                <td>

                                    <span class="cp-status">

                                        {{ $servicio->estado ?? 'Sin estado' }}

                                    </span>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="5" class="cp-empty">

                                    No hay servicios programados para hoy.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

    {{-- ================================================

         MESAS Y ZONAS

    ================================================= --}}

    @elseif($modulo === 'mesas')

        <section class="cp-workspace">

            <div class="cp-workspace-heading">

                <div>

                    <span class="cp-kicker">

                        ORGANIZACIÓN DEL PISO

                    </span>

                    <h2>Mesas y zonas</h2>

                    <p>

                        Distribución y estado físico de las mesas.

                    </p>

                </div>

                <span class="cp-count">

                    {{ $mesas->count() }} mesas

                </span>

            </div>

            <div class="cp-zones">

                @forelse($zonas as $zona)

                    <span>{{ $zona->nombre }}</span>

                @empty

                    <p>No hay zonas registradas.</p>

                @endforelse

            </div>

            <div class="cp-table-container">

                <table class="cp-table">

                    <thead>

                        <tr>

                            <th>Mesa</th>

                            <th>Zona</th>

                            <th>Capacidad</th>

                            <th>Estado físico</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($mesas as $mesa)

                            <tr>

                                <td class="cp-strong">

                                    Mesa {{ $mesa->numero }}

                                </td>

                                <td>{{ $mesa->zona }}</td>

                                <td>

                                    {{ $mesa->capacidad ?? 0 }}

                                </td>

                                <td>

                                    <span class="cp-status">

                                        {{ $mesa->estado_fisico ?? 'Sin estado' }}

                                    </span>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="4" class="cp-empty">

                                    No hay mesas registradas.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

    {{-- ================================================

         ASIGNACIÓN DE MESEROS

    ================================================= --}}

    @elseif($modulo === 'asignaciones')

        <section class="cp-workspace">

            <div class="cp-workspace-heading">

                <div>

                    <span class="cp-kicker">

                        COORDINACIÓN DEL PERSONAL

                    </span>

                    <h2>Asignación de meseros</h2>

                    <p>

                        Asigna y retira meseros de las zonas

                        correspondientes.

                    </p>

                </div>

                <span class="cp-count">

                    {{ $asignaciones->count() }} activas

                </span>

            </div>

            <form

                method="POST"

                action="{{ route('capitan.asignaciones.store') }}"

                class="cp-form"

            >

                @csrf

                <label>

                    Zona de servicio

                    <select name="id_zona" required>

                        <option value="">

                            Selecciona una zona

                        </option>

                        @foreach($zonas as $zona)

                            <option

                                value="{{ $zona->id_zona }}"

                                @selected(old('id_zona') == $zona->id_zona)

                            >

                                {{ $zona->nombre }}

                            </option>

                        @endforeach

                    </select>

                </label>

                <label>

                    Mesero

                    <select name="id_usuario_mesero" required>

                        <option value="">

                            Selecciona un mesero

                        </option>

                        @foreach($meseros as $mesero)

                            <option

                                value="{{ $mesero->id_usuario }}"

                                @selected(

                                    old('id_usuario_mesero') == $mesero->id_usuario

                                )

                            >

                                {{ trim(

                                    $mesero->nombre . ' ' .

                                    $mesero->apellido_p . ' ' .

                                    $mesero->apellido_m

                                ) }}

                            </option>

                        @endforeach

                    </select>

                </label>

                <button

                    type="submit"

                    class="cp-button"

                    @disabled($zonas->isEmpty() || $meseros->isEmpty())

                >

                    Asignar mesero

                    <span aria-hidden="true">↗</span>

                </button>

            </form>

            <h3 class="cp-subheading">

                Asignaciones activas

            </h3>

            <div class="cp-table-container">

                <table class="cp-table">

                    <thead>

                        <tr>

                            <th>Mesero</th>

                            <th>Zona</th>

                            <th>Fecha de asignación</th>

                            <th>Acción</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($asignaciones as $asignacion)

                            <tr>

                                <td class="cp-strong">

                                    {{ trim(

                                        $asignacion->nombre . ' ' .

                                        $asignacion->apellido_p . ' ' .

                                        $asignacion->apellido_m

                                    ) }}

                                </td>

                                <td>{{ $asignacion->zona }}</td>

                                <td>

                                    {{ $asignacion->fecha_inicio }}

                                </td>

                                <td>

                                    <form

                                        method="POST"

                                        action="{{ route(

                                            'capitan.asignaciones.retirar',

                                            $asignacion->id_zona_mesero

                                        ) }}"

                                    >

                                        @csrf

                                        @method('PATCH')

                                        <button

                                            type="submit"

                                            class="cp-button-outline"

                                        >

                                            Retirar

                                        </button>

                                    </form>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="4" class="cp-empty">

                                    No hay asignaciones activas.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

    {{-- ================================================

         ATENCIONES

    ================================================= --}}

    @elseif($modulo === 'atenciones')

        <section class="cp-workspace">

            <div class="cp-workspace-heading">

                <div>

                    <span class="cp-kicker">

                        SEGUIMIENTO DEL SERVICIO

                    </span>

                    <h2>Atenciones</h2>

                    <p>Consulta las atenciones de la sede.</p>

                </div>

                <span class="cp-count">

                    {{ $atenciones->count() }} registros

                </span>

            </div>

            <div class="cp-table-container">

                <table class="cp-table">

                    <thead>

                        <tr>

                            <th>Atención</th>

                            <th>Servicio</th>

                            <th>Mesa</th>

                            <th>Comensales</th>

                            <th>Estado</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($atenciones as $atencion)

                            <tr>

                                <td class="cp-strong">

                                    #{{ $atencion->id_atencion }}

                                </td>

                                <td>{{ $atencion->servicio }}</td>

                                <td>{{ $atencion->mesa }}</td>

                                <td>{{ $atencion->comensales ?? 0 }}</td>

                                <td>

                                    <span class="cp-status">

                                        {{ $atencion->estado ?? 'Sin estado' }}

                                    </span>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="5" class="cp-empty">

                                    No hay atenciones registradas.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

    {{-- ================================================

         COMIDAS

         SE CONSERVAN COMANDAS Y SU BASE DE DATOS

    ================================================= --}}

    @elseif($modulo === 'comandas')

        <section class="cp-workspace">

            <div class="cp-workspace-heading">

                <div>

                    <span class="cp-kicker">

                        CONTROL DE COMIDAS

                    </span>

                    <h2>Comidas del servicio</h2>

                    <p>

                        Consulta el seguimiento y preparación

                        de los pedidos.

                    </p>

                </div>

                <span class="cp-count">

                    {{ $comandas->count() }} pedidos

                </span>

            </div>

            <div class="cp-table-container">

                <table class="cp-table">

                    <thead>

                        <tr>

                            <th>Pedido</th>

                            <th>Atención</th>

                            <th>Estado</th>

                            <th>Apertura</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($comandas as $comanda)

                            <tr>

                                <td class="cp-strong">

                                    #{{ $comanda->id_comanda }}

                                </td>

                                <td>

                                    #{{ $comanda->id_atencion }}

                                </td>

                                <td>

                                    <span class="cp-status">

                                        {{ $comanda->estado ?? 'Sin estado' }}

                                    </span>

                                </td>

                                <td>

                                    {{ $comanda->fecha_apertura ?? '—' }}

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="4" class="cp-empty">

                                    No hay comidas registradas.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

    {{-- ================================================

         GRUPOS Y ENTREGAS

    ================================================= --}}

    @elseif($modulo === 'grupos')

        <section class="cp-workspace">

            <div class="cp-workspace-heading">

                <div>

                    <span class="cp-kicker">

                        COORDINACIÓN DEL SERVICIO

                    </span>

                    <h2>Grupos programados</h2>

                    <p>

                        Horarios y comensales previstos.

                    </p>

                </div>

                <span class="cp-count">

                    {{ $grupos->count() }} grupos

                </span>

            </div>

            <div class="cp-table-container">

                <table class="cp-table">

                    <thead>

                        <tr>

                            <th>Grupo</th>

                            <th>Servicio</th>

                            <th>Comensales previstos</th>

                            <th>Horario</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($grupos as $grupo)

                            <tr>

                                <td class="cp-strong">

                                    {{ $grupo->nombre ?? 'Sin nombre' }}

                                </td>

                                <td>

                                    {{ $grupo->servicio }}

                                </td>

                                <td>

                                    {{ $grupo->comensales_pre ?? 0 }}

                                </td>

                                <td>

                                    {{ $grupo->inicio_hora ?? '—' }}

                                    –

                                    {{ $grupo->fin_hora ?? '—' }}

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="4" class="cp-empty">

                                    No hay grupos registrados.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

    {{-- ================================================

         INCIDENCIAS

    ================================================= --}}

    @elseif($modulo === 'incidencias')

        <section class="cp-workspace">

            <div class="cp-workspace-heading">

                <div>

                    <span class="cp-kicker">

                        SEGUIMIENTO OPERATIVO

                    </span>

                    <h2>Incidencias del servicio</h2>

                    <p>

                        Documenta las situaciones detectadas

                        durante la atención.

                    </p>

                </div>

                <span class="cp-count">

                    {{ $incidencias->count() }} registros

                </span>

            </div>

            <form

                method="POST"

                action="{{ route('capitan.incidencias.store') }}"

                class="cp-form cp-form-column"

            >

                @csrf

                <label>

                    Descripción de la incidencia

                    <textarea

                        name="descripcion"

                        rows="4"

                        minlength="10"

                        maxlength="2000"

                        placeholder="Describe lo ocurrido durante el servicio..."

                        required

                    >{{ old('descripcion') }}</textarea>

                </label>

                <button

                    class="cp-button"

                    type="submit"

                >

                    Registrar incidencia

                    <span aria-hidden="true">↗</span>

                </button>

            </form>

            <h3 class="cp-subheading">

                Incidencias registradas

            </h3>

            <div class="cp-table-container">

                <table class="cp-table">

                    <thead>

                        <tr>

                            <th>Folio</th>

                            <th>Descripción</th>

                            <th>Estado</th>

                            <th>Fecha</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($incidencias as $incidencia)

                            <tr>

                                <td class="cp-strong">

                                    #{{ $incidencia->id_incidencia }}

                                </td>

                                <td>

                                    {{ $incidencia->descripcion }}

                                </td>

                                <td>

                                    <span class="cp-status">

                                        {{ $incidencia->estado ?? 'Sin estado' }}

                                    </span>

                                </td>

                                <td>

                                    {{ $incidencia->fecha }}

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="4" class="cp-empty">

                                    No hay incidencias registradas.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>

    {{-- ================================================

         REPORTES

    ================================================= --}}

    @elseif($modulo === 'reportes')

        <section class="cp-workspace">

            <div class="cp-workspace-heading">

                <div>

                    <span class="cp-kicker">

                        INDICADORES OPERATIVOS

                    </span>

                    <h2>Resumen de servicio</h2>

                    <p>

                        Información de {{ $sede->nombre }}.

                    </p>

                </div>

                <span class="cp-count">

                    Sede actual

                </span>

            </div>

            <div class="cp-reports">

                @foreach([
                    ['Servicios programados hoy', $serviciosHoy, $fotos['servicios'], '01 / SERVICIOS'],
                    ['Mesas registradas', $mesasTotales, $fotos['hero'], '02 / MESAS'],
                    ['Meseros de la sede', $meserosTotales, $fotos['meseros'], '03 / PERSONAL'],
                    ['Atenciones abiertas', $atencionesAbiertas, $fotos['comensales'], '04 / ATENCIONES'],
                    ['Incidencias abiertas', $incidenciasAbiertas, $fotos['incidencias'], '05 / INCIDENCIAS'],
                ] as [$etiqueta, $cantidad, $imagen, $folio])
                    <article class="cp-report-item" style="--cp-report-image: url('{{ $imagen }}');">
                        <span class="cp-report-code">{{ $folio }}</span>
                        <span class="cp-report-name">{{ $etiqueta }}</span>
                        <strong>{{ $cantidad }}</strong>
                        <small>Comedor Industrial · {{ $sede->nombre }}</small>
                    </article>
                @endforeach

            </div>

        </section>

    @endif

</div>

@endsection
