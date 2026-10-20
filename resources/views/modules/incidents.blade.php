@extends('layouts.app')
@section('title', 'Incidencias')
@section('content')
<div class="page-heading"><div><span class="section-kicker">Operación · {{ $site->nombre }}</span><h1>Incidencias</h1><p>Seguimiento de problemas operativos de la sede.</p></div></div>
<section class="panel"><div class="panel-head"><h2>Registro</h2><span class="catalog-count">{{ $incidents->count() }} incidencia(s)</span></div><div class="table-wrap"><table class="catalog-table"><thead><tr><th>Fecha</th><th>Descripción</th><th>Estado</th><th>Responsable</th></tr></thead><tbody>@forelse($incidents as $incident)<tr><td>{{ $incident->fecha ?? '—' }}</td><td>{{ $incident->descripcion ?? '—' }}</td><td>{{ $incident->estado ?? 'Pendiente' }}</td><td>{{ $incident->id_usuario ?? '—' }}</td></tr>@empty<tr><td colspan="4"><div class="empty-state">No hay incidencias registradas.</div></td></tr>@endforelse</tbody></table></div></section>
@endsection
