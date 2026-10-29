@extends('layouts.app')
@section('title', 'Pedidos programados')
@section('content')
<div class="chef-page">
    <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Recepción de insumos</span><h1>Pedidos programados</h1><p>Consulta las entregas y los insumos solicitados para tu sede.</p></div></section>
    <section class="chef-panel">
        <div class="chef-panel-heading"><h2>Pedidos de la sede</h2><span class="chef-count">{{ $orders->total() }} resultados</span></div>
        <form class="chef-filters" method="GET" action="{{ route('receptions.index') }}">
            <label class="chef-search"><span class="chef-sr-only">Número o proveedor</span><x-icon name="search" /><input name="q" type="search" maxlength="150" value="{{ $search }}" placeholder="Número o proveedor"></label>
            <label>Fecha de entrega<input type="date" name="fecha_entrega" value="{{ $filters['fecha_entrega'] ?? '' }}"></label>
            <label>Estado<select name="estado"><option value="">Todos los estados</option>@foreach($states as $state)<option @selected(($filters['estado'] ?? '') === $state) value="{{ $state }}">{{ $state }}</option>@endforeach</select></label>
            <button class="chef-button chef-button-secondary">Filtrar</button><a class="chef-link-muted" href="{{ route('receptions.index') }}">Limpiar</a>
        </form>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Pedido</th><th>Proveedor</th><th>Fecha de pedido</th><th>Entrega</th><th>Estado</th><th>Consulta</th></tr></thead><tbody>
            @forelse($orders as $order)
            <tr><td>#{{ $order->id_pedido }}</td><td>{{ $order->proveedor_nombre ?? 'Sin proveedor disponible' }}</td><td>{{ $order->fecha_pedido ?? 'Sin fecha' }}</td><td>{{ $order->fecha_entrega ?? 'Sin programar' }}</td><td><span class="chef-pill chef-pill-slate">{{ $order->estado ?: 'Sin estado' }}</span></td><td><a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $order->id_pedido) }}">Ver detalle</a></td></tr>
            @empty
            <tr><td colspan="6"><div class="chef-empty"><strong>No hay pedidos con estos criterios</strong><p>Revisa los filtros o la sede seleccionada.</p></div></td></tr>
            @endforelse
        </tbody></table></div>
        @if($orders->hasPages())<div class="chef-pagination">{{ $orders->links() }}</div>@endif
    </section>
</div>
@endsection
