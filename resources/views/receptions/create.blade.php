@extends('layouts.app')
@section('title', 'Registrar recepción física')
@section('content')
    <link rel="stylesheet" href="{{ asset('css/reception-capture.css') }}">
    @php($quantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.'))
    <div class="chef-page reception-capture">
        <section class="chef-hero capture-banner"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Recepción física</span><h1>Recibir pedido #{{ $order->id_pedido }}</h1><p>{{ $provider }} · Fecha prevista: {{ $order->fecha_entrega ?: '—' }}</p></div></section>
        <ol class="capture-steps" aria-label="Etapas del proceso">@foreach(['Verificar pedido', 'Capturar entrega', 'Revisar calidad', 'Guardar'] as $step)<li data-step="{{ $loop->index }}" @if($loop->first) aria-current="step" @endif><span>{{ $loop->iteration }}</span>{{ $step }}</li>@endforeach</ol>
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
                    <details class="capture-product" data-reception-line data-ingredient="{{ $detail->insumo_nombre }}" data-unit="{{ $detail->unidad_nombre ?: ($detail->unidad ?? '') }}" @if($loop->first || $errors->any()) open @endif>
                        <summary><span class="capture-number">{{ $loop->iteration }}</span><span class="capture-photo">@if($detail->imagen_url)<img src="{{ \Illuminate\Support\Str::startsWith($detail->imagen_url, ['http://', 'https://', '/']) ? $detail->imagen_url : \Illuminate\Support\Facades\Storage::url($detail->imagen_url) }}" alt="{{ $detail->insumo_nombre }}">@else<x-icon name="box" />@endif</span><span class="capture-product-title"><strong>{{ mb_strtoupper($detail->insumo_nombre) }}</strong><small>Unidad de medida: {{ $detail->unidad_nombre ?: $detail->unidad }}</small></span><span class="capture-toggle">Ver / ocultar</span></summary>
                        <div class="capture-product-body">
                        <div class="capture-order-quantities"><div><span>Cantidad solicitada</span><strong>{{ $quantity($detail->cantidad) }} <small>{{ $detail->unidad_nombre ?: $detail->unidad }}</small></strong></div><div><span>Pendiente de recibir</span><strong>{{ $quantity($detail->pendiente) }} <small>{{ $detail->unidad_nombre ?: $detail->unidad }}</small></strong></div></div>
                        <h2>Capturar entrega</h2><p class="capture-help">Registra lo que llegó y cuánto fue aceptado. El faltante se calcula sobre el pendiente real del pedido.</p>
                        @if($detail->especificaciones)<p>{{ $detail->especificaciones }}</p>@endif
                        <input type="hidden" name="detalles[{{ $index }}][id]" value="{{ $detail->id_detalle_pedido }}">
                        <div class="chef-form">
                            <label class="capture-blue">Cantidad entregada
                                <input type="number" data-delivered min="0" max="{{ $detail->pendiente }}" step="{{ $detail->permite_fracciones ? '0.001' : '1' }}" required name="detalles[{{ $index }}][entregada]" value="{{ old("detalles.$index.entregada", $quantity($saved['entregada'] ?? 0)) }}">
                            </label>
                            <label class="capture-green">Cantidad aceptada
                                <input type="number" data-accepted min="0" max="{{ $detail->pendiente }}" step="{{ $detail->permite_fracciones ? '0.001' : '1' }}" required name="detalles[{{ $index }}][aceptada]" value="{{ old("detalles.$index.aceptada", $quantity($saved['aceptada'] ?? 0)) }}">
                            </label>
                            <div class="capture-calculated capture-red">Cantidad rechazada<strong data-rejected>0</strong><small>Entregada menos aceptada · {{ $detail->unidad }}</small></div>
                            <div class="capture-calculated capture-yellow">Cantidad faltante<strong data-missing data-pending="{{ $detail->pendiente }}">{{ $quantity($detail->pendiente) }}</strong><small>Pendiente menos entregada · {{ $detail->unidad }}</small></div>
                        </div>
                        <div class="capture-progress" data-progress role="img" aria-label="Distribución de cantidades"><span data-bar-accepted></span><span data-bar-rejected></span><span data-bar-missing></span></div>
                        <p class="capture-help">Verde: aceptada · Rojo: rechazada · Amarillo: faltante. Aceptada + rechazada = entregada.</p>
                        <section class="capture-quality"><h2>Control de calidad</h2><p class="capture-help">Verifica el empaque, las condiciones físicas y la trazabilidad del producto.</p><div class="chef-form">
                            <label>Código de lote<input data-lot maxlength="100" name="detalles[{{ $index }}][codigo_lote]" value="{{ old("detalles.$index.codigo_lote", $saved['codigo_lote'] ?? '') }}"><small>Copia el código de lote del empaque o documento de entrega.</small></label>
                            <label>Caducidad<input data-expiry type="date" name="detalles[{{ $index }}][caducidad]" value="{{ old("detalles.$index.caducidad", $saved['caducidad'] ?? '') }}"><small>Obligatoria al recibir mercancía; lo aceptado no puede estar caducado.</small></label>
                            <label>Resultado de revisión
                                <select name="detalles[{{ $index }}][resultado_revision]" required>
                                    @foreach(['Sin entrega', 'Aceptado', 'Aceptación parcial', 'Rechazado'] as $status)
                                        <option value="{{ $status }}" @selected(old("detalles.$index.resultado_revision", $saved['resultado_revision'] ?? 'Sin entrega') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select><small>Selecciona el resultado de la inspección física.</small>
                            </label>
                            <label>Observaciones<textarea maxlength="5000" name="detalles[{{ $index }}][observaciones]">{{ old("detalles.$index.observaciones", $saved['observaciones'] ?? '') }}</textarea><small>Describe las condiciones y observaciones de la revisión.</small></label>
                            <label>Incidencia por faltante o rechazo<textarea data-incident maxlength="5000" name="detalles[{{ $index }}][incidencia]" placeholder="Describe daños, diferencias o faltantes">{{ old("detalles.$index.incidencia", $saved['incidencia'] ?? '') }}</textarea><small>Explica los daños, rechazos o faltantes cuando existan diferencias.</small></label>
                        </div>
                        </section></div>
                    </details>
                @endforeach
                <section class="rx-capture-summary" aria-label="Resumen antes de guardar"><h2>Resumen de la entrega</h2><ul data-capture-summary></ul><p>Las cantidades se muestran por insumo y unidad. La recepción se enviará pendiente de revisión; Almacén confirmará su ingreso.</p></section>
                <label>Observaciones generales<textarea name="observaciones" maxlength="5000">{{ old('observaciones', isset($originalReceipt) ? 'Nueva captura corregida de recepción #'.$originalReceipt->id_recepcion : '') }}</textarea></label>
                <div class="chef-form-actions">
                    <button class="chef-button chef-button-primary" type="submit" data-submit-reception>Guardar pendiente de revisión</button>
                    <a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $order->id_pedido) }}">Cancelar</a>
                </div>
            </form>
            <dialog class="rx-confirm-dialog" data-capture-dialog aria-labelledby="capture-dialog-title"><h2 id="capture-dialog-title">Revisar recepción antes de guardar</h2><p>Comprueba las cantidades e incidencias. Se guardará como pendiente de revisión y no modificará el inventario.</p><div class="rx-capture-summary"><ul data-dialog-summary></ul></div><div class="chef-form-actions"><button class="chef-button chef-button-secondary" type="button" data-capture-back>Seguir revisando</button><button class="chef-button chef-button-primary" type="button" data-capture-confirm>Confirmar registro</button></div></dialog>
        </section>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-capture-form]');
            const dialog = document.querySelector('[data-capture-dialog]');
            let confirmed = false;
            const format = value => Number(Number(value).toFixed(3)).toString();
            const milli = value => { const [whole, fraction = ''] = String(value || 0).split('.'); return Number(whole)*1000 + Number(fraction.padEnd(3, '0').slice(0, 3)); };
            const setStep = index => document.querySelectorAll('[data-step]').forEach(step => { if (+step.dataset.step === index) step.setAttribute('aria-current', 'step'); else step.removeAttribute('aria-current'); });
            form.addEventListener('focusin', event => setStep(event.target.closest('.capture-quality') ? 2 : 1));
            form.addEventListener('invalid', event => { const card = event.target.closest('details'); if (card) card.open = true; }, true);
            const summary = () => {
                const list = form.querySelector('[data-capture-summary]');
                list.replaceChildren();
                form.querySelectorAll('[data-reception-line]').forEach(line => {
                    const item = document.createElement('li');
                    item.textContent = `${line.dataset.ingredient} (${line.dataset.unit || 'unidad base'}): entregada ${line.querySelector('[data-delivered]').value || 0}, aceptada ${line.querySelector('[data-accepted]').value || 0}, rechazada ${line.querySelector('[data-rejected]').textContent}, faltante ${line.querySelector('[data-missing]').textContent}.`;
                    const note = line.querySelector('[data-incident]').value.trim();
                    if (note) item.textContent += ` Incidencia: ${note}`;
                    list.append(item);
                });
            };
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
                    rejected.textContent = format(Math.max(0, milli(delivered.value)-milli(accepted.value))/1000);
                    missing.textContent = format(Math.max(0, milli(missing.dataset.pending)-milli(delivered.value))/1000);
                    const parts = [Math.max(0, Math.min(a,d,pending)), Math.max(0, Math.min(d,pending)-Math.min(a,d,pending)), Math.max(0,pending-d)];
                    ['accepted','rejected','missing'].forEach((key,i) => line.querySelector('[data-bar-'+key+']').style.width = (pending > 0 ? parts[i]/pending*100 : 0)+'%');
                    line.querySelector('[data-progress]').setAttribute('aria-label', 'Aceptada '+format(a)+', rechazada '+rejected.textContent+', faltante '+missing.textContent+' '+line.dataset.unit);
                    accepted.max = delivered.value || 0;
                    accepted.setCustomValidity(a>d ? 'Lo aceptado no puede superar lo entregado.' : '');
                    incident.required = (d<pending || a<d);
                    lot.required = d>0;
                    expiry.required = d>0;
                };
                delivered.addEventListener('input', update);
                accepted.addEventListener('input', update);
                update();
            });
            summary();
            form.addEventListener('input', summary);
            dialog.querySelector('[data-capture-back]').addEventListener('click', () => { dialog.close(); setStep(2); });
            dialog.querySelector('[data-capture-confirm]').addEventListener('click', () => {
                confirmed = true;
                dialog.close();
                form.requestSubmit();
            });
            form?.addEventListener('submit', event => {
                if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
                if (!confirmed) {
                    event.preventDefault();
                    summary();
                    dialog.querySelector('[data-dialog-summary]').replaceChildren(...Array.from(form.querySelector('[data-capture-summary]').children, item => item.cloneNode(true)));
                    setStep(3); dialog.showModal();
                    return;
                }
                const btn = form.querySelector('[data-submit-reception]');
                if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
            });
        });
    </script>
@endsection
