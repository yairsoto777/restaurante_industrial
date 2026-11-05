@extends('layouts.app')
@section('title', 'Registrar recepción física')
@section('content')
    <div class="chef-page">
        <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Recepción física</span><h1>Recibir pedido #{{ $order->id_pedido }}</h1><p>{{ $provider }} · Fecha prevista: {{ $order->fecha_entrega ?: '—' }}</p></div></section>
        <section class="chef-panel">
            <p>Registra cantidades entregadas y aceptadas. El stock solo aumenta después de que Almacén confirme la recepción.</p>
            @if(isset($originalReceipt))
                <div class="chef-empty" role="status"><strong>Corrección de recepción #{{ $originalReceipt->id_recepcion }}</strong>
                    <p>Se creará una recepción nueva conservando la anterior como antecedente. Revisa las cantidades pendientes y corrige los datos antes de guardar.</p>
                </div>
            @endif
            @if($errors->any())<div class="chef-empty" role="alert"><strong>No se pudo guardar:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('receptions.store', $order->id_pedido) }}" data-capture-form>
                @csrf
                <input type="hidden" name="token" value="{{ old('token', $token) }}">
                @foreach($details as $index => $detail)
                    @php($saved = $prefill[(int) $detail->id_detalle_pedido] ?? [])
                    <fieldset class="chef-panel" data-reception-line>
                        <legend><strong>{{ $detail->insumo_nombre }}</strong> · Solicitado: {{ $detail->cantidad }} · Pendiente: {{ $detail->pendiente }}</legend>
                        @if($detail->especificaciones)<p>{{ $detail->especificaciones }}</p>@endif
                        <input type="hidden" name="detalles[{{ $index }}][id]" value="{{ $detail->id_detalle_pedido }}">
                        <div class="chef-form">
                            <label>Entregado
                                <input type="number" data-delivered min="0" max="{{ $detail->pendiente }}" step="{{ $detail->permite_fracciones ? '0.001' : '1' }}" required name="detalles[{{ $index }}][entregada]" value="{{ old("detalles.$index.entregada", $saved['entregada'] ?? 0) }}">
                            </label>
                            <label>Aceptado
                                <input type="number" data-accepted min="0" max="{{ $detail->pendiente }}" step="{{ $detail->permite_fracciones ? '0.001' : '1' }}" required name="detalles[{{ $index }}][aceptada]" value="{{ old("detalles.$index.aceptada", $saved['aceptada'] ?? 0) }}">
                            </label>
                            <div>Rechazado: <strong data-rejected>0.000</strong></div>
                            <div>Sin entregar: <strong data-missing data-pending="{{ $detail->pendiente }}">{{ $detail->pendiente }}</strong></div>
                            <label>Código de lote<input data-lot maxlength="100" name="detalles[{{ $index }}][codigo_lote]" value="{{ old("detalles.$index.codigo_lote", $saved['codigo_lote'] ?? '') }}"></label>
                            <label>Caducidad<input data-expiry type="date" name="detalles[{{ $index }}][caducidad]" value="{{ old("detalles.$index.caducidad", $saved['caducidad'] ?? '') }}"></label>
                            <label>Resultado de revisión
                                <select name="detalles[{{ $index }}][resultado_revision]" required>
                                    @foreach(['Sin entrega', 'Aceptado', 'Aceptación parcial', 'Rechazado'] as $status)
                                        <option value="{{ $status }}" @selected(old("detalles.$index.resultado_revision", $saved['resultado_revision'] ?? 'Sin entrega') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Observaciones<textarea maxlength="5000" name="detalles[{{ $index }}][observaciones]">{{ old("detalles.$index.observaciones", $saved['observaciones'] ?? '') }}</textarea></label>
                            <label>Incidencia por faltante o rechazo<textarea data-incident maxlength="5000" name="detalles[{{ $index }}][incidencia]" placeholder="Describe daños, diferencias o faltantes">{{ old("detalles.$index.incidencia", $saved['incidencia'] ?? '') }}</textarea></label>
                        </div>
                    </fieldset>
                @endforeach
                <label>Observaciones generales<textarea name="observaciones" maxlength="5000">{{ old('observaciones', isset($originalReceipt) ? 'Nueva captura corregida de recepción #'.$originalReceipt->id_recepcion : '') }}</textarea></label>
                <div class="chef-form-actions">
                    <button class="chef-button chef-button-primary" type="submit" data-submit-reception>Guardar pendiente de revisión</button>
                    <a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $order->id_pedido) }}">Cancelar</a>
                </div>
            </form>
        </section>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-capture-form]');
            form?.querySelectorAll('[data-reception-line]').forEach(line => {
                const delivered = line.querySelector('[data-delivered]');
                const accepted = line.querySelector('[data-accepted]');
                const missing = line.querySelector('[data-missing]');
                const rejected = line.querySelector('[data-rejected]');
                const incident = line.querySelector('[data-incident]');
                const lot = line.querySelector('[data-lot]');
                const expiry = line.querySelector('[data-expiry]');
                const update = () => {
                    const d = Number(delivered.value || 0), a = Number(accepted.value || 0);
                    const pending = Number(missing.dataset.pending || 0);
                    rejected.textContent = Math.max(0, d-a).toFixed(3);
                    missing.textContent = Math.max(0, pending-d).toFixed(3);
                    accepted.setCustomValidity(a>d ? 'Lo aceptado no puede superar lo entregado.' : '');
                    incident.required = (d<pending || a<d);
                    lot.required = d>0;
                    expiry.required = d>0;
                };
                delivered.addEventListener('input', update);
                accepted.addEventListener('input', update);
                update();
            });
            form?.addEventListener('submit', event => {
                if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
                const btn = form.querySelector('[data-submit-reception]');
                if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
            });
        });
    </script>
@endsection
