@extends('layouts.app')
@section('title', 'Servicio en vivo')
@section('content')
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="live-service-title">
        <div class="chef-hero-icon"><x-icon name="activity" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Cocina · {{ now('America/Mexico_City')->format('d/m/Y') }}</span><h1 id="live-service-title">Servicio en vivo</h1><p>Consulta los servicios aprobados y el que se encuentra en operación.</p></div>
        <div class="chef-hero-actions"><a class="chef-button chef-button-secondary" href="{{ route('services.index') }}"><x-icon name="calendar" />Ver agenda</a><a class="chef-button chef-button-secondary" href="{{ route('kitchen.index') }}"><x-icon name="plates" />Comandas</a></div>
    </section>

    <section class="chef-stat-grid chef-stat-grid-three" aria-label="Resumen de servicios de hoy">
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="calendar" /></span><div><strong>{{ $todayServices->count() }}</strong><span>Servicios aprobados hoy</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-green"><x-icon name="activity" /></span><div><strong>{{ $active ? 1 : 0 }}</strong><span>En operación</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="clock" /></span><div><strong>{{ $todayServices->where('estado', 'Programado')->count() }}</strong><span>Programados</span></div></article>
    </section>

    <section class="chef-panel" aria-labelledby="active-service-heading">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Seguimiento en tiempo real</span><h2 id="active-service-heading">Servicio activo</h2></div></div>
        @if($active)
            <div class="chef-live-service-card"><span class="chef-pill chef-pill-green">En curso</span><h3>{{ $active->nombre }}</h3><p>{{ substr($active->inicio_hora, 0, 5) }} – {{ substr($active->fin_hora, 0, 5) }} <span>·</span> Capacidad registrada: {{ $active->capacidad ?? 'Sin definir' }}</p><a class="chef-button chef-button-primary" href="{{ route('kitchen.index') }}">Abrir operación de cocina</a></div>
        @else
            <div class="chef-empty"><span class="chef-empty-icon"><x-icon name="activity" /></span><strong>No hay un servicio activo ahora</strong><p>Los servicios programados aparecen abajo. El estado cambia cuando la operación autorizada inicia el servicio.</p></div>
        @endif
    </section>

    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Sede activa</span><h2>Servicios aprobados de hoy</h2></div><span class="chef-count">{{ $todayServices->count() }} resultados</span></div>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Servicio</th><th>Horario</th><th>Capacidad</th><th>Estado</th></tr></thead><tbody>
            @forelse($todayServices as $service)
                <tr><td><strong>{{ $service->nombre }}</strong></td><td>{{ substr($service->inicio_hora, 0, 5) }} – {{ substr($service->fin_hora, 0, 5) }}</td><td>{{ $service->capacidad ?? 'Sin definir' }}</td><td>@if($service->estado === 'En curso')<span class="chef-pill chef-pill-blue">En curso</span>@elseif($service->estado === 'Finalizado')<span class="chef-pill chef-pill-slate">Finalizado</span>@else<span class="chef-pill chef-pill-amber">{{ $service->estado ?: 'Programado' }}</span>@endif</td></tr>
            @empty
                <tr><td colspan="4"><div class="chef-empty"><strong>No hay servicios aprobados para hoy</strong><p>Cuando el Jefe de cocina registre un servicio y sea aprobado, aparecerá aquí.</p><a class="chef-button chef-button-secondary" href="{{ route('services.index') }}">Consultar agenda</a></div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
