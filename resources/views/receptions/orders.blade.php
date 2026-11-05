@extends('layouts.app')
@section('title', 'Recepción de insumos')
@section('content')
    <div class="chef-page">
        <section class="chef-hero">
            <div class="chef-hero-icon"><x-icon name="box" /></div>
            <div class="chef-hero-copy">
                <span class="chef-eyebrow">{{ $site->nombre }} · Recepción</span>
                <h1>{{ $isWarehouseManager ? 'Supervisión de recepciones' : 'Pedidos por recibir' }}</h1>
                <p>Consulta pedidos, entregas y comprobantes de tu sede.</p>
            </div>
        </section>
        @if(session('status'))<section class="chef-panel" role="status">{{ session('status') }}</section>@endif
        @if($isWarehouseManager)
            <section class="chef-panel">
                <div class="chef-panel-heading"><h2>Recepciones para revisión</h2><span class="chef-count">{{ $recentReceipts->count() }} recientes</span></div>
                <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Recepción</th><th>Pedido</th><th>Fecha</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
                        @forelse($recentReceipts as $receipt)
                            <tr><td>#{{ $receipt->id_recepcion }}</td><td>#{{ $receipt->id_pedido }}</td><td>{{ $receipt->fecha_recepcion }}</td><td>{{ $receipt->estado }}</td>
                                <td><a class="chef-button chef-button-secondary" href="{{ route('receptions.review.show', $receipt->id_recepcion) }}">Revisar</a></td></tr>
                        @empty<tr><td colspan="5">No hay recepciones para revisar.</td></tr>@endforelse
                        </tbody></table></div>
            </section>
        @endif
        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Pedidos de proveedores</h2><span class="chef-count">{{ $orders->total() }}</span></div>
            <form class="chef-filters" action="{{ route('receptions.index') }}" method="GET">
                <label class="chef-search">Buscar<input name="q" type="search" value="{{ $search }}" maxlength="150" placeholder="Número o proveedor"></label>
                <label>Fecha de entrega<input name="fecha_entrega" type="date" value="{{ $filters['fecha_entrega'] ?? '' }}"></label>
                <label>Estado<select name="estado"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state }}" @selected(($filters['estado'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></label>
                <button class="chef-button chef-button-primary" type="submit">Filtrar</button>
                <a class="chef-button chef-button-secondary" href="{{ route('receptions.index') }}">Limpiar</a>
            </form>
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Pedido</th><th>Proveedor</th><th>Creado</th><th>Entrega prevista</th><th>Estado</th><th></th></tr></thead><tbody>
                    @forelse($orders as $order)
                        <tr><td>#{{ $order->id_pedido }}</td><td>{{ $order->proveedor_nombre ?: 'Sin proveedor' }}</td><td>{{ $order->fecha_pedido ?: '—' }}</td><td>{{ $order->fecha_entrega ?: '—' }}</td><td><span class="chef-pill chef-pill-slate">{{ $order->estado ?: '—' }}</span></td>
                            <td><a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $order->id_pedido) }}">Ver pedido</a></td></tr>
                    @empty<tr><td colspan="6">No hay pedidos para estos filtros.</td></tr>@endforelse
                    </tbody></table></div>
            @if($orders->hasPages())<div class="chef-pagination">{{ $orders->links() }}</div>@endif
        </section>
    </div>
@endsection
