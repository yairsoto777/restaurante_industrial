@extends('layouts.app')
@section('title', 'Detalle del pedido')
@section('content')
    @php
        $isWarehouseManager = $isWarehouseManager ?? auth()->user()->roles()->get()->contains(fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede && \App\Support\EmploymentRoles::matches($role->nombre, 'Jefe de almacen'));
        $canCapture = (bool) config('receptions.capture_enabled') && auth()->user()->roles()->get()->contains(fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede && \App\Support\EmploymentRoles::matches($role->nombre, 'Recepcion de insumos'));
    @endphp
    <div class="chef-page">
        <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · {{ $isWarehouseManager ? 'Supervisión de almacén' : 'Recepción de insumos' }}</span><h1>Pedido #{{ $order->id_pedido }}</h1><p>{{ $order->proveedor_nombre ?: 'Proveedor no encontrado' }} · Estado: {{ $order->estado ?: 'Sin estado' }}</p></div></section>
        <div class="chef-form-actions">
            <a class="chef-button chef-button-secondary" href="{{ route('receptions.index') }}">Volver a recepción</a>
            @if($canCapture)<a class="chef-button chef-button-primary" href="{{ route('receptions.create', $order->id_pedido) }}">Registrar recepción física</a>@endif
        </div>
        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Datos del pedido</h2><span class="chef-pill chef-pill-slate">{{ $order->estado ?: 'Sin estado' }}</span></div>
            <p><strong>Pedido:</strong> {{ $order->fecha_pedido ?: 'Sin fecha' }} · <strong>Entrega prevista:</strong> {{ $order->fecha_entrega ?: 'Sin programar' }}</p>
            @if($order->observaciones)<p><strong>Observaciones:</strong> {{ $order->observaciones }}</p>@endif
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Insumo</th><th>Cantidad solicitada</th><th>Especificaciones</th></tr></thead><tbody>
                    @forelse($details as $detail)<tr><td>{{ $detail->insumo_nombre ?: 'Insumo no disponible' }}</td><td>{{ $detail->cantidad === null ? 'Sin dato' : number_format((float) $detail->cantidad, 3, '.', ',') }}</td><td>{{ $detail->especificaciones ?: 'Sin especificaciones' }}</td></tr>
                    @empty<tr><td colspan="3">Este pedido no contiene partidas.</td></tr>@endforelse
                    </tbody></table></div>
        </section>
        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Historial de recepciones de este pedido</h2><span class="chef-count">{{ $receipts->count() }}</span></div>
            @if(!($receiptsAvailable ?? true))<p>El historial no está disponible en la estructura actual de la base de datos.</p>
            @else
                <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Recepción</th><th>Fecha</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
                        @forelse($receipts as $receipt)
                            <tr><td>#{{ $receipt->id_recepcion }}</td><td>{{ $receipt->fecha_recepcion ?: 'Sin fecha' }}</td><td><span class="chef-pill chef-pill-slate">{{ $receipt->estado ?: 'Sin estado' }}</span></td><td><a class="chef-button chef-button-secondary" href="{{ ($isWarehouseManager ? route('receptions.review.show', $receipt->id_recepcion) : route('receptions.receipts.show', $receipt->id_recepcion)) }}">{{ $isWarehouseManager ? 'Revisar recepción' : 'Ver recepción' }}</a></td></tr>
                        @empty<tr><td colspan="4"><div class="chef-empty"><strong>Sin recepciones todavía</strong><p>El pedido aún no tiene capturas registradas.</p></div></td></tr>@endforelse
                        </tbody></table></div>
            @endif
            @if($isWarehouseManager)<p>El Jefe de almacén consulta estas recepciones; la captura física se realiza desde el perfil de Recepción de insumos.</p>@endif
        </section>
    </div>
@endsection
