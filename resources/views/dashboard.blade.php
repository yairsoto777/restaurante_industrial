@extends('layouts.app')
@section('title', 'Panel principal')
@section('content')
<div class="dashboard-heading">
    <div><span class="section-kicker">Resumen operativo</span><h1>Buen día, {{ auth()->user()->nombre ?: auth()->user()->name }}</h1><p>Aquí tienes un resumen de la operación de {{ $currentSite?->nombre ?? 'las sedes disponibles' }}.</p></div>
    <div class="current-date"><x-icon name="calendar"/><div><small>Fecha actual</small><strong>{{ now()->format('d/m/Y') }}</strong></div></div>
</div>

@if(!$currentSite)
    <div class="alert warning">No hay una sede disponible para mostrar información operativa.</div>
@endif

<section class="kpi-grid" aria-label="Indicadores principales">
    @foreach($kpis as $kpi)
        <article class="kpi-card"><div class="kpi-icon {{ $kpi['tone'] }}"><x-icon :name="$kpi['icon']"/></div><div class="kpi-copy"><span>{{ $kpi['label'] }}</span><strong>{{ number_format($kpi['value']) }}</strong><small>{{ $kpi['detail'] }}</small></div></article>
    @endforeach
</section>

<div class="dashboard-grid">
    <section class="dashboard-card activity-card">
        <div class="card-heading"><div><span class="section-kicker">Movimientos</span><h2>Actividad reciente</h2></div></div>
        @forelse($activity as $event)
            <div class="activity-item"><span class="activity-dot {{ $event['tone'] }}"></span><time>{{ \Carbon\Carbon::parse($event['timestamp'])->format('H:i') }}</time><div><strong>{{ $event['title'] }}</strong><span>{{ \Illuminate\Support\Str::limit($event['detail'], 75) }}</span></div></div>
        @empty
            <div class="inline-empty"><x-icon name="activity"/><p>No hay actividad reciente.</p></div>
        @endforelse
    </section>

    <section class="dashboard-card occupation-card">
        <div class="card-heading"><div><span class="section-kicker">Capacidad</span><h2>Ocupación de hoy</h2></div></div>
        <div class="occupation-content">
            <div class="donut" style="--value: {{ $occupation['percent'] }}"><div><strong>{{ $occupation['percent'] }}%</strong><span>ocupación</span></div></div>
            @if($occupation['capacity'] > 0)
                <div class="occupation-legend"><p><span class="legend-dot blue"></span><b>{{ $occupation['current'] }}</b> ocupación actual</p><p><span class="legend-dot pale"></span><b>{{ $occupation['available'] }}</b> capacidad disponible</p><small>Capacidad total: {{ $occupation['capacity'] }}</small></div>
            @else
                <div class="occupation-legend"><p>Sin datos de ocupación</p><small>La sede no tiene capacidad configurada.</small></div>
            @endif
        </div>
    </section>

    <section class="dashboard-card services-card">
        <div class="card-heading"><div><span class="section-kicker">Programación</span><h2>Estado de servicios</h2></div><a href="{{ route('services.index') }}">Ver módulo <x-icon name="chevron"/></a></div>
        <div class="services-list">
        @forelse($services as $service)
            @php($statusClass = match(mb_strtolower($service->estado ?? '')) {'en curso', 'activo' => 'live', 'finalizado', 'terminado' => 'done', default => 'scheduled'})
            <div class="service-row"><div class="service-time"><strong>{{ $service->inicio_hora ? substr($service->inicio_hora, 0, 5) : '--:--' }}</strong><span>{{ $service->fin_hora ? substr($service->fin_hora, 0, 5) : '--:--' }}</span></div><div class="service-name"><strong>{{ $service->nombre }}</strong><span>{{ (int) $service->ocupacion }} / {{ $service->capacidad ?? 'Sin capacidad' }} comensales</span></div><span class="service-status {{ $statusClass }}">{{ $service->estado ?: 'Programado' }}</span></div>
        @empty
            <div class="inline-empty"><x-icon name="calendar"/><p>No hay servicios programados.</p></div>
        @endforelse
        </div>
    </section>

    <section class="dashboard-card chart-card">
        <div class="card-heading"><div><span class="section-kicker">Actividad operativa</span><h2>Comensales por hora</h2></div><span class="chart-date">Hoy</span></div>
        @if($hourlyDiners->isNotEmpty())
            @php($maxDiners = max(1, (int) $hourlyDiners->max('total')))
            <div class="bar-chart">
                @foreach($hourlyDiners as $point)<div class="bar-column"><span>{{ $point->total }}</span><i style="height: {{ max(6, round(($point->total / $maxDiners) * 100)) }}%"></i><small>{{ str_pad($point->hora, 2, '0', STR_PAD_LEFT) }}:00</small></div>@endforeach
            </div>
        @else
            <div class="chart-empty"><div class="chart-grid-lines"></div><x-icon name="chart"/><p>No hay datos disponibles.</p></div>
        @endif
    </section>
</div>
@endsection
