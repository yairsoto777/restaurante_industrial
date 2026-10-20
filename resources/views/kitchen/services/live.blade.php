@extends('layouts.app')

@section('title', 'Servicio en vivo')

@section('content')
<div class="page-heading"><div><span class="section-kicker">Cocina · {{ $site->nombre }}</span><h1>Servicio en vivo</h1><p>Consulta el servicio registrado como activo para hoy.</p></div><a class="button" href="{{ route('services.index') }}">Ver próximos servicios</a></div>
<section class="panel"><div class="panel-head"><h2>Servicio activo</h2></div>@if($active)<div class="empty-state"><span class="section-kicker">En curso</span><h2>{{ $active->nombre }}</h2><p>Horario: {{ substr($active->inicio_hora, 0, 5) }} - {{ substr($active->fin_hora, 0, 5) }} · Capacidad: {{ $active->capacidad ?? '—' }}</p><span class="badge">{{ $active->estado }}</span></div>@else<div class="empty-state"><strong>No hay un servicio activo.</strong><p>Los servicios programados para hoy no se consideran activos hasta que su estado sea actualizado.</p></div>@endif</section>
<section class="panel"><div class="panel-head"><h2>Servicios de hoy</h2></div><div class="table-wrap"><table class="catalog-table"><thead><tr><th>Servicio</th><th>Horario</th><th>Estado</th></tr></thead><tbody>@forelse($todayServices as $service)<tr><td>{{ $service->nombre }}</td><td>{{ substr($service->inicio_hora, 0, 5) }} - {{ substr($service->fin_hora, 0, 5) }}</td><td><span class="badge">{{ $service->estado ?: 'Programado' }}</span></td></tr>@empty<tr><td colspan="3">No hay servicios registrados para hoy.</td></tr>@endforelse</tbody></table></div></section>
@endsection
