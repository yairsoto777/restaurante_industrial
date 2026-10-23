@extends('layouts.app')
@section('title', 'Servicios de cocina')
@section('content')
@php($canManage = auth()->user()->hasRole('Jefe de cocina'))
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="services-title">
        <div class="chef-hero-icon"><x-icon name="calendar" /></div>
        <div class="chef-hero-copy">
            <span class="chef-eyebrow">{{ $site->nombre }} · Cocina</span>
            <h1 id="services-title">Servicios</h1>
            <p>{{ $canManage ? 'Programa servicios y consulta su aprobación y estado operativo.' : 'Consulta los servicios aprobados y vigentes de tu sede y abre la operación en vivo.' }}</p>
        </div>
        <div class="chef-hero-actions">
            @if($canManage)
                <a class="chef-button chef-button-primary" href="{{ route('kitchen.services.create') }}"><x-icon name="calendar" />Registrar servicio</a>
            @endif
            <a class="chef-button chef-button-secondary" href="{{ route('services.live') }}"><x-icon name="activity" />Servicio en vivo</a>
        </div>
    </section>

    <section class="chef-stat-grid {{ $canManage ? '' : 'chef-stat-grid-three' }}" aria-label="Resumen de servicios">
        @if($canManage)
            <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="calendar" /></span><div><strong>{{ $totalCount }}</strong><span>Servicios registrados</span></div></article>
            <article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="clock" /></span><div><strong>{{ $pendingCount }}</strong><span>Pendientes de revisión</span></div></article>
            <article class="chef-stat"><span class="chef-stat-icon chef-stat-green"><x-icon name="check" /></span><div><strong>{{ $approvedCount }}</strong><span>Aprobados</span></div></article>
            <article class="chef-stat"><span class="chef-stat-icon chef-stat-indigo"><x-icon name="activity" /></span><div><strong>{{ $activeCount }}</strong><span>En curso</span></div></article>
        @else
            <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="calendar" /></span><div><strong>{{ $totalCount }}</strong><span>Servicios visibles</span></div></article>
            <article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="clock" /></span><div><strong>{{ $scheduledCount }}</strong><span>Programados</span></div></article>
            <article class="chef-stat"><span class="chef-stat-icon chef-stat-indigo"><x-icon name="activity" /></span><div><strong>{{ $activeCount }}</strong><span>En curso</span></div></article>
        @endif
    </section>

    <section class="chef-panel">
        <div class="chef-panel-heading">
            <div><span class="chef-kicker">Agenda de la sede</span><h2>{{ $canManage ? 'Programación y seguimiento' : 'Servicios autorizados' }}</h2></div>
            <span class="chef-count">{{ $services->total() }} resultados</span>
        </div>
        <form class="chef-filters" method="GET" action="{{ route('services.index') }}">
            <label class="chef-search"><span class="chef-sr-only">Buscar servicio</span><x-icon name="search" /><input type="search" name="q" value="{{ $search }}" placeholder="Buscar servicio"></label>
            <label><span class="chef-sr-only">Filtrar por estado</span><select name="estado">
                <option value="">Todos los estados</option>
                @if($canManage)
                    <option value="Pendiente" @selected($filterState === 'Pendiente')>Pendiente de revisión</option>
                    <option value="Aprobado" @selected($filterState === 'Aprobado')>Aprobado</option>
                    <option value="Rechazado" @selected($filterState === 'Rechazado')>Rechazado</option>
                @endif
                <option value="Programado" @selected($filterState === 'Programado')>Programado</option>
                <option value="En curso" @selected($filterState === 'En curso')>En curso</option>
                <option value="Finalizado" @selected($filterState === 'Finalizado')>Finalizado</option>
                <option value="Cancelado" @selected($filterState === 'Cancelado')>Cancelado</option>
            </select></label>
            <button class="chef-button chef-button-secondary" type="submit">Filtrar</button>
            @if($search !== '' || $filterState !== '')<a class="chef-link-muted" href="{{ route('services.index') }}">Limpiar</a>@endif
        </form>

        <div class="chef-table-wrap">
            <table class="chef-table">
                <thead><tr><th>Servicio</th><th>Fecha y horario</th><th>Capacidad</th><th>Aprobación</th><th>Estado operativo</th>@if($canManage)<th>Acciones</th>@endif</tr></thead>
                <tbody>
                @forelse($services as $service)
                    <tr>
                        <td><strong>{{ $service->nombre }}</strong>@if($service->observaciones_revision)<small class="chef-cell-note">{{ $service->observaciones_revision }}</small>@endif</td>
                        <td>{{ $service->fecha?->format('d/m/Y') ?: 'Fecha pendiente' }}<small class="chef-cell-note">{{ $service->inicio_hora ? substr($service->inicio_hora, 0, 5) : '--:--' }} – {{ $service->fin_hora ? substr($service->fin_hora, 0, 5) : '--:--' }}</small></td>
                        <td>{{ $service->capacidad ? number_format($service->capacidad).' personas' : 'Sin definir' }}</td>
                        <td>
                            @if($service->estado_aprobacion === 'Aprobado')<span class="chef-pill chef-pill-green">Aprobado</span>
                            @elseif($service->estado_aprobacion === 'Rechazado')<span class="chef-pill chef-pill-red">Rechazado</span>
                            @else<span class="chef-pill chef-pill-amber">{{ $service->estado_aprobacion ?: 'Pendiente' }}</span>@endif
                        </td>
                        <td>
                            @if($service->estado === 'En curso')<span class="chef-pill chef-pill-blue">En curso</span>
                            @elseif($service->estado === 'Finalizado')<span class="chef-pill chef-pill-slate">Finalizado</span>
                            @elseif($service->estado === 'Cancelado')<span class="chef-pill chef-pill-red">Cancelado</span>
                            @else<span class="chef-pill chef-pill-slate">{{ $service->estado ?: 'Programado' }}</span>@endif
                        </td>
                        @if($canManage)
                            <td><div class="chef-row-actions">
                                @if(in_array($service->estado_aprobacion, ['Pendiente', 'Rechazado', null], true))
                                    <a class="chef-button chef-button-small chef-button-secondary" href="{{ route('kitchen.services.edit', $service) }}">Editar propuesta</a>
                                @elseif($service->estado === 'Programado')
                                    <a class="chef-button chef-button-small chef-button-secondary" href="{{ route('menus.index', ['service' => $service->id_servicio]) }}">Preparar menú</a>
                                    <form method="POST" action="{{ route('kitchen.services.status', $service) }}">@csrf @method('PATCH')<input type="hidden" name="estado" value="En curso"><button class="chef-button chef-button-small chef-button-primary" type="submit">Iniciar</button></form>
                                    <form method="POST" action="{{ route('kitchen.services.status', $service) }}" data-confirm="¿Cancelar este servicio?">@csrf @method('PATCH')<input type="hidden" name="estado" value="Cancelado"><button class="chef-button chef-button-small chef-button-danger" type="submit">Cancelar</button></form>
                                @elseif($service->estado === 'En curso')
                                    <form method="POST" action="{{ route('kitchen.services.status', $service) }}">@csrf @method('PATCH')<input type="hidden" name="estado" value="Finalizado"><button class="chef-button chef-button-small chef-button-primary" type="submit">Finalizar</button></form>
                                    <form method="POST" action="{{ route('kitchen.services.status', $service) }}" data-confirm="¿Cancelar este servicio en curso?">@csrf @method('PATCH')<input type="hidden" name="estado" value="Cancelado"><button class="chef-button chef-button-small chef-button-danger" type="submit">Cancelar</button></form>
                                @else<span class="chef-muted">Sin acciones</span>@endif
                            </div></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canManage ? 6 : 5 }}"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="calendar" /></span><strong>No hay servicios para mostrar</strong><p>{{ $search || $filterState ? 'Ajusta los filtros para ver otros servicios.' : ($canManage ? 'Registra una propuesta de servicio para iniciar la programación.' : 'Los servicios aprobados y vigentes de esta sede aparecerán aquí cuando se registren.') }}</p>@if($canManage && ! $search && ! $filterState)<a class="chef-button chef-button-primary" href="{{ route('kitchen.services.create') }}">Registrar servicio</a>@endif</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($services->hasPages())<div class="chef-pagination">{{ $services->links() }}</div>@endif
    </section>
</div>
<script>document.querySelectorAll('form[data-confirm]').forEach((form)=>form.addEventListener('submit',(event)=>{if(!window.confirm(form.dataset.confirm))event.preventDefault()}));</script>
@endsection
