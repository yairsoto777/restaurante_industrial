
@extends('layouts.app')

@section('title', 'Servicio en vivo')

@section('content')

    @php
        /*
        |--------------------------------------------------------------------------
        | Datos del dashboard
        |--------------------------------------------------------------------------
        | Se utilizan las colecciones que ya proporciona el controlador.
        | No se generan registros ficticios.
        */

        $tableData = collect($tables ?? []);
        $waiterData = collect($waiters ?? []);
        $zoneData = collect($zones ?? []);

        $totalTables = $tableData->count();
        $totalZones = $zoneData->count();
        $totalWaiters = $waiterData->count();

        $attentionStates = [
            'En atención',
            'Pendiente de pago',
            'Cerrada',
            'Cancelada'
        ];

        $openAttentions = $tableData->where(
            'atencion_estado',
            'En atención'
        )->count();

        $pendingAttention = $tableData->where(
            'atencion_estado',
            'Pendiente de pago'
        )->count();

        $closedAttentions = $tableData->where(
            'atencion_estado',
            'Cerrada'
        )->count();

        $cancelledAttentions = $tableData->where(
            'atencion_estado',
            'Cancelada'
        )->count();

        $occupiedTables = $openAttentions + $pendingAttention;

        $withoutAttention = max(0, $totalTables - $occupiedTables);

        $paymentCount = isset($pendingPayments)
            ? (int) $pendingPayments
            : $pendingAttention;

        $totalDiners = $tableData
            ->filter(fn($table) => in_array(
                $table->atencion_estado,
                ['En atención', 'Pendiente de pago'],
                true
            ))
            ->sum(fn($table) => (int) ($table->comensales ?? 0));

        $zoneGroups = $tableData->groupBy(
            fn($table) => $table->zona ?: 'Sin zona'
        );

        $totalComandas = $tableData->filter(
            fn($table) => !empty($table->comanda_estado)
        )->count();

        $commandStates = [
            'Pendiente',
            'En preparación',
            'Lista',
            'Pagada'
        ];

        $commandCounts = [];

        foreach ($commandStates as $state) {
            $commandCounts[$state] = $tableData
                ->where('comanda_estado', $state)
                ->count();
        }

        $totalAttentionStates = $openAttentions
            + $pendingAttention
            + $closedAttentions
            + $cancelledAttentions;

        $percentage = fn($value, $total) =>
            $total > 0 ? round(($value / $total) * 100, 1) : 0;

        $attentionPercentage = $percentage(
            $openAttentions,
            $totalAttentionStates
        );

        $paymentPercentage = $percentage(
            $pendingAttention,
            $totalAttentionStates
        );

        $closedPercentage = $percentage(
            $closedAttentions,
            $totalAttentionStates
        );

        $cancelledPercentage = $percentage(
            $cancelledAttentions,
            $totalAttentionStates
        );

        $activeServiceDate = \Carbon\Carbon::parse($date)
            ->locale('es')
            ->translatedFormat('l d \d\e F \d\e Y');

    @endphp

    <div class="live-dashboard">

        {{-- ENCABEZADO --}}
        <section class="live-heading">
            <div>
            <span class="live-eyebrow">
                <span class="live-dot"></span>
                {{ $site->nombre }} / OPERACIÓN DE PISO
            </span>

                <h1>Servicio en vivo</h1>

                <p>
                    Centro de supervisión de mesas, zonas, meseros,
                    comandas y cuentas del comedor.
                </p>
            </div>

            <div class="live-heading-date">
                <span>FECHA DE OPERACIÓN</span>
                <strong>{{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</strong>
                <small>{{ ucfirst($activeServiceDate) }}</small>
            </div>
        </section>

        {{-- FILTROS --}}
        <section class="live-card live-filter-card">

            <div class="live-section-heading">
                <div>
                    <span class="live-kicker">CONSULTA OPERATIVA</span>
                    <h2>Filtros de búsqueda</h2>
                    <p>Localiza rápidamente la información del servicio.</p>
                </div>

                <span class="live-filter-count">
                {{ $totalTables }} mesas encontradas
            </span>
            </div>

            <form
                method="GET"
                action="{{ route('services.live') }}"
                class="live-filter-grid"
            >

                <div class="live-field">
                    <label>Fecha de operación</label>
                    <input
                        type="date"
                        name="fecha"
                        value="{{ $date }}"
                        required
                    >
                </div>

                <div class="live-field">
                    <label>Zona</label>
                    <select name="zona">
                        <option value="">Todas las zonas</option>

                        @foreach($zoneData as $zone)
                            <option
                                value="{{ $zone->id_zona }}"
                                @selected(($filters['zona'] ?? '') == $zone->id_zona)
                            >
                                {{ $zone->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="live-field">
                    <label>Mesero responsable</label>
                    <select name="mesero">
                        <option value="">Todos los meseros</option>

                        @foreach($waiterData as $waiter)
                            <option
                                value="{{ $waiter->id_usuario }}"
                                @selected(($filters['mesero'] ?? '') == $waiter->id_usuario)
                            >
                                {{ $waiter->nombre }}
                                {{ $waiter->apellido_p }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="live-field">
                    <label>Estado de atención</label>
                    <select name="atencion">
                        <option value="">Todos los estados</option>

                        @foreach($attentionStates as $state)
                            <option
                                value="{{ $state }}"
                                @selected(($filters['atencion'] ?? '') === $state)
                            >
                                {{ $state }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="live-field">
                    <label>Estado de comanda</label>
                    <select name="comanda">
                        <option value="">Todas las comandas</option>

                        @foreach($commandStates as $state)
                            <option
                                value="{{ $state }}"
                                @selected(($filters['comanda'] ?? '') === $state)
                            >
                                {{ $state }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="live-field">
                    <label>Número de mesa</label>
                    <input
                        type="search"
                        name="mesa"
                        value="{{ $filters['mesa'] ?? '' }}"
                        placeholder="Ej. 05"
                    >
                </div>

                <div class="live-filter-actions">
                    <button class="live-btn live-btn-primary" type="submit">
                        Aplicar filtros
                    </button>

                    <a
                        href="{{ route('services.live', ['fecha' => $date]) }}"
                        class="live-btn live-btn-light"
                    >
                        Limpiar
                    </a>
                </div>

            </form>
        </section>

        {{-- SERVICIO ACTIVO --}}
        @if($active)

            <section class="live-service-banner">

                <div class="live-service-info">
                    <span class="live-kicker">SERVICIO ACTIVO</span>

                    <h2>{{ $active->nombre }}</h2>

                    <p>
                    <span>
                        <i class="live-icon-clock"></i>
                        {{ substr($active->inicio_hora, 0, 5) }}
                        -
                        {{ substr($active->fin_hora, 0, 5) }}
                    </span>

                        <span>
                        {{ $active->estado }}
                    </span>
                    </p>
                </div>

                <div class="live-service-status">
                    <span class="live-dot"></span>
                    OPERACIÓN EN CURSO
                </div>

            </section>

            {{-- INDICADORES PRINCIPALES --}}
            <section class="live-kpi-grid">

                <article class="live-kpi">
                    <div class="live-kpi-top">
                        <span>MESAS REGISTRADAS</span>
                        <span class="live-kpi-icon blue">▦</span>
                    </div>

                    <strong>{{ $totalTables }}</strong>

                    <small>Total de mesas en el resultado actual</small>
                </article>

                <article class="live-kpi">
                    <div class="live-kpi-top">
                        <span>MESAS OCUPADAS</span>
                        <span class="live-kpi-icon orange">◉</span>
                    </div>

                    <strong>{{ $occupiedTables }}</strong>

                    <small>Con atención o pendientes de pago</small>
                </article>

                <article class="live-kpi">
                    <div class="live-kpi-top">
                        <span>MESEROS</span>
                        <span class="live-kpi-icon purple">♙</span>
                    </div>

                    <strong>{{ $totalWaiters }}</strong>

                    <small>Meseros incluidos en la consulta</small>
                </article>

                <article class="live-kpi">
                    <div class="live-kpi-top">
                        <span>ATENCIONES ABIERTAS</span>
                        <span class="live-kpi-icon green">✓</span>
                    </div>

                    <strong>{{ $openAttentions }}</strong>

                    <small>Mesas actualmente en atención</small>
                </article>

                <article class="live-kpi">
                    <div class="live-kpi-top">
                        <span>POR COBRAR</span>
                        <span class="live-kpi-icon red">$</span>
                    </div>

                    <strong>{{ $paymentCount }}</strong>

                    <small>Cuentas pendientes de pago</small>
                </article>

            </section>

            {{-- GRÁFICAS --}}
            <section class="live-charts-grid">

                {{-- DISTRIBUCIÓN POR ZONA --}}
                <article class="live-card live-chart-card">

                    <div class="live-section-heading">
                        <div>
                            <span class="live-kicker">DISTRIBUCIÓN</span>
                            <h2>Mesas por zona</h2>
                            <p>Distribución de las mesas encontradas.</p>
                        </div>

                        <span class="live-chart-total">
                        {{ $totalTables }} mesas
                    </span>
                    </div>

                    @forelse($zoneGroups as $zoneName => $zoneTables)

                        <div class="live-bar-item">

                            <div class="live-bar-label">
                                <strong>{{ $zoneName }}</strong>

                                <span>
                                {{ $zoneTables->count() }} mesas
                                ({{ $percentage($zoneTables->count(), $totalTables) }}%)
                            </span>
                            </div>

                            <div class="live-bar-track">
                                <div
                                    class="live-bar-fill"
                                    style="width: {{ $percentage($zoneTables->count(), $totalTables) }}%"
                                ></div>
                            </div>

                        </div>

                    @empty

                        <div class="live-empty-small">
                            No hay mesas registradas para esta consulta.
                        </div>

                    @endforelse

                </article>

                {{-- ESTADOS DE ATENCIÓN --}}
                <article class="live-card live-chart-card">

                    <div class="live-section-heading">
                        <div>
                            <span class="live-kicker">ESTADOS OPERATIVOS</span>
                            <h2>Estado de las atenciones</h2>
                            <p>Situación de las mesas consultadas.</p>
                        </div>
                    </div>

                    <div class="live-donut-layout">

                        <div
                            class="live-donut"
                            style="background: conic-gradient(
                            #2563eb 0% {{ $attentionPercentage }}%,
                            #f59e0b {{ $attentionPercentage }}% {{ $attentionPercentage + $paymentPercentage }}%,
                            #16a34a {{ $attentionPercentage + $paymentPercentage }}% {{ $attentionPercentage + $paymentPercentage + $closedPercentage }}%,
                            #ef4444 {{ $attentionPercentage + $paymentPercentage + $closedPercentage }}% 100%
                        )"
                        >
                            <div class="live-donut-center">
                                <strong>{{ $totalAttentionStates }}</strong>
                                <span>Atenciones</span>
                            </div>
                        </div>

                        <div class="live-legend">

                            <div>
                                <span class="live-legend-dot blue"></span>
                                <span>En atención</span>
                                <strong>{{ $openAttentions }}</strong>
                            </div>

                            <div>
                                <span class="live-legend-dot orange"></span>
                                <span>Pendiente de pago</span>
                                <strong>{{ $pendingAttention }}</strong>
                            </div>

                            <div>
                                <span class="live-legend-dot green"></span>
                                <span>Cerradas</span>
                                <strong>{{ $closedAttentions }}</strong>
                            </div>

                            <div>
                                <span class="live-legend-dot red"></span>
                                <span>Canceladas</span>
                                <strong>{{ $cancelledAttentions }}</strong>
                            </div>

                        </div>

                    </div>

                </article>

            </section>

            {{-- SEGUIMIENTO DE COMANDAS --}}
            <section class="live-card">

                <div class="live-section-heading">
                    <div>
                        <span class="live-kicker">SEGUIMIENTO DE COCINA</span>
                        <h2>Estado de las comandas</h2>
                        <p>Control de los pedidos registrados en las mesas.</p>
                    </div>

                    <span class="live-chart-total">
                    {{ $totalComandas }} comandas
                </span>
                </div>

                <div class="live-command-grid">

                    <article class="live-command-item">
                        <span class="live-command-indicator pending"></span>
                        <div>
                            <strong>{{ $commandCounts['Pendiente'] }}</strong>
                            <span>Pendientes</span>
                        </div>
                    </article>

                    <article class="live-command-item">
                        <span class="live-command-indicator preparing"></span>
                        <div>
                            <strong>{{ $commandCounts['En preparación'] }}</strong>
                            <span>En preparación</span>
                        </div>
                    </article>

                    <article class="live-command-item">
                        <span class="live-command-indicator ready"></span>
                        <div>
                            <strong>{{ $commandCounts['Lista'] }}</strong>
                            <span>Listas</span>
                        </div>
                    </article>

                    <article class="live-command-item">
                        <span class="live-command-indicator paid"></span>
                        <div>
                            <strong>{{ $commandCounts['Pagada'] }}</strong>
                            <span>Pagadas</span>
                        </div>
                    </article>

                </div>

            </section>

            {{-- MESAS POR ZONA --}}
            <section class="live-card">

                <div class="live-section-heading">
                    <div>
                        <span class="live-kicker">SUPERVISIÓN DEL COMEDOR</span>
                        <h2>Distribución y estado de mesas</h2>
                        <p>
                            Consulta las mesas, responsables y estados de atención.
                        </p>
                    </div>

                    <span class="live-chart-total">
                    {{ $zoneGroups->count() }} zonas
                </span>
                </div>

                <div class="live-table-wrapper">

                    <table class="live-table">

                        <thead>
                        <tr>
                            <th>Mesa</th>
                            <th>Zona</th>
                            <th>Mesero</th>
                            <th>Comensales</th>
                            <th>Atención</th>
                            <th>Comanda</th>
                        </tr>
                        </thead>

                        <tbody>

                        @forelse($tableData as $table)

                            @php
                                $attentionClass = match($table->atencion_estado) {
                                    'En atención' => 'blue',
                                    'Pendiente de pago' => 'orange',
                                    'Cerrada' => 'green',
                                    'Cancelada' => 'red',
                                    default => 'gray'
                                };

                                $commandClass = match($table->comanda_estado) {
                                    'Pendiente' => 'orange',
                                    'En preparación' => 'blue',
                                    'Lista' => 'green',
                                    'Pagada' => 'purple',
                                    default => 'gray'
                                };
                            @endphp

                            <tr>

                                <td>
                                    <strong class="live-table-number">
                                        M-{{ str_pad($table->numero, 2, '0', STR_PAD_LEFT) }}
                                    </strong>
                                </td>

                                <td>{{ $table->zona ?: 'Sin zona' }}</td>

                                <td>{{ $table->mesero ?: 'Sin asignar' }}</td>

                                <td>
                                    {{ $table->comensales ?? 0 }}
                                </td>

                                <td>
                                    <span class="live-status {{ $attentionClass }}">
                                        {{ $table->atencion_estado ?: 'Sin atención' }}
                                    </span>
                                </td>

                                <td>
                                    <span class="live-status {{ $commandClass }}">
                                        {{ $table->comanda_estado ?: 'Sin comanda' }}
                                    </span>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="6" class="live-table-empty">
                                    No existen mesas que coincidan con los filtros.
                                </td>
                            </tr>

                        @endforelse

                        </tbody>

                    </table>

                </div>

            </section>

            {{-- SUPERVISIÓN DE MESEROS --}}
            <section class="live-card">

                <div class="live-section-heading">
                    <div>
                        <span class="live-kicker">PERSONAL OPERATIVO</span>
                        <h2>Supervisión de meseros</h2>
                        <p>
                            Consulta la carga de trabajo y las mesas asignadas.
                        </p>
                    </div>

                    <span class="live-chart-total">
                    {{ $totalWaiters }} meseros
                </span>
                </div>

                <div class="live-waiter-grid">

                    @forelse($waiterData as $waiter)

                        <article class="live-waiter-card">

                            <div class="live-waiter-top">

                                <div class="live-avatar">
                                    {{ strtoupper(substr($waiter->nombre ?? 'M', 0, 1)) }}
                                    {{ strtoupper(substr($waiter->apellido_p ?? '', 0, 1)) }}
                                </div>

                                <div class="live-waiter-name">
                                    <strong>
                                        {{ $waiter->nombre }}
                                        {{ $waiter->apellido_p }}
                                    </strong>

                                    <span>Mesero</span>
                                </div>

                                <span class="live-status {{ $waiter->atenciones ? 'green' : 'gray' }}">
                                {{ $waiter->atenciones ? 'En operación' : 'Sin atención' }}
                            </span>

                            </div>

                            <div class="live-waiter-stats">

                                <div>
                                    <strong>{{ $waiter->atenciones ?? 0 }}</strong>
                                    <span>Atenciones</span>
                                </div>

                                <div>
                                    <strong>{{ $waiter->mesas->count() }}</strong>
                                    <span>Mesas</span>
                                </div>

                            </div>

                            <div class="live-waiter-tables">

                                <span>MESAS ASIGNADAS EN LA CONSULTA</span>

                                <p>
                                    {{ $waiter->mesas->map(
                                        fn($mesa) => 'M-' . str_pad($mesa, 2, '0', STR_PAD_LEFT)
                                    )->join(' · ') ?: 'Sin mesas en este servicio' }}
                                </p>

                            </div>

                        </article>

                    @empty

                        <div class="live-empty-small">
                            No hay meseros registrados en esta consulta.
                        </div>

                    @endforelse

                </div>

            </section>

            {{-- RESUMEN FINAL --}}
            <section class="live-footer-summary">

                <div>
                    <span class="live-kicker">RESUMEN DEL SERVICIO</span>
                    <h2>Estado general de la operación</h2>
                    <p>
                        Información correspondiente a los filtros seleccionados.
                    </p>
                </div>

                <div class="live-footer-numbers">

                    <div>
                        <strong>{{ $totalDiners }}</strong>
                        <span>Comensales en atención</span>
                    </div>

                    <div>
                        <strong>{{ $occupiedTables }}</strong>
                        <span>Mesas con atención activa</span>
                    </div>

                    <div>
                        <strong>{{ $paymentCount }}</strong>
                        <span>Cuentas por cobrar</span>
                    </div>

                </div>

            </section>

        @else

            <section class="live-card live-no-service">

                <div class="live-no-service-icon">◷</div>

                <span class="live-kicker">AGENDA OPERATIVA</span>

                <h2>No hay servicio activo</h2>

                <p>
                    No se encontró un servicio activo para
                    {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}.
                </p>

                <span>
                Selecciona otra fecha o consulta la programación
                de servicios autorizados.
            </span>

            </section>

        @endif

    </div>

    <style>
        .live-dashboard {
            --live-blue: #137cf3;
            --live-navy: #0b2345;
            --live-text: #142d4e;
            --live-muted: #71839e;
            --live-border: #e0e8f2;
            --live-bg: #f4f7fb;
            display: flex;
            flex-direction: column;
            gap: 20px;
            color: var(--live-text);
            min-width: 0;
        }

        .live-dashboard * {
            box-sizing: border-box;
        }

        .live-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 0;
        }

        .live-eyebrow,
        .live-kicker {
            display: block;
            color: var(--live-blue);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.4px;
        }

        .live-eyebrow {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .live-dot {
            width: 9px;
            height: 9px;
            display: inline-block;
            background: #16b981;
            border-radius: 50%;
            box-shadow: 0 0 0 4px #dcfce7;
        }

        .live-heading h1 {
            font-size: clamp(25px, 3vw, 34px);
            font-weight: 850;
            margin: 12px 0 5px;
            color: var(--live-navy);
        }

        .live-heading p,
        .live-section-heading p {
            color: var(--live-muted);
            font-size: 12px;
            margin: 0;
        }

        .live-heading-date {
            text-align: right;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .live-heading-date span {
            font-size: 9px;
            color: var(--live-muted);
            font-weight: 800;
            letter-spacing: 1px;
        }

        .live-heading-date strong {
            color: var(--live-blue);
            font-size: 17px;
        }

        .live-heading-date small {
            color: var(--live-muted);
            font-size: 11px;
        }

        .live-card {
            background: #fff;
            border: 1px solid var(--live-border);
            border-radius: 16px;
            padding: 23px;
            box-shadow: 0 5px 18px rgba(20, 45, 78, .035);
            min-width: 0;
        }

        .live-section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
        }

        .live-section-heading h2 {
            font-size: 19px;
            color: var(--live-navy);
            font-weight: 800;
            margin: 6px 0;
        }

        .live-section-heading p {
            margin-top: 4px;
        }

        .live-filter-count,
        .live-chart-total {
            background: #edf5ff;
            color: #1767c6;
            padding: 9px 13px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .live-filter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
        }

        .live-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .live-field label {
            font-size: 11px;
            font-weight: 750;
            color: #314965;
        }

        .live-field input,
        .live-field select {
            width: 100%;
            height: 43px;
            border: 1px solid #dce5ef;
            border-radius: 8px;
            padding: 0 12px;
            background: #fff;
            color: var(--live-text);
            font-size: 12px;
            outline: none;
            min-width: 0;
        }

        .live-field input:focus,
        .live-field select:focus {
            border-color: var(--live-blue);
            box-shadow: 0 0 0 3px rgba(19,124,243,.09);
        }

        .live-filter-actions {
            display: flex;
            align-items: end;
            gap: 9px;
        }

        .live-btn {
            min-height: 43px;
            border-radius: 8px;
            padding: 0 17px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            font-size: 11px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            border: 0;
        }

        .live-btn-primary {
            background: var(--live-blue);
            color: #fff;
        }

        .live-btn-primary:hover {
            background: #0869d9;
        }

        .live-btn-light {
            background: #f1f5f9;
            color: #425875;
        }

        .live-service-banner {
            background: linear-gradient(115deg, #0b2345, #1759a4);
            border-radius: 16px;
            padding: 24px 27px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            color: #fff;
        }

        .live-service-banner .live-kicker {
            color: #9dccff;
        }

        .live-service-info h2 {
            font-size: 23px;
            font-weight: 850;
            margin: 9px 0;
            color: #fff;
        }

        .live-service-info p {
            display: flex;
            flex-wrap: wrap;
            gap: 17px;
            color: #d8e9ff;
            font-size: 11px;
            margin: 0;
        }

        .live-service-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 15px;
            border: 1px solid rgba(255,255,255,.2);
            background: rgba(255,255,255,.09);
            border-radius: 30px;
            color: white;
            font-size: 10px;
            font-weight: 800;
        }

        .live-kpi-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 13px;
        }

        .live-kpi {
            background: white;
            border: 1px solid var(--live-border);
            border-radius: 13px;
            padding: 18px;
            min-width: 0;
            box-shadow: 0 4px 14px rgba(20,45,78,.025);
        }

        .live-kpi-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 5px;
        }

        .live-kpi-top > span:first-child {
            font-size: 9px;
            color: var(--live-muted);
            font-weight: 800;
            letter-spacing: .5px;
        }

        .live-kpi-icon {
            width: 31px;
            height: 31px;
            border-radius: 9px;
            display: grid;
            place-items: center;
            font-size: 17px;
            font-weight: 800;
        }

        .live-kpi-icon.blue { background: #e9f2ff; color: #137cf3; }
        .live-kpi-icon.orange { background: #fff4e5; color: #e99a19; }
        .live-kpi-icon.purple { background: #f2edff; color: #8156df; }
        .live-kpi-icon.green { background: #e8f9ef; color: #16a36b; }
        .live-kpi-icon.red { background: #fff0f0; color: #e24b4b; }

        .live-kpi > strong {
            display: block;
            font-size: 31px;
            line-height: 1.2;
            color: var(--live-navy);
            margin: 12px 0 5px;
            font-weight: 850;
        }

        .live-kpi > small {
            font-size: 10px;
            color: var(--live-muted);
            line-height: 1.5;
        }

        .live-charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 17px;
        }

        .live-chart-card {
            min-height: 265px;
        }

        .live-bar-item {
            margin: 19px 0;
        }

        .live-bar-label {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 11px;
        }

        .live-bar-label strong {
            color: var(--live-text);
        }

        .live-bar-label span {
            color: var(--live-muted);
            font-size: 10px;
        }

        .live-bar-track {
            height: 9px;
            background: #edf2f8;
            border-radius: 20px;
            overflow: hidden;
        }

        .live-bar-fill {
            height: 100%;
            min-width: 0;
            background: linear-gradient(90deg, #137cf3, #59a8ff);
            border-radius: 20px;
            transition: width .3s ease;
        }

        .live-donut-layout {
            display: flex;
            align-items: center;
            justify-content: space-evenly;
            gap: 20px;
            min-height: 165px;
        }

        .live-donut {
            width: 155px;
            height: 155px;
            flex-shrink: 0;
            border-radius: 50%;
            display: grid;
            place-items: center;
            position: relative;
        }

        .live-donut::before {
            content: "";
            position: absolute;
            width: 108px;
            height: 108px;
            background: white;
            border-radius: 50%;
        }

        .live-donut-center {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .live-donut-center strong {
            font-size: 25px;
            font-weight: 850;
            color: var(--live-navy);
        }

        .live-donut-center span {
            font-size: 10px;
            color: var(--live-muted);
        }

        .live-legend {
            display: flex;
            flex-direction: column;
            gap: 14px;
            min-width: 0;
            flex: 1;
        }

        .live-legend > div {
            display: grid;
            grid-template-columns: 9px 1fr auto;
            align-items: center;
            gap: 8px;
            font-size: 10px;
            color: var(--live-muted);
        }

        .live-legend strong {
            color: var(--live-text);
            font-size: 12px;
        }

        .live-legend-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
        }

        .live-legend-dot.blue { background: #2563eb; }
        .live-legend-dot.orange { background: #f59e0b; }
        .live-legend-dot.green { background: #16a34a; }
        .live-legend-dot.red { background: #ef4444; }

        .live-command-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .live-command-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 17px;
            border: 1px solid var(--live-border);
            border-radius: 11px;
            background: #fbfdff;
        }

        .live-command-indicator {
            width: 11px;
            height: 35px;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .live-command-indicator.pending { background: #f59e0b; }
        .live-command-indicator.preparing { background: #2563eb; }
        .live-command-indicator.ready { background: #16a34a; }
        .live-command-indicator.paid { background: #8156df; }

        .live-command-item strong {
            display: block;
            font-size: 23px;
            font-weight: 850;
            color: var(--live-navy);
        }

        .live-command-item span:last-child {
            display: block;
            font-size: 10px;
            color: var(--live-muted);
        }

        .live-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .live-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }

        .live-table thead {
            background: #f4f7fb;
        }

        .live-table th {
            color: #637a96;
            font-size: 9px;
            text-align: left;
            padding: 14px 12px;
            font-weight: 850;
            letter-spacing: .5px;
        }

        .live-table td {
            padding: 14px 12px;
            font-size: 11px;
            border-bottom: 1px solid #edf1f6;
            color: #536982;
        }

        .live-table tbody tr:hover {
            background: #f9fbff;
        }

        .live-table-number {
            color: #137cf3;
            font-weight: 850;
        }

        .live-status {
            display: inline-flex;
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 800;
            white-space: nowrap;
        }

        .live-status.blue { background: #eaf2ff; color: #2365c5; }
        .live-status.orange { background: #fff4e5; color: #bd790e; }
        .live-status.green { background: #e7f8ed; color: #16834f; }
        .live-status.red { background: #ffeded; color: #d43e3e; }
        .live-status.purple { background: #f1ebff; color: #7547ca; }
        .live-status.gray { background: #eef2f6; color: #697b90; }

        .live-table-empty {
            text-align: center;
            padding: 35px !important;
            color: var(--live-muted) !important;
        }

        .live-waiter-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 13px;
        }

        .live-waiter-card {
            border: 1px solid var(--live-border);
            border-radius: 12px;
            padding: 17px;
            background: #fff;
            min-width: 0;
        }

        .live-waiter-top {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .live-avatar {
            width: 39px;
            height: 39px;
            border-radius: 11px;
            background: #eaf2ff;
            color: #137cf3;
            display: grid;
            place-items: center;
            font-size: 12px;
            font-weight: 850;
            flex-shrink: 0;
        }

        .live-waiter-name {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
            min-width: 90px;
        }

        .live-waiter-name strong {
            font-size: 11px;
            color: var(--live-navy);
        }

        .live-waiter-name span {
            font-size: 9px;
            color: var(--live-muted);
        }

        .live-waiter-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin: 17px 0;
            padding: 12px 0;
            border-top: 1px solid #edf1f6;
            border-bottom: 1px solid #edf1f6;
        }

        .live-waiter-stats > div {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .live-waiter-stats strong {
            font-size: 20px;
            color: var(--live-navy);
        }

        .live-waiter-stats span {
            font-size: 9px;
            color: var(--live-muted);
        }

        .live-waiter-tables > span {
            color: var(--live-muted);
            font-size: 8px;
            font-weight: 850;
            letter-spacing: .6px;
        }

        .live-waiter-tables p {
            font-size: 10px;
            color: #42617f;
            line-height: 1.7;
            margin-bottom: 0;
        }

        .live-footer-summary {
            background: linear-gradient(110deg, #0b2345, #164d8c);
            color: #fff;
            border-radius: 16px;
            padding: 23px;
        }

        .live-footer-summary .live-kicker {
            color: #9dccff;
        }

        .live-footer-summary h2 {
            font-size: 19px;
            color: white;
            margin: 8px 0;
        }

        .live-footer-summary p {
            font-size: 11px;
            color: #d0e2f7;
            margin: 0;
        }

        .live-footer-numbers {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 23px;
        }

        .live-footer-numbers > div {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 13px;
            border-radius: 10px;
            background: rgba(255,255,255,.09);
        }

        .live-footer-numbers strong {
            font-size: 25px;
            color: white;
        }

        .live-footer-numbers span {
            color: #d0e2f7;
            font-size: 10px;
        }

        .live-empty-small {
            padding: 25px;
            text-align: center;
            color: var(--live-muted);
            font-size: 11px;
        }

        .live-no-service {
            text-align: center;
            padding: 65px 20px;
        }

        .live-no-service-icon {
            width: 65px;
            height: 65px;
            display: grid;
            place-items: center;
            border-radius: 18px;
            background: #edf5ff;
            color: var(--live-blue);
            font-size: 30px;
            margin: 0 auto 20px;
        }

        .live-no-service h2 {
            color: var(--live-navy);
            font-size: 23px;
            margin: 10px 0;
        }

        .live-no-service p {
            color: #526984;
            font-size: 13px;
        }

        .live-no-service > span:last-child {
            font-size: 11px;
            color: var(--live-muted);
        }

        @media(max-width: 1150px) {
            .live-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .live-waiter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media(max-width: 850px) {
            .live-filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .live-charts-grid {
                grid-template-columns: 1fr;
            }

            .live-command-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media(max-width: 600px) {
            .live-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .live-heading-date {
                text-align: left;
            }

            .live-filter-grid {
                grid-template-columns: 1fr;
            }

            .live-filter-actions {
                align-items: stretch;
            }

            .live-filter-actions .live-btn {
                flex: 1;
            }

            .live-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .live-kpi {
                padding: 14px;
            }

            .live-kpi-top > span:first-child {
                font-size: 8px;
            }

            .live-service-banner {
                align-items: flex-start;
                flex-direction: column;
            }

            .live-donut-layout {
                flex-direction: column;
            }

            .live-legend {
                width: 100%;
            }

            .live-waiter-grid {
                grid-template-columns: 1fr;
            }

            .live-footer-numbers {
                grid-template-columns: 1fr;
            }

            .live-card {
                padding: 16px;
            }

            .live-section-heading {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>

@endsection
