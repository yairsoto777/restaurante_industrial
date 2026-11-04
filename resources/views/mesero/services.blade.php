@extends('layouts.app')
@section('title', 'Servicios')
@section('content')
<div class="chef-page">
    <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="calendar" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Mesero</span><h1>Servicios</h1><p>Consulta los servicios aprobados de tu sede y las atenciones que te asignó el capitán.</p></div><div class="chef-hero-actions"><a class="chef-button chef-button-primary" href="{{ route('waiter.index') }}">Mi operación</a></div></section>
    <section class="chef-stat-grid chef-stat-grid-three">
        <article class="chef-stat"><strong>{{ $total }}</strong><span>Servicios aprobados</span></article>
        <article class="chef-stat"><strong>{{ $today }}</strong><span>Servicios de hoy</span></article>
        <article class="chef-stat"><strong>{{ $active }}</strong><span>En curso</span></article>
    </section>
    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Programación de la sede</span><h2>Servicios registrados</h2></div></div>
        <form method="GET" action="{{ route('services.index') }}" class="form-grid">
            <label>Buscar servicio<input type="search" name="q" value="{{ $search }}" placeholder="Nombre del servicio"></label>
            <label>Estado<select name="estado"><option value="">Todos</option>@foreach(['Programado', 'En curso', 'Finalizado', 'Cancelado'] as $option)<option value="{{ $option }}" @selected($state === $option)>{{ $option }}</option>@endforeach</select></label>
            <div class="actions"><button class="chef-button chef-button-primary">Filtrar</button><a class="chef-button chef-button-secondary" href="{{ route('services.index') }}">Limpiar</a></div>
        </form>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Servicio</th><th>Fecha</th><th>Horario</th><th>Capacidad</th><th>Estado</th><th>Mis atenciones</th></tr></thead><tbody>
        @forelse($services as $service)
            <tr><td><strong>{{ $service->nombre }}</strong></td><td>{{ \Illuminate\Support\Carbon::parse($service->fecha)->format('d/m/Y') }}</td><td>{{ substr($service->inicio_hora ?? '', 0, 5) }} – {{ substr($service->fin_hora ?? '', 0, 5) }}</td><td>{{ $service->capacidad }} comensales</td><td>{{ $service->estado ?? 'Sin estado' }}</td><td>@if($service->mis_atenciones)<a class="chef-button chef-button-secondary" href="{{ route('waiter.index') }}">{{ $service->mis_atenciones }} asignadas</a>@else Sin atenciones asignadas @endif</td></tr>
        @empty
            <tr><td colspan="6">No hay servicios aprobados que coincidan con esta consulta en tu sede.</td></tr>
        @endforelse
        </tbody></table></div>
        {{ $services->links() }}
    </section>
</div>
@endsection
