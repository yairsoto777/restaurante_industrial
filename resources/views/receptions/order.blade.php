@extends('layouts.app')
@section('title', 'Detalle del pedido')
@section('content')
<div class="chef-page">
    <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Recepción de insumos</span><h1>Pedido #{{ $order->id_pedido }}</h1><p>{{ $order->proveedor_nombre ?? 'Sin proveedor disponible' }}</p></div></section>
    <a class="chef-button chef-button-secondary" href="{{ route('receptions.index') }}">Volver a pedidos</a>
    @if(config('receptions.capture_enabled') && auth()->user()->roles()->get()->contains(fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede && \App\Support\EmploymentRoles::matches($role->nombre, 'Recepcion de insumos')))
    <a class="chef-button chef-button-primary" href="{{ route('receptions.create', $order->id_pedido) }}">Registrar nueva recepción</a>
    @endif
    <section class="chef-panel"><div class="chef-panel-heading"><h2>Datos del pedido</h2><span class="chef-pill chef-pill-slate">{{ $order->estado ?: 'Sin estado' }}</span></div>
        <p>Fecha de pedido: {{ $order->fecha_pedido ?? 'Sin fecha' }} · Entrega: {{ $order->fecha_entrega ?? 'Sin programar' }}</p>
        @if($order->observaciones)<p>{{ $order->observaciones }}</p>@endif
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Insumo</th><th>Cantidad solicitada (unidad base)</th><th>Especificaciones</th></tr></thead><tbody>
        @forelse($details as $detail)<tr><td>{{ $detail->insumo_nombre ?? 'Insumo no disponible' }}</td><td>{{ $detail->cantidad === null ? 'Sin cantidad' : number_format($detail->cantidad, 3, '.', ',') }}</td><td>{{ $detail->especificaciones ?: 'Sin especificaciones' }}</td></tr>
        @empty<tr><td colspan="3">Este pedido no tiene insumos registrados.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @if($receipts->isNotEmpty() && auth()->user()->roles()->get()->contains(fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede && \App\Support\EmploymentRoles::matches($role->nombre, 'Recepcion de insumos')))
    <section class="chef-panel"><h2>Recepciones registradas</h2>
        @foreach($receipts as $receipt)<p><a class="chef-button chef-button-secondary" href="{{ route('receptions.receipts.show', $receipt->id_recepcion) }}">Recepción #{{ $receipt->id_recepcion }} · {{ $receipt->fecha_recepcion }} · {{ $receipt->estado }}</a></p>@endforeach
    </section>
    @endif
</div>
@endsection
