@extends('almacen.workspace.layout')
@section('title','Pedidos de insumos')
@section('banner','orders')
@section('banner-title','Abastecimiento con cada entrega en orden')
@section('banner-copy','Programa tus compras, detalla los insumos necesarios y da seguimiento a las entregas de tu sede.')
@section('workspace')
<section class="wh-card"><header class="wh-card-heading"><div><h2>Control de pedidos</h2><p class="wh-note">La creación de pedidos no registra entradas al inventario.</p></div><button class="wh-button" type="button" data-open-dialog="warehouse-order"><x-icon name="plus"/>Nuevo pedido</button></header>
<form method="GET" class="wh-filters"><label>Folio o proveedor<input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar pedido"></label><label>Proveedor<select name="proveedor"><option value="">Todos</option>@foreach($suppliers as $s)<option value="{{ $s->id_proveedor }}" @selected((string)($filters['proveedor'] ?? '') === (string)$s->id_proveedor)>{{ $s->nombre }}</option>@endforeach</select></label><label>Fecha de entrega<input type="date" name="fecha" value="{{ $filters['fecha'] ?? '' }}"></label><label>Estado<select name="estado"><option value="">Todos</option>@foreach($states as $s)<option @selected(($filters['estado'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></label><button class="wh-button secondary">Filtrar</button><a class="wh-clear" href="{{ route('orders.index') }}">Limpiar</a></form>
<div class="wh-table-wrap"><table class="wh-table"><thead><tr><th>Folio</th><th>Proveedor</th><th>Entrega prevista</th><th>Insumos / cantidades</th><th>Importe estimado</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>@forelse($orders as $order)
@php($lines = $details->get($order->id_pedido,collect()))
@php($hasCost = $lines->isNotEmpty() && $lines->every(fn ($l) => isset($l->precio_unitario) && is_numeric($l->precio_unitario)))
<tr><td><a href="{{ route('orders.show',$order->id_pedido) }}">#{{ $order->id_pedido }}</a></td><td><strong>{{ $order->proveedor_nombre }}</strong></td><td>{{ $order->fecha_entrega ?: 'Sin fecha' }}</td><td>@foreach($lines as $l)<div class="wh-order-item"><strong>{{ mb_strtoupper($l->nombre ?? 'Insumo no disponible') }}</strong><span>{{ \App\Support\WarehouseWorkspace::quantity($l->cantidad) }} {{ $l->unidad }}</span></div>@endforeach</td><td>{{ $hasCost ? '$'.number_format($lines->sum(fn ($l) => (float)$l->cantidad*(float)$l->precio_unitario),2).' MXN' : 'Sin costo registrado' }}</td><td><span class="wh-badge {{ \App\Support\WarehouseWorkspace::badgeTone($order->estado) }}">{{ $order->estado ?: 'Sin estado' }}</span></td><td><a class="wh-button secondary small" href="{{ route('orders.show',$order->id_pedido) }}">Ver pedido</a></td></tr>
@empty<tr><td colspan="7" class="wh-empty">No hay pedidos que coincidan con los filtros.</td></tr>@endforelse</tbody></table></div><div class="wh-pagination">{{ $orders->links() }}</div></section>
@include('almacen.workspace.order-form')
@endsection
