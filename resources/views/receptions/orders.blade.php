@extends('layouts.app')
@section('title', 'Recepción de insumos')
@section('content')
    @php
        $isWarehouseManager = $isWarehouseManager ?? auth()->user()->roles()->get()->contains(fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede && \App\Support\EmploymentRoles::matches($role->nombre, 'Jefe de almacen'));
        $recentReceipts = $recentReceipts ?? collect();
        $receiptsAvailable = $receiptsAvailable ?? false;
        $pendingReceipts = $recentReceipts->filter(fn ($r) => str_contains(\Illuminate\Support\Str::lower((string) ($r->estado ?? '')), 'pendiente'))->count();
    @endphp
    <div class="chef-page">
        <section class="chef-hero">
            <div class="chef-hero-icon"><x-icon name="box" /></div>
            <div class="chef-hero-copy">
                <span class="chef-eyebrow">{{ $site->nombre }} · {{ $isWarehouseManager ? 'Jefe de almacén' : 'Recepción de insumos' }}</span>
                <h1>{{ $isWarehouseManager ? 'Supervisión de recepciones' : 'Recepción de insumos' }}</h1>
                <p>{{ $isWarehouseManager ? 'Consulta entregas capturadas, sus discrepancias y los pedidos relacionados. El registro físico corresponde al personal receptor.' : 'Consulta pedidos programados y registra las entregas físicas que te correspondan.' }}</p>
            </div>
        </section>

        @if($isWarehouseManager)
            <section class="chef-panel" aria-labelledby="supervision-title">
                <div class="chef-panel-heading">
                    <h2 id="supervision-title">Recepciones registradas para supervisión</h2>
                    <span class="chef-count">{{ $recentReceipts->count() }} recientes</span>
                </div>
                @if(!$receiptsAvailable)
                    <div class="chef-empty"><strong>Recepciones no disponibles</strong><p>Verifica que exista la tabla recepciones y su relación con pedidos.</p></div>
                @else
                    <p>Recepciones pendientes detectadas entre las últimas 20: <strong>{{ $pendingReceipts }}</strong>. Consulta el detalle para revisar cantidades, lotes y observaciones.</p>
                    <div class="chef-table-wrap">
                        <table class="chef-table">
                            <thead><tr><th>Recepción</th><th>Pedido</th><th>Fecha registrada</th><th>Estado</th><th>Acción</th></tr></thead>
                            <tbody>
                            @forelse($recentReceipts as $receipt)
                                <tr>
                                    <td>#{{ $receipt->id_recepcion }}</td>
                                    <td>#{{ $receipt->id_pedido }}</td>
                                    <td>{{ $receipt->fecha_recepcion ?: 'Sin fecha' }}</td>
                                    <td><span class="chef-pill chef-pill-slate">{{ $receipt->estado ?: 'Sin estado' }}</span></td>
                                    <td>
                                        <a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $receipt->id_pedido) }}">Ver pedido</a>
                                        <a class="chef-button chef-button-primary" href="{{ ($isWarehouseManager ? route('receptions.review.show', $receipt->id_recepcion) : route('receptions.receipts.show', $receipt->id_recepcion)) }}">Revisar recepción</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><div class="chef-empty"><strong>No hay recepciones registradas en esta sede</strong><p>Cuando el personal de Recepción de insumos capture una entrega, aparecerá aquí.</p></div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <p>Se muestran las 20 recepciones más recientes. Esta pantalla no confirma entradas ni modifica inventario.</p>
                @endif
            </section>
        @endif

        <section class="chef-panel" aria-labelledby="scheduled-orders-title">
            <div class="chef-panel-heading"><h2 id="scheduled-orders-title">{{ $isWarehouseManager ? 'Pedidos relacionados' : 'Pedidos programados' }}</h2><span class="chef-count">{{ $orders->total() }} resultados</span></div>
            <form class="chef-filters" method="GET" action="{{ route('receptions.index') }}">
                <label class="chef-search"><span class="chef-sr-only">Número de pedido o proveedor</span><x-icon name="search" /><input name="q" type="search" maxlength="150" value="{{ $search }}" placeholder="Número de pedido o proveedor"></label>
                <label>Fecha de entrega<input type="date" name="fecha_entrega" value="{{ $filters['fecha_entrega'] ?? '' }}"></label>
                <label>Estado<select name="estado"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state }}" @selected(($filters['estado'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></label>
                <button class="chef-button chef-button-secondary" type="submit">Filtrar</button><a class="chef-link-muted" href="{{ route('receptions.index') }}">Limpiar</a>
            </form>
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Pedido</th><th>Proveedor</th><th>Fecha</th><th>Entrega prevista</th><th>Estado</th><th>Detalle</th></tr></thead>
                    <tbody>@forelse($orders as $order)
                        <tr><td>#{{ $order->id_pedido }}</td><td>{{ $order->proveedor_nombre ?: 'Proveedor no encontrado' }}</td><td>{{ $order->fecha_pedido ?: 'Sin fecha' }}</td><td>{{ $order->fecha_entrega ?: 'Sin programar' }}</td><td><span class="chef-pill chef-pill-slate">{{ $order->estado ?: 'Sin estado' }}</span></td><td><a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $order->id_pedido) }}">Ver pedido</a></td></tr>
                    @empty<tr><td colspan="6"><div class="chef-empty"><strong>No se encontraron pedidos</strong><p>Verifica los filtros o la sede activa.</p></div></td></tr>@endforelse</tbody>
                </table></div>
            @if($orders->hasPages())<div class="chef-pagination">{{ $orders->links() }}</div>@endif
            @if($isWarehouseManager && Route::has('orders.create'))<p><a class="chef-button chef-button-secondary" href="{{ route('orders.create') }}">Ir a crear un pedido</a></p>@endif
        </section>
    </div>
@endsection
