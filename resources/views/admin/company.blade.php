@extends('layouts.app')
@section('title', 'Resumen de empresa')
@section('content')
<div class="dashboard-heading">
    <div><span class="section-kicker">Vista global de empresa</span><h1>{{ $site->nombre }}</h1><p>Resumen informativo de la sede. La operación diaria corresponde a su gerente y equipo.</p></div>
    <a class="button" href="{{ route('dashboard') }}">Volver al dashboard</a>
</div>
<section class="panel company-summary">
    <div><small>Gerente</small><strong>{{ $manager?->name ?? 'Sin gerente asignado' }}</strong><span>{{ $manager?->correo }}</span></div>
    <div><small>Estado de la sede</small><strong>{{ $site->users->isNotEmpty() ? 'Activa' : 'Sin personal asignado' }}</strong><span>{{ $site->direccion ?: 'Dirección no registrada' }}</span></div>
    <div><small>Capacidad</small><strong>{{ number_format((int) $site->capacidad) }}</strong><span>personas configuradas</span></div>
</section>
<section class="kpi-grid" aria-label="Métricas de empresa">
    @foreach($metrics as $metric)
    <article class="kpi-card"><div class="kpi-icon {{ $metric['tone'] }}"><x-icon :name="$metric['icon']"/></div><div class="kpi-copy"><span>{{ $metric['label'] }}</span><strong>{{ number_format($metric['value']) }}</strong><small>Resumen de la sede</small></div></article>
    @endforeach
</section>
<section class="dashboard-card activity-card">
    <div class="card-heading"><div><span class="section-kicker">Consulta global</span><h2>Actividad reciente</h2></div></div>
    @forelse($activity as $event)
    <div class="activity-item"><span class="activity-dot {{ $event['tone'] }}"></span><time>{{ \Carbon\Carbon::parse($event['timestamp'])->format('d/m H:i') }}</time><div><strong>{{ $event['title'] }}</strong><span>{{ \Illuminate\Support\Str::limit($event['detail'], 90) }}</span></div></div>
    @empty<div class="inline-empty"><x-icon name="activity"/><p>No hay actividad reciente registrada.</p></div>@endforelse
</section>
@endsection
