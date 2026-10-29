@extends('layouts.app')
@section('title', 'Recepción registrada')
@section('content')
<div class="chef-page">
    <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }}</span><h1>Recepción #{{ $receipt->id_recepcion }}</h1><p>Pedido #{{ $receipt->id_pedido }} · {{ $receipt->fecha_recepcion }} · {{ $receipt->estado }}</p></div></section>
    <section class="chef-panel">
        <p>{{ $receipt->observaciones }}</p>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Insumo</th><th>Entregada</th><th>Aceptada</th><th>Rechazada</th><th>Faltante de entrega</th><th>Lote / Caducidad</th><th>Revisión y observaciones</th></tr></thead><tbody>
        @foreach($details as $detail)
        @php($metadata = json_decode($detail->observaciones ?? '', true))
        <tr><td>{{ $detail->insumo_nombre }}</td><td>{{ $detail->cantidad_entregada }}</td><td>{{ $detail->cantidad_aceptada }}</td><td>{{ number_format((float) $detail->cantidad_entregada - (float) $detail->cantidad_aceptada, 3, '.', '') }}</td><td>{{ $metadata['faltante_entrega'] ?? 'Sin dato histórico' }}</td><td>{{ $detail->codigo_lote }} / {{ $detail->caducidad }}</td><td>{{ $detail->resultado_revision }}<p>{{ $metadata['captura']['observaciones'] ?? $detail->observaciones }}</p><p>{{ $metadata['captura']['incidencia'] ?? '' }}</p></td></tr>
        @endforeach
        </tbody></table></div>
    </section>
    <a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $receipt->id_pedido) }}">Volver al pedido</a>
</div>
@endsection
