@extends('layouts.app')
@section('title', $registration ? 'Registrar recepción' : 'Entregas programadas')
@section('content')
<div class="rx-page">
    <header class="rx-heading"><div><span class="rx-eyebrow">{{ $site->nombre }} · Recepción</span><h1>{{ $registration ? 'Registrar recepción' : 'Entregas programadas' }}</h1><p>{{ $registration ? 'Selecciona un pedido existente para registrar la mercancía que entregó el proveedor.' : 'Consulta los pedidos de Almacén y prepara la revisión de cada entrega.' }}</p></div><x-icon name="calendar"/></header>
    @if(!$available)<section class="rx-card rx-empty">Los pedidos aún no están disponibles para consulta.</section>@else
    @if($registration && !$captureAvailable)<div class="rx-notice waiting" role="status">Puedes consultar los pedidos. La captura requiere que esté habilitado el esquema existente de recepciones.</div>@endif
    <section class="rx-card">
        <div class="rx-card-heading"><h2>{{ $registration ? '1. Seleccionar pedido' : 'Pedidos de la sede' }}</h2><span>{{ $orders->total() }} pedidos</span></div>
        <form class="rx-filters" method="GET">
            <label>Folio o proveedor<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="Buscar pedido o proveedor"></label>
            <label>Fecha de entrega<input name="fecha" type="date" value="{{ $filters['fecha'] ?? '' }}"></label>
            <label>Proveedor<select name="proveedor"><option value="">Todos</option>@foreach($providers as $provider)<option value="{{ $provider->id_proveedor }}" @selected(($filters['proveedor'] ?? '') == $provider->id_proveedor)>{{ $provider->nombre }}</option>@endforeach</select></label>
            <label>Estado<select name="estado"><option value="">Todos</option>@foreach($states as $state)<option @selected(($filters['estado'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></label>
            <button class="rx-button primary">Filtrar</button><a class="rx-button" href="{{ route($registration ? 'receptions.register' : 'receptions.index') }}">Limpiar</a>
        </form>
        <div class="rx-table-wrap"><table><thead><tr><th>Pedido</th><th>Proveedor</th><th>Entrega prevista</th><th>Insumos solicitados</th><th>Estado</th><th>Observaciones</th><th>Acciones</th></tr></thead><tbody>
            @forelse($orders as $order)<tr><td><strong>#{{ $order->id_pedido }}</strong><small>Creado {{ $order->fecha_pedido ?: 'Sin fecha' }}</small></td><td>{{ $order->proveedor_nombre ?: 'Sin proveedor' }}</td><td>{{ $order->fecha_entrega ?: 'Por definir' }}@if(!empty($order->hora_entrega))<small>{{ $order->hora_entrega }}</small>@endif</td>
            <td><details class="rx-order-lines"><summary>{{ $lines->get($order->id_pedido,collect())->count() }} insumos</summary><ul>@foreach($lines->get($order->id_pedido,collect()) as $line)<li><strong>{{ $line->insumo_nombre }}</strong> · {{ $line->cantidad ?? 'Sin cantidad' }}@if($line->especificaciones)<small>{{ $line->especificaciones }}</small>@endif</li>@endforeach</ul></details></td>
            <td>@include('receptions.workspace.status',['state'=>$order->estado])</td><td>{{ $order->observaciones ?: 'Sin observaciones' }}</td><td><div class="rx-actions"><a class="rx-button" href="{{ route('receptions.orders.show',$order->id_pedido) }}">Ver detalle</a>@if($captureAvailable && in_array($order->estado,config('receptions.order_states',[]),true))<a class="rx-button primary" href="{{ route('receptions.create',$order->id_pedido) }}">Recibir pedido</a>@endif</div></td></tr>
            @empty<tr><td colspan="7" class="rx-empty">No hay pedidos que coincidan con los filtros.</td></tr>@endforelse
        </tbody></table></div>
        @if($orders->hasPages())<div class="rx-pagination">{{ $orders->links() }}</div>@endif
    </section>@endif
</div>
@endsection
