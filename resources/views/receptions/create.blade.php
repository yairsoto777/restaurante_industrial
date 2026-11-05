@extends('layouts.app')
@section('title', 'Registrar recepción física')
@section('content')
    <div class="chef-page">
        <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Recepción física de insumos</span><h1>Recibir pedido #{{ $order->id_pedido }}</h1><p>{{ $provider }} · Pedido: {{ $order->fecha_pedido ?? 'Sin fecha' }} · Entrega: {{ $order->fecha_entrega ?? 'Sin fecha' }}</p></div></section>
        <section class="chef-panel">
            <p><strong>Captura de mercancía:</strong> registra las cantidades efectivamente entregadas y aceptadas, su lote, caducidad y cualquier incidencia. La recepción queda pendiente de revisión y <strong>no aumenta el inventario</strong> en este paso.</p>
            @if($errors->any())<div class="chef-empty" role="alert"><strong>No se pudo guardar la recepción</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('receptions.store', $order->id_pedido) }}" data-capture-form>
                @csrf
                <input type="hidden" name="token" value="{{ old('token', $token) }}">
                @foreach($details as $index => $detail)
                    <fieldset class="chef-panel" data-reception-line>
                        <legend><strong>{{ $detail->insumo_nombre }}</strong> · Solicitada: {{ $detail->cantidad }} · Pendiente: {{ $detail->pendiente }}</legend>
                        @if($detail->especificaciones)<p>{{ $detail->especificaciones }}</p>@endif
                        <input type="hidden" name="detalles[{{ $index }}][id]" value="{{ $detail->id_detalle_pedido }}">
                        <div class="chef-form">
                            <label>Entregada<input data-delivered type="number" min="0" max="{{ $detail->pendiente }}" step="{{ $detail->permite_fracciones ? '0.001' : '1' }}" name="detalles[{{ $index }}][entregada]" value="{{ old("detalles.$index.entregada", 0) }}" required></label>
                            <label>Aceptada<input data-accepted type="number" min="0" max="{{ $detail->pendiente }}" step="{{ $detail->permite_fracciones ? '0.001' : '1' }}" name="detalles[{{ $index }}][aceptada]" value="{{ old("detalles.$index.aceptada", 0) }}" required></label>
                            <label>Rechazada<output data-rejected>0.000</output></label>
                            <label>Faltante de entrega<output data-missing data-pending="{{ $detail->pendiente }}">{{ $detail->pendiente }}</output></label>
                            <label>Código de lote<input maxlength="100" name="detalles[{{ $index }}][codigo_lote]" value="{{ old("detalles.$index.codigo_lote") }}"></label>
                            <label>Caducidad<input type="date" name="detalles[{{ $index }}][caducidad]" value="{{ old("detalles.$index.caducidad") }}"></label>
                            <label>Resultado de revisión<input maxlength="50" name="detalles[{{ $index }}][resultado_revision]" value="{{ old("detalles.$index.resultado_revision") }}" required placeholder="Ej. Aceptado o Aceptación parcial"></label>
                            <label>Observaciones<textarea maxlength="5000" name="detalles[{{ $index }}][observaciones]">{{ old("detalles.$index.observaciones") }}</textarea></label>
                            <label>Incidencia ante faltantes o rechazos<textarea data-incident maxlength="5000" name="detalles[{{ $index }}][incidencia]" placeholder="Describe el faltante, daño o rechazo">{{ old("detalles.$index.incidencia") }}</textarea></label>
                        </div>
                    </fieldset>
                @endforeach
                <label>Observaciones generales<textarea name="observaciones" maxlength="5000">{{ old('observaciones') }}</textarea></label>
                <div class="chef-form-actions"><button class="chef-button chef-button-primary" type="submit" data-submit-reception>Guardar pendiente de revisión</button><a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $order->id_pedido) }}">Volver al pedido</a></div>
            </form>
        </section>
    </div>
    <script>
        document.querySelectorAll('[data-reception-line]').forEach((line) => {
            const delivered = line.querySelector('[data-delivered]');
            const accepted = line.querySelector('[data-accepted]');
            const incident = line.querySelector('[data-incident]');
            const missing = line.querySelector('[data-missing]');
            const rejected = line.querySelector('[data-rejected]');
            const update = () => {
                const d = Number(delivered.value || 0);
                const a = Number(accepted.value || 0);
                const pending = Number(missing.dataset.pending || 0);
                rejected.textContent = Math.max(0, d - a).toFixed(3);
                missing.textContent = Math.max(0, pending - d).toFixed(3);
                accepted.setCustomValidity(a > d ? 'La cantidad aceptada no puede superar la entregada.' : '');
                incident.required = d < pending || a < d;
            };
            delivered.addEventListener('input', update);
            accepted.addEventListener('input', update);
            update();
        });
        const captureForm = document.querySelector('[data-capture-form]');
        captureForm?.addEventListener('submit', () => {
            const btn = captureForm.querySelector('[data-submit-reception]');
            if (captureForm.checkValidity() && btn) {
                btn.disabled = true;
                btn.textContent = 'Guardando recepción…';
            }
        });
    </script>
@endsection
