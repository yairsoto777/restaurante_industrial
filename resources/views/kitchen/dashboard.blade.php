@extends('layouts.app')
@section('title', 'Operación de cocina')
@section('content')
<div class="page-heading"><div><span class="section-kicker">Cocina</span><h1>Operación de cocina</h1><p>{{ auth()->user()->name }} · {{ $site->nombre }}</p></div></div>
<section class="kpi-grid" aria-label="Módulos autorizados">
<a class="kpi-card" href="{{ route('services.live') }}"><div class="kpi-icon blue"><x-icon name="activity"/></div><div class="kpi-copy"><span>Servicio en vivo</span><small>Consulta el servicio activo.</small></div></a>
<a class="kpi-card" href="{{ route('groups.index') }}"><div class="kpi-icon cyan"><x-icon name="group"/></div><div class="kpi-copy"><span>Grupos y entregas</span><small>Consulta la operación del servicio.</small></div></a>
<a class="kpi-card" href="{{ route('menus.index') }}"><div class="kpi-icon green"><x-icon name="utensils"/></div><div class="kpi-copy"><span>Menú por servicio</span><small>Consulta los platillos asignados.</small></div></a>
<a class="kpi-card" href="{{ route('inventory.index') }}"><div class="kpi-icon orange"><x-icon name="warehouse"/></div><div class="kpi-copy"><span>Inventario de insumos</span><small>Consulta existencias de tu sede.</small></div></a>
<a class="kpi-card" href="{{ route('incidents.index') }}"><div class="kpi-icon red"><x-icon name="alert"/></div><div class="kpi-copy"><span>Incidencias</span><small>Reporta anomalías de cocina.</small></div></a>
</section>
<section class="panel"><div class="panel-head"><div><span class="section-kicker">Hoy</span><h2>Servicios de tu sede</h2></div></div><div class="table-wrap"><table><thead><tr><th>Servicio</th><th>Horario</th><th>Estado</th></tr></thead><tbody>
@forelse($services as $service)<tr><td>{{ $service->nombre }}</td><td>{{ $service->inicio_hora ? substr($service->inicio_hora, 0, 5) : '--:--' }} - {{ $service->fin_hora ? substr($service->fin_hora, 0, 5) : '--:--' }}</td><td><span class="badge">{{ $service->estado ?: 'Programado' }}</span></td></tr>@empty<tr><td colspan="3">No hay servicios programados para hoy.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
