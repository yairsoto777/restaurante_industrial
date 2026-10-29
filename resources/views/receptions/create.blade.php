@extends('layouts.app')
@section('title', 'Registrar nueva recepción')
@section('content')
<div class="chef-page">
    <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Recepción</span><h1>Recibir pedido #{{ $order->id_pedido }}</h1><p>{{ $provider }} · Pedido: {{ $order->fecha_pedido ?? 'Sin fecha' }} · Entrega: {{ $order->fecha_entrega ?? 'Sin fecha' }}</p></div></section>
    <section class="chef-panel">
        <p>La recepción quedará pendiente de revisión. Las cantidades aceptadas reservan el pendiente de este pedido; el inventario no aumenta.</p>
        <form method="POST" action="{{ route('receptions.store', $order->id_pedido) }}">
            @csrf
            <input type="hidden" name="token" value="{{ old('token', $token) }}">
            @foreach($details as $index => $detail)
            <fieldset class="chef-panel" data-reception-line>
                <legend>{{ $detail->insumo_nombre }} · Solicitada: {{ $detail->cantidad }} · Pendiente: {{ $detail->pendiente }}</legend>
                <p>{{ $detail->especificaciones }}</p>
                <input type="hidden" name="detalles[{{ $index }}][id]" value="{{ $detail->id_detalle_pedido }}">
                <div class="chef-form">
                    <label>Entregada<input data-delivered type="number" min="0" max="{{ $detail->pendiente }}" step="{{ $detail->permite_fracciones ? '0.001' : '1' }}" name="detalles[{{ $index }}][entregada]" value="{{ old("detalles.$index.entregada", 0) }}" required></label>
                    <label>Aceptada<input data-accepted type="number" min="0" max="{{ $detail->pendiente }}" step="{{ $detail->permite_fracciones ? '0.001' : '1' }}" name="detalles[{{ $index }}][aceptada]" value="{{ old("detalles.$index.aceptada", 0) }}" required></label>
                    <label>Rechazada<output data-rejected>0.000</output></label>
                    <label>Faltante de entrega<output data-missing data-pending="{{ $detail->pendiente }}">{{ $detail->pendiente }}</output></label>
                    <label>Código de lote<input maxlength="100" name="detalles[{{ $index }}][codigo_lote]" value="{{ old("detalles.$index.codigo_lote") }}"></label>
                    <label>Caducidad<input type="date" name="detalles[{{ $index }}][caducidad]" value="{{ old("detalles.$index.caducidad") }}"></label>
                    <label>Resultado de revisión<input maxlength="50" name="detalles[{{ $index }}][resultado_revision]" value="{{ old("detalles.$index.resultado_revision") }}" required></label>
                    <label>Observaciones<textarea maxlength="5000" name="detalles[{{ $index }}][observaciones]">{{ old("detalles.$index.observaciones") }}</textarea></label>
                    <label>Incidencia (obligatoria ante faltantes o rechazos)<textarea maxlength="5000" name="detalles[{{ $index }}][incidencia]">{{ old("detalles.$index.incidencia") }}</textarea></label>
                </div>
            </fieldset>
            @endforeach
            <label>Observaciones generales<textarea name="observaciones" maxlength="5000">{{ old('observaciones') }}</textarea></label>
            <div class="chef-form-actions"><button class="chef-button chef-button-primary" type="submit">Guardar pendiente de revisión</button><a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $order->id_pedido) }}">Volver al pedido</a></div>
        </form>
    </section>
</div>
<script>
document.querySelectorAll('[data-reception-line]').forEach(line => {
    const delivered = line.querySelector('[data-delivered]');
    const accepted = line.querySelector('[data-accepted]');
    const missing = line.querySelector('[data-missing]');
    const update = () => {
        line.querySelector('[data-rejected]').textContent = Math.max(0, Number(delivered.value) - Number(accepted.value)).toFixed(3);
        missing.textContent = Math.max(0, Number(missing.dataset.pending) - Number(delivered.value)).toFixed(3);
    };
    delivered.addEventListener('input', update); accepted.addEventListener('input', update); update();
});
</script>
@endsection
