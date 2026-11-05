@extends('layouts.app')
@section('title', 'Pedidos a proveedores')
@section('content')
    <div class="chef-page">
        <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div>
            <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Abastecimiento</span><h1>Pedidos a proveedores</h1><p>Crea y consulta solicitudes de compra. No registra recepciones ni cambia inventario.</p></div>
        </section>
        @if(session('status'))<div class="chef-panel" role="status">{{ session('status') }}</div>@endif
        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Pedidos de la sede</h2><span class="chef-count">{{ $stats['total'] }} pedidos · {{ $stats['programados'] }} programados</span></div>
            @if($canCreate)<p><a class="chef-button chef-button-primary" href="{{ route('orders.create') }}">+ Nuevo pedido</a> <a class="chef-button chef-button-secondary" href="{{ route('orders.suppliers.index') }}">Administrar proveedores</a></p>@endif
            <form class="chef-filters" method="GET" action="{{ route('orders.index') }}">
                <label class="chef-search">Número o proveedor<input type="search" name="q" value="{{ $q }}" maxlength="150" placeholder="Buscar pedido o proveedor"></label>
                <label>Estado<select name="estado"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state }}" @selected(($filters['estado'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></label>
                <button class="chef-button chef-button-secondary" type="submit">Filtrar</button><a class="chef-button chef-button-secondary" href="{{ route('orders.index') }}">Limpiar</a>
            </form>
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Pedido</th><th>Proveedor</th><th>Pedido</th><th>Entrega prevista</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
                    @forelse($orders as $order)
                        <tr><td>#{{ $order->id_pedido }}</td><td>{{ $order->proveedor_nombre ?? 'Proveedor no encontrado' }}</td><td>{{ $order->fecha_pedido }}</td><td>{{ $order->fecha_entrega ?? 'Sin fecha' }}</td><td>{{ $order->estado ?? 'Sin estado' }}</td><td><a class="chef-button chef-button-secondary" href="{{ route('orders.show', $order->id_pedido) }}">Ver pedido</a></td></tr>
                    @empty<tr><td colspan="6">No hay pedidos para estos filtros.</td></tr>@endforelse
                    </tbody></table></div>
            {{ $orders->links() }}
        </section>
    </div>
@endsection
