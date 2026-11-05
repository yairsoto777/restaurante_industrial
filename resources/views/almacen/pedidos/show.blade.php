@extends('layouts.app')
@section('title', 'Detalle de pedido')
@section('content')
    <div class="chef-page">
        <section class="chef-hero"><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Abastecimiento</span><h1>Pedido #{{ $order->id_pedido }}</h1><p>Proveedor: {{ $order->proveedor_nombre ?? 'Proveedor no encontrado' }} · Estado: {{ $order->estado }}</p></div></section>
        <section class="chef-panel"><h2>Información de compra</h2><p>Fecha del pedido: {{ $order->fecha_pedido }} · Entrega prevista: {{ $order->fecha_entrega ?? 'No definida' }}</p><p>{{ $order->observaciones }}</p>
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Insumo</th><th>Cantidad solicitada</th><th>Observaciones</th></tr></thead><tbody>
                    @forelse($details as $detail)<tr><td>{{ $detail->insumo ?? 'Insumo no encontrado' }}</td><td>{{ number_format((float)$detail->cantidad, 3) }} {{ $detail->unidad }}</td><td>{{ $detail->especificaciones ?? '—' }}</td></tr>
                    @empty<tr><td colspan="3">Sin partidas.</td></tr>@endforelse</tbody></table></div>
        </section>
        <section class="chef-panel"><h2>Recepciones asociadas</h2>
            @forelse($receipts as $receipt)<p><a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $order->id_pedido) }}">Recepción #{{ $receipt->id_recepcion }} · {{ $receipt->fecha_recepcion }} · {{ $receipt->estado }}</a></p>
            @empty<p>Este pedido todavía no tiene recepciones registradas.</p>@endforelse
            <p>La captura física se realiza desde Recepción de insumos; esta pantalla no aumenta el inventario.</p>
        </section>
        <a class="chef-button chef-button-secondary" href="{{ route('orders.index') }}">Volver a pedidos</a>
    </div>
@endsection

F
