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

                    {{ \Carbon\Carbon::parse($fechaOperacion ?? now())->translatedFormat('d/m/Y') }}

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

            <section class="cp-workspace cp-live-dashboard">

                <div class="cp-workspace-heading">
                    <div>
                        <span class="cp-kicker">SUPERVISIÓN OPERATIVA</span>
                        <h2>Servicio en vivo</h2>
                        <p>Control de mesas, zonas, comandas y personal de la sede.</p>
                    </div>
                    <span class="cp-count">
                    {{ $servicioEnVivo->nombre ?? 'Sin servicio en curso' }}
                </span>
                </div>

                <form method="GET" action="{{ route('capitan.index') }}" class="cp-live-date-form">
                    <input type="hidden" name="modulo" value="servicios">
                    <label for="cp-live-date">
                        Fecha de operación
                        <input id="cp-live-date" type="date" name="fecha"
                               value="{{ $fechaOperacion }}" required>
                    </label>
                    <button type="submit" class="cp-button">Consultar fecha</button>
                    <a href="{{ route('capitan.index', ['modulo' => 'servicios']) }}"
                       class="cp-button-outline">Ver hoy</a>
                </form>

                @unless($servicioEnVivo)
                    <div class="cp-live-empty" role="status">
                        <strong>No hay un servicio en curso para esta fecha.</strong>
                        <p>Se conserva visible el inventario completo de mesas, las zonas y las asignaciones de meseros. Los indicadores de atención corresponden a las atenciones abiertas de la fecha seleccionada.</p>
                    </div>
                @endunless

                <div class="cp-live-stats" aria-label="Indicadores del servicio">
                    <article class="cp-info-card">
                        <span class="cp-kicker">MESAS REGISTRADAS</span>
                        <div class="cp-info-number"><strong>{{ $mesasServicio->count() }}</strong><span>Inventario de la sede</span></div>
                    </article>
                    <article class="cp-info-card">
                        <span class="cp-kicker">DISPONIBLES</span>
                        <div class="cp-info-number"><strong>{{ $mesasDisponiblesServicio }}</strong><span>Listas para atención</span></div>
                    </article>
                    <article class="cp-info-card">
                        <span class="cp-kicker">OCUPADAS</span>
                        <div class="cp-info-number"><strong>{{ $mesasOcupadasServicio }}</strong><span>Con atención abierta</span></div>
                    </article>
                    <article class="cp-info-card">
                        <span class="cp-kicker">ATENDIDAS</span>
                        <div class="cp-info-number"><strong>{{ $mesasAtendidasServicio }}</strong><span>Con mesero responsable</span></div>
                    </article>
                    <article class="cp-info-card">
                        <span class="cp-kicker">PENDIENTES DE PAGO</span>
                        <div class="cp-info-number"><strong>{{ $mesasPendientesPagoServicio }}</strong><span>Esperando cobro</span></div>
                    </article>
                </div>

                <section class="cp-live-section">
                    <div class="cp-section-heading">
                        <div>
                            <span class="cp-kicker">DISTRIBUCIÓN DEL COMEDOR</span>
                            <h2>Ocupación por zona</h2>
                            <p>Porcentaje de mesas con atención abierta en cada zona.</p>
                        </div>
                    </div>

                    <div class="cp-live-zone-grid">
                        @forelse($resumenZonasServicio as $zona)
                            @php
                                $porcentajeOcupacion = $zona->total > 0
                                    ? (int) round(($zona->ocupadas / $zona->total) * 100)
                                    : 0;
                            @endphp
                            <article class="cp-live-zone-card">
                                <div class="cp-live-zone-heading">
                                    <h3>{{ $zona->zona }}</h3>
                                    <strong>{{ $zona->total }} mesas</strong>
                                </div>
                                <div class="cp-live-zone-progress"
                                     role="progressbar"
                                     aria-label="Ocupación de {{ $zona->zona }}"
                                     aria-valuemin="0" aria-valuemax="100"
                                     aria-valuenow="{{ $porcentajeOcupacion }}">
                                    <span style="width: {{ $porcentajeOcupacion }}%"></span>
                                </div>
                                <div class="cp-live-zone-details">
                                    <span><strong>{{ $zona->disponibles }}</strong>Disponibles</span>
                                    <span><strong>{{ $zona->ocupadas }}</strong>Ocupadas</span>
                                    <span><strong>{{ $zona->por_cobrar }}</strong>Por cobrar</span>
                                </div>
                                <small class="cp-live-zone-percent">{{ $porcentajeOcupacion }}% de ocupación</small>
                            </article>
                        @empty
                            <div class="cp-empty">No hay zonas con mesas registradas.</div>
                        @endforelse
                    </div>
                </section>

                <section class="cp-live-section">
                    <div class="cp-section-heading">
                        <div>
                            <span class="cp-kicker">CONTROL OPERATIVO</span>
                            <h2>Estado de las mesas</h2>
                            <p>Consulta por mesa, zona, mesero o estado de atención.</p>
                        </div>
                        <span class="cp-count">{{ $mesasServicio->count() }} mesas</span>
                    </div>

                    <div class="cp-live-filters">
                        <label class="cp-live-search-label">
                            <span>Buscar</span>
                            <input type="search" id="cp-live-search" placeholder="Mesa, zona o mesero...">
                        </label>
                        <label>
                            <span>Zona</span>
                            <select id="cp-live-zone-filter">
                                <option value="">Todas las zonas</option>
                                @foreach($resumenZonasServicio as $zona)
                                    <option value="{{ mb_strtolower($zona->zona) }}">{{ $zona->zona }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Mesero</span>
                            <select id="cp-live-waiter-filter">
                                <option value="">Todos los meseros</option>
                                @foreach($meserosServicio as $mesero)
                                    <option value="{{ $mesero->id_usuario }}">
                                        {{ trim($mesero->nombre.' '.$mesero->apellido_p) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Estado</span>
                            <select id="cp-live-status-filter">
                                <option value="">Todos los estados</option>
                                <option value="disponible">Disponible</option>
                                <option value="ocupada">Ocupada</option>
                                <option value="por-cobrar">Por cobrar</option>
                                <option value="fuera-servicio">Fuera de servicio</option>
                            </select>
                        </label>
                    </div>

                    <div class="cp-table-container">
                        <table class="cp-table" id="cp-live-tables-table">
                            <thead>
                            <tr>
                                <th>Mesa</th><th>Zona</th><th>Mesero</th>
                                <th>Comensales</th><th>Estado</th><th>Comanda</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($mesasServicio as $mesa)
                                @php
                                    $estadoFisico = mb_strtolower(trim((string) $mesa->estado_fisico));
                                    $estadoAtencion = mb_strtolower(trim((string) $mesa->atencion_estado));

                                    if ($estadoAtencion === 'pendiente de pago') {
                                        $estadoVisible = 'Por cobrar';
                                        $estadoFiltro = 'por-cobrar';
                                    } elseif ($mesa->id_atencion !== null) {
                                        $estadoVisible = 'Ocupada';
                                        $estadoFiltro = 'ocupada';
                                    } elseif ($estadoFisico === 'disponible') {
                                        $estadoVisible = 'Disponible';
                                        $estadoFiltro = 'disponible';
                                    } else {
                                        $estadoVisible = $mesa->estado_fisico ?: 'Fuera de servicio';
                                        $estadoFiltro = 'fuera-servicio';
                                    }
                                @endphp
                                <tr data-cp-live-row
                                    data-zone="{{ mb_strtolower($mesa->zona) }}"
                                    data-waiter="{{ $mesa->id_usuario_mesero ?? '' }}"
                                    data-status="{{ $estadoFiltro }}">
                                    <td class="cp-strong">M-{{ str_pad($mesa->numero, 2, '0', STR_PAD_LEFT) }}</td>
                                    <td>{{ $mesa->zona }}</td>
                                    <td>{{ $mesa->mesero ?: 'Sin asignar' }}</td>
                                    <td>{{ $mesa->comensales ?? '—' }}</td>
                                    <td><span class="cp-status">{{ $estadoVisible }}</span></td>
                                    <td>{{ $mesa->comanda_estado ?: ($mesa->id_atencion ? 'Sin comanda' : 'Sin atención') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="cp-empty">No hay mesas registradas en esta sede.</td></tr>
                            @endforelse
                            <tr id="cp-live-no-results" hidden>
                                <td colspan="6" class="cp-empty">No se encontraron mesas con esos filtros.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="cp-live-section">
                    <div class="cp-section-heading">
                        <div>
                            <span class="cp-kicker">PERSONAL OPERATIVO</span>
                            <h2>Supervisión de meseros</h2>
                            <p>Meseros asignados, sus zonas y las mesas que atienden.</p>
                        </div>
                        <span class="cp-count">{{ $meserosServicio->count() }} meseros</span>
                    </div>

                    <div class="cp-table-container">
                        <table class="cp-table">
                            <thead><tr><th>Mesero</th><th>Zona asignada</th><th>Atenciones</th><th>Mesas</th><th>Estado</th></tr></thead>
                            <tbody>
                            @forelse($meserosServicio as $mesero)
                                <tr>
                                    <td class="cp-strong">{{ trim($mesero->nombre.' '.$mesero->apellido_p.' '.$mesero->apellido_m) }}</td>
                                    <td>{{ $mesero->zona }}</td>
                                    <td>{{ $mesero->atenciones }}</td>
                                    <td>
                                        {{ $mesero->mesas->map(fn ($numero) => 'M-'.str_pad($numero, 2, '0', STR_PAD_LEFT))->join(' · ') ?: 'Sin mesas activas' }}
                                    </td>
                                    <td><span class="cp-status">{{ $mesero->atenciones > 0 ? 'En operación' : 'Sin atención' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="cp-empty">No hay meseros con asignaciones activas en esta sede.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="cp-live-section">
                    <div class="cp-section-heading">
                        <div>
                            <span class="cp-kicker">INVENTARIO DEL PISO</span>
                            <h2>Resumen de zonas</h2>
                        </div>
                    </div>
                    <div class="cp-table-container">
                        <table class="cp-table">
                            <thead><tr><th>Zona</th><th>Total</th><th>Disponibles</th><th>Ocupadas</th><th>Por cobrar</th></tr></thead>
                            <tbody>
                            @forelse($resumenZonasServicio as $zona)
                                <tr>
                                    <td class="cp-strong">{{ $zona->zona }}</td>
                                    <td>{{ $zona->total }}</td>
                                    <td>{{ $zona->disponibles }}</td>
                                    <td>{{ $zona->ocupadas }}</td>
                                    <td>{{ $zona->por_cobrar }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="cp-empty">No hay zonas registradas.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

            </section>

            {{-- AGENDA DE SERVICIOS DE LA FECHA --}}
            <section class="cp-workspace cp-live-agenda">
                <div class="cp-workspace-heading">
                    <div>
                        <span class="cp-kicker">PROGRAMACIÓN</span>
                        <h2>Servicios de la fecha</h2>
                        <p>Consulta los horarios, capacidad y estado operativo.</p>
                    </div>
                    <span class="cp-count">{{ $servicios->count() }} servicios</span>
                </div>
                <div class="cp-table-container">
                    <table class="cp-table">
                        <thead><tr><th>Servicio</th><th>Inicio</th><th>Fin</th><th>Capacidad</th><th>Estado</th></tr></thead>
                        <tbody>
                        @forelse($servicios as $servicio)
                            <tr>
                                <td class="cp-strong">{{ $servicio->nombre }}</td>
                                <td>{{ $servicio->inicio_hora ? substr($servicio->inicio_hora, 0, 5) : '—' }}</td>
                                <td>{{ $servicio->fin_hora ? substr($servicio->fin_hora, 0, 5) : '—' }}</td>
                                <td>{{ $servicio->capacidad ?? 0 }}</td>
                                <td><span class="cp-status">{{ $servicio->estado ?? 'Sin estado' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="cp-empty">No hay servicios registrados para esta fecha.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <style>
                .cp-live-dashboard{display:flex;flex-direction:column;gap:22px}
                .cp-live-date-form,.cp-live-filters{display:flex;align-items:end;flex-wrap:wrap;gap:12px;padding:16px;border:1px solid #dce8f4;border-radius:14px;background:#fff}
                .cp-live-date-form label,.cp-live-filters label{display:flex;flex-direction:column;gap:6px;color:#536782;font-size:12px;font-weight:700}
                .cp-live-date-form input,.cp-live-filters input,.cp-live-filters select{min-height:40px;padding:8px 11px;border:1px solid #dce5ef;border-radius:9px;background:#fff;color:#102b4d}
                .cp-live-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:13px}
                .cp-live-stats .cp-info-card{min-width:0;padding:18px}
                .cp-live-section{display:flex;flex-direction:column;gap:15px;margin-top:8px}
                .cp-live-zone-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}
                .cp-live-zone-card{padding:19px;border:1px solid #dce8f4;border-radius:14px;background:#fff}
                .cp-live-zone-heading{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:15px}
                .cp-live-zone-heading h3{margin:0;color:#102b4d;font-size:16px}
                .cp-live-zone-heading strong{color:#1479e8;font-size:13px}
                .cp-live-zone-progress{height:9px;overflow:hidden;border-radius:20px;background:#e8eff7}
                .cp-live-zone-progress span{display:block;height:100%;border-radius:inherit;background:#1479e8}
                .cp-live-zone-details{display:flex;justify-content:space-between;gap:8px;margin-top:15px}
                .cp-live-zone-details span{display:flex;flex-direction:column;gap:4px;color:#75869b;font-size:11px}
                .cp-live-zone-details strong{color:#102b4d;font-size:17px}
                .cp-live-zone-percent{display:block;margin-top:12px;color:#64748b;font-size:11px}
                .cp-live-filters{align-items:end}
                .cp-live-filters label{flex:1;min-width:145px}
                .cp-live-filters .cp-live-search-label{flex:2;min-width:220px}
                .cp-live-filters input,.cp-live-filters select{width:100%;box-sizing:border-box}
                .cp-live-dashboard [hidden]{display:none!important}
                .cp-live-empty{padding:32px;border:1px dashed #cbd8e6;border-radius:14px;background:#fff;text-align:center}
                .cp-live-empty strong{display:block;color:#102b4d;font-size:17px}
                .cp-live-empty p{margin:8px 0 0;color:#64748b}
                @media(max-width:1100px){.cp-live-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.cp-live-zone-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
                @media(max-width:650px){.cp-live-stats,.cp-live-zone-grid{grid-template-columns:1fr}.cp-live-date-form,.cp-live-filters{align-items:stretch;flex-direction:column}.cp-live-filters label,.cp-live-filters .cp-live-search-label{width:100%;min-width:0}}
            </style>

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

                        Servicio

                        <select name="id_servicio" required>

                            <option value="">Selecciona un servicio</option>

                            @foreach($serviciosAsignables as $servicio)

                                <option value="{{ $servicio->id_servicio }}" @selected(old('id_servicio') == $servicio->id_servicio)>{{ $servicio->nombre }} · {{ $servicio->fecha }} · {{ $servicio->estado }}</option>

                            @endforeach

                        </select>

                    </label>

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

    @if(($modulo ?? '') === 'servicios')
        <script>
            (() => {
                const table = document.getElementById('cp-live-tables-table');
                const search = document.getElementById('cp-live-search');
                const zone = document.getElementById('cp-live-zone-filter');
                const waiter = document.getElementById('cp-live-waiter-filter');
                const status = document.getElementById('cp-live-status-filter');
                const noResults = document.getElementById('cp-live-no-results');

                if (!table || !search || !zone || !waiter || !status) return;

                const rows = Array.from(table.querySelectorAll('tbody tr[data-cp-live-row]'));

                const applyFilters = () => {
                    const query = search.value.trim().toLocaleLowerCase();
                    const selectedZone = zone.value;
                    const selectedWaiter = waiter.value;
                    const selectedStatus = status.value;
                    let visible = 0;

                    rows.forEach((row) => {
                        const text = row.innerText.toLocaleLowerCase();
                        const matches = (!query || text.includes(query))
                            && (!selectedZone || row.dataset.zone === selectedZone)
                            && (!selectedWaiter || row.dataset.waiter === selectedWaiter)
                            && (!selectedStatus || row.dataset.status === selectedStatus);

                        row.hidden = !matches;
                        if (matches) visible++;
                    });

                    if (noResults) noResults.hidden = visible !== 0;
                };

                [search, zone, waiter, status].forEach((control) => {
                    control.addEventListener('input', applyFilters);
                    control.addEventListener('change', applyFilters);
                });
            })();
        </script>
    @endif
@endsection
