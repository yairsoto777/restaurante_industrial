@extends('layouts.app')
@section('title', 'Detalle del pedido')
@section('content')
    @php
        $canCapture = (bool) config('receptions.capture_enabled') && auth()->user()->roles()->get()->contains(
            fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede && \App\Support\EmploymentRoles::matches($role->nombre, 'Recepcion de insumos')
        ) && in_array($order->estado, (array) config('receptions.order_states', []), true);
    @endphp
    <div class="chef-page">
        <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }}</span><h1>Pedido #{{ $order->id_pedido }}</h1><p>{{ $order->proveedor_nombre ?: 'Proveedor sin datos' }} · {{ $order->estado ?: 'Sin estado' }}</p></div></section>
        <div class="chef-form-actions"><a class="chef-button chef-button-secondary" href="{{ route('receptions.index') }}">Volver</a>
            @if($canCapture)<a class="chef-button chef-button-primary" href="{{ route('receptions.create', $order->id_pedido) }}">Registrar recepción física</a>@endif</div>
        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Productos solicitados</h2></div>
            <p>Fecha de pedido: {{ $order->fecha_pedido ?: '—' }} · Entrega prevista: {{ $order->fecha_entrega ?: '—' }}</p>
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Insumo</th><th>Cantidad solicitada</th><th>Especificaciones</th></tr></thead><tbody>
                    @forelse($details as $detail)<tr><td>{{ $detail->insumo_nombre ?: 'No disponible' }}</td><td>{{ $detail->cantidad === null ? 'Sin dato' : number_format((float) $detail->cantidad, 3, '.', ',') }}</td><td>{{ $detail->especificaciones ?: '—' }}</td></tr>
                    @empty<tr><td colspan="3">Este pedido todavía no contiene partidas.</td></tr>@endforelse
                    </tbody></table></div>
        </section>
        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Historial de recepciones</h2><span class="chef-count">{{ $receipts->count() }}</span></div>
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Recepción</th><th>Fecha</th><th>Estado</th><th></th></tr></thead><tbody>
                    @forelse($receipts as $receipt)<tr><td>#{{ $receipt->id_recepcion }}</td><td>{{ $receipt->fecha_recepcion ?: '—' }}</td><td>{{ $receipt->estado ?: '—' }}</td>
                        <td><a class="chef-button chef-button-secondary" href="{{ $isWarehouseManager ? route('receptions.review.show', $receipt->id_recepcion) : route('receptions.receipts.show', $receipt->id_recepcion) }}">{{ $isWarehouseManager ? 'Revisar' : 'Consultar' }}</a></td></tr>
                    @empty<tr><td colspan="4">Sin recepciones registradas.</td></tr>@endforelse
                    </tbody></table></div>
        </section>
    </div>
@endsection
