@extends('layouts.app')
@section('title','Historial de recepciones')
@section('content')
<div class="rx-page">
    <header class="rx-heading"><div><span class="rx-eyebrow">{{ $site->nombre }} · Tus registros</span><h1>Historial de recepciones</h1><p>Consulta tus entregas registradas, sus incidencias y el resultado de la revisión.</p></div><a class="rx-button primary" href="{{ route('receptions.register') }}"><x-icon name="plus"/>Registrar recepción</a></header>
    <div class="rx-kpis three">@foreach([['total','Recepciones registradas','box','info'],['pending','Pendientes de revisión','clock','waiting'],['confirmed','Confirmadas por Almacén','check','ok']] as [$key,$label,$icon,$tone])<article class="rx-card rx-kpi {{ $tone }}"><span class="rx-kpi-icon"><x-icon :name="$icon"/></span><div><strong>{{ $summary[$key] ?? '—' }}</strong><h2>{{ $label }}</h2></div></article>@endforeach</div>
    <section class="rx-card">
        <div class="rx-card-heading"><h2>Mis recepciones</h2><span>{{ auth()->user()->name }}</span></div>
        <form class="rx-filters" method="GET"><label>Recepción, pedido o proveedor<input name="q" maxlength="150" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar en tu historial"></label><label>Fecha<input type="date" name="fecha" value="{{ $filters['fecha'] ?? '' }}"></label><label>Estado<select name="estado"><option value="">Todos</option>@foreach($states as $state)<option @selected(($filters['estado'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></label><button class="rx-button primary">Filtrar</button><a class="rx-button" href="{{ route('receptions.history') }}">Limpiar</a></form>
        <div class="rx-table-wrap"><table><thead><tr><th>Folio / pedido</th><th>Proveedor</th><th>Fecha / responsable</th><th>Insumos recibidos</th><th>Incidencias</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
            @forelse($receipts ?? [] as $receipt)<tr><td><strong>#{{ $receipt->id_recepcion }}</strong><small>Pedido #{{ $receipt->id_pedido }}</small></td><td>{{ $receipt->proveedor_nombre }}</td><td>{{ $receipt->fecha_recepcion }}<small>{{ auth()->user()->name }}</small></td><td>{{ $lines->get($receipt->id_recepcion,collect())->pluck('nombre')->filter()->implode(', ') ?: 'Sin partidas disponibles' }}</td><td>@if($incidents->get($receipt->id_recepcion,0))<span class="rx-status danger">{{ $incidents->get($receipt->id_recepcion) }} registradas</span>@else<span class="rx-muted">Sin incidencias vinculadas</span>@endif</td><td>@include('receptions.workspace.status',['state'=>$receipt->estado])</td><td><a class="rx-button" href="{{ route('receptions.receipts.show',$receipt->id_recepcion) }}">Ver detalle</a></td></tr>
            @empty<tr><td colspan="7" class="rx-empty">{{ $available ? 'No hay recepciones para estos filtros.' : 'El historial aún no está disponible.' }}</td></tr>@endforelse
        </tbody></table></div>
        @if($receipts?->hasPages())<div class="rx-pagination">{{ $receipts->links() }}</div>@endif
    </section>
</div>
@endsection
