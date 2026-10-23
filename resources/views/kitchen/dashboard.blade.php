@extends('layouts.app')
@section('title', 'Panel de cocina')
@section('content')
<div class="kitchen-dashboard">
    <header class="kitchen-dashboard-hero">
        <div class="kitchen-dashboard-hero-copy">
            <span class="kitchen-dashboard-eyebrow">{{ $site->nombre }} · Cocina</span>
            <h1>Panel de cocina</h1>
            <p>Operación del {{ $today->format('d/m/Y') }} · {{ auth()->user()->name }}</p>
        </div>
        <a class="chef-button chef-button-primary" href="{{ route('services.live') }}"><x-icon name="activity" />Servicio en vivo</a>
    </header>

    <section class="kitchen-dashboard-metrics" aria-label="Indicadores de la sede">
        @foreach($metrics as $metric)
            <article class="kitchen-dashboard-metric kitchen-dashboard-metric-{{ $metric['tone'] }}">
                <span class="kitchen-dashboard-metric-icon"><x-icon :name="$metric['icon']" /></span>
                <div><span>{{ $metric['label'] }}</span><strong>{{ number_format((int) $metric['value']) }}</strong><small>{{ $metric['detail'] }}</small></div>
            </article>
        @endforeach
    </section>

    <section aria-labelledby="kitchen-sections-title">
        <div class="kitchen-dashboard-section-heading"><div><span class="kitchen-dashboard-eyebrow">Accesos de operación</span><h2 id="kitchen-sections-title">Tus secciones</h2></div></div>
        <div class="kitchen-dashboard-links">
            <a class="kitchen-dashboard-link" href="{{ route('services.index') }}"><span class="kitchen-dashboard-link-icon blue"><x-icon name="calendar" /></span><span><strong>Servicios</strong><small>Revisa agenda y estado aprobado.</small></span><x-icon name="chevron" /></a>
            <a class="kitchen-dashboard-link" href="{{ route('services.live') }}"><span class="kitchen-dashboard-link-icon cyan"><x-icon name="activity" /></span><span><strong>Servicio en vivo</strong><small>Consulta el servicio activo de hoy.</small></span><x-icon name="chevron" /></a>
            <a class="kitchen-dashboard-link" href="{{ route('inventory.index') }}"><span class="kitchen-dashboard-link-icon orange"><x-icon name="warehouse" /></span><span><strong>Inventario</strong><small>Consulta existencias de la sede.</small></span><x-icon name="chevron" /></a>
            <a class="kitchen-dashboard-link" href="{{ route('groups.index') }}"><span class="kitchen-dashboard-link-icon indigo"><x-icon name="group" /></span><span><strong>Grupos y entregas</strong><small>Consulta atenciones y entregas.</small></span><x-icon name="chevron" /></a>
            <a class="kitchen-dashboard-link" href="{{ route('menus.index') }}"><span class="kitchen-dashboard-link-icon green"><x-icon name="utensils" /></span><span><strong>Menú por servicio</strong><small>Ve los platillos publicados.</small></span><x-icon name="chevron" /></a>
            <a class="kitchen-dashboard-link" href="{{ route('dishes.index') }}"><span class="kitchen-dashboard-link-icon blue"><x-icon name="dish" /></span><span><strong>Platillos</strong><small>Consulta el catálogo y sus recetas.</small></span><x-icon name="chevron" /></a>
            <a class="kitchen-dashboard-link" href="{{ route('kitchen.index') }}"><span class="kitchen-dashboard-link-icon green"><x-icon name="plates" /></span><span><strong>Operación de cocina</strong><small>Avanza comandas y registra entregas.</small></span><x-icon name="chevron" /></a>
            <a class="kitchen-dashboard-link" href="{{ route('incidents.index') }}"><span class="kitchen-dashboard-link-icon red"><x-icon name="alert" /></span><span><strong>Incidencias</strong><small>Reporta faltantes y problemas.</small></span><x-icon name="chevron" /></a>
        </div>
    </section>

    <section class="chef-panel kitchen-dashboard-services" aria-labelledby="today-services-title">
        <div class="chef-panel-heading"><div><span class="chef-kicker">{{ $today->format('d/m/Y') }}</span><h2 id="today-services-title">Servicios aprobados de hoy</h2></div><a class="chef-link-muted" href="{{ route('services.index') }}">Ver agenda</a></div>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Servicio</th><th>Horario</th><th>Estado</th></tr></thead><tbody>
            @forelse($services as $service)
                <tr><td><strong>{{ $service->nombre }}</strong></td><td>{{ $service->inicio_hora ? substr($service->inicio_hora, 0, 5) : '--:--' }} – {{ $service->fin_hora ? substr($service->fin_hora, 0, 5) : '--:--' }}</td><td>@if($service->estado === 'En curso')<span class="chef-pill chef-pill-blue">En curso</span>@elseif($service->estado === 'Finalizado')<span class="chef-pill chef-pill-slate">Finalizado</span>@else<span class="chef-pill chef-pill-green">{{ $service->estado ?: 'Programado' }}</span>@endif</td></tr>
            @empty
                <tr><td colspan="3"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="calendar" /></span><strong>No hay servicios aprobados para hoy</strong><p>El personal de cocina verá aquí los servicios de su sede cuando el Jefe de cocina los registre y sean aprobados.</p><a class="chef-button chef-button-secondary" href="{{ route('services.index') }}">Consultar servicios</a></div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
