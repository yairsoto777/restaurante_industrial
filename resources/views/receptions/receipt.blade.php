@extends('layouts.app')
@section('title', 'Detalle de recepción')
@section('content')
    @php
        $isWarehouseManager = auth()->user()->roles()->get()->contains(fn ($role) => (int) $role->pivot->id_sede === (int) $site->id_sede && \App\Support\EmploymentRoles::matches($role->nombre, 'Jefe de almacen'));
        $totals = ['lines' => 0, 'with_discrepancy' => 0];
        foreach ($details as $line) {
            $totals['lines']++;
            $meta = json_decode($line->observaciones ?? '', true);
            $missing = is_array($meta) ? (float) ($meta['faltante_entrega'] ?? 0) : 0;
            if ((float) $line->cantidad_entregada > (float) $line->cantidad_aceptada || $missing > 0) $totals['with_discrepancy']++;
        }
    @endphp
    <div class="chef-page">
        <section class="chef-hero"><div class="chef-hero-icon"><x-icon name="box" /></div><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · {{ $isWarehouseManager ? 'Supervisión de almacén' : 'Recepción de insumos' }}</span><h1>Recepción #{{ $receipt->id_recepcion }}</h1><p>Pedido #{{ $receipt->id_pedido }} · {{ $receipt->fecha_recepcion ?: 'Sin fecha' }} · {{ $receipt->estado ?: 'Sin estado' }}</p></div></section>
        <section class="chef-panel">
            <div class="chef-panel-heading"><h2>Resumen de entrega</h2><span class="chef-pill chef-pill-slate">{{ $receipt->estado ?: 'Sin estado' }}</span></div>
            <p>Partidas: <strong>{{ $totals['lines'] }}</strong> · Con discrepancias registradas: <strong>{{ $totals['with_discrepancy'] }}</strong></p>
            @if($receipt->observaciones)<p><strong>Observaciones generales:</strong> {{ $receipt->observaciones }}</p>@endif
            @if($isWarehouseManager)<p><strong>Revisión de almacén:</strong> comprueba cantidades, lote, caducidad y problemas registrados. Revisa cantidades, lotes e incidencias antes de tomar una decisión.</p>@endif
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Insumo</th><th>Entregada</th><th>Aceptada</th><th>Rechazada</th><th>Faltante</th><th>Lote / caducidad</th><th>Revisión, observaciones e incidencia</th></tr></thead><tbody>
                    @forelse($details as $detail)
                        @php
                            $metadata = json_decode($detail->observaciones ?? '', true);
                            $metadata = is_array($metadata) ? $metadata : [];
                            $captura = is_array($metadata['captura'] ?? null) ? $metadata['captura'] : [];
                            $rejected = max(0, (float) $detail->cantidad_entregada - (float) $detail->cantidad_aceptada);
                        @endphp
                        <tr>
                            <td><strong>{{ $detail->insumo_nombre ?: 'Insumo no disponible' }}</strong></td>
                            <td>{{ number_format((float) $detail->cantidad_entregada, 3, '.', ',') }}</td>
                            <td>{{ number_format((float) $detail->cantidad_aceptada, 3, '.', ',') }}</td>
                            <td>{{ number_format($rejected, 3, '.', ',') }}</td>
                            <td>{{ isset($metadata['faltante_entrega']) ? number_format((float) $metadata['faltante_entrega'], 3, '.', ',') : 'Sin dato histórico' }}</td>
                            <td>{{ $detail->codigo_lote ?: 'Sin código' }} / {{ $detail->caducidad ?: 'Sin fecha' }}</td>
                            <td><strong>{{ $detail->resultado_revision ?: 'Sin resultado' }}</strong>
                                @if(!empty($captura['observaciones']))<p>{{ $captura['observaciones'] }}</p>@elseif($detail->observaciones && empty($metadata))<p>{{ $detail->observaciones }}</p>@endif
                                @if(!empty($captura['incidencia']))<p><strong>Incidencia:</strong> {{ $captura['incidencia'] }}</p>@endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="7">Esta recepción no tiene partidas registradas.</td></tr>@endforelse
                    </tbody></table></div>
        </section>

        @if(session('status'))
            <section class="chef-panel"><p role="status">{{ session('status') }}</p></section>
        @endif
        @if($errors->any())
            <section class="chef-panel" role="alert"><strong>No se pudo completar la operación:</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </section>
        @endif
        @if($isWarehouseManager && $receipt->estado === config('receptions.pending'))
            <section class="chef-panel">
                <h2>Decisión del Jefe de almacén</h2>
                <p>Confirma únicamente las cantidades aceptadas. Este proceso genera entradas de inventario una sola vez.</p>
                <form method="POST" action="{{ route('receptions.review.confirm', $receipt->id_recepcion) }}">
                    @csrf
                    <div class="chef-form">
                        <label>Ubicación de ingreso
                            <select name="id_ubicacion" required>
                                <option value="">Seleccionar almacén de esta sede</option>
                                @foreach(($locations ?? collect()) as $location)
                                    <option value="{{ $location->id_ubicacion_inv }}" @selected(old('id_ubicacion') == $location->id_ubicacion_inv)>{{ $location->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Observaciones de revisión
                            <textarea name="observacion_revision" minlength="5" maxlength="1000" required>{{ old('observacion_revision') }}</textarea>
                        </label>
                    </div>
                    <button class="chef-button chef-button-primary" type="submit" onclick="return confirm('¿Confirmar la entrada de cantidades aceptadas al almacén?')">Confirmar ingreso al inventario</button>
                </form>
                <hr>
                <p>Si hay diferencias, devuelve la recepción para una nueva captura. La captura original permanece en el historial.</p>
                <form method="POST" action="{{ route('receptions.review.return', $receipt->id_recepcion) }}">
                    @csrf
                    <label>Motivo de devolución/cancelación
                        <textarea name="motivo" minlength="10" maxlength="1000" required>{{ old('motivo') }}</textarea>
                    </label>
                    <button class="chef-button chef-button-secondary" type="submit" onclick="return confirm('¿Devolver/cancelar esta recepción pendiente?')">Devolver para corrección / cancelar</button>
                </form>
            </section>
        @elseif($isWarehouseManager)
            <section class="chef-panel"><p>Esta recepción está en estado <strong>{{ $receipt->estado }}</strong>. No puede confirmarse ni devolverse nuevamente desde esta pantalla.</p></section>
        @endif
        <div class="chef-form-actions"><a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $receipt->id_pedido) }}">Volver al pedido</a><a class="chef-button chef-button-secondary" href="{{ route('receptions.index') }}">Volver a recepciones</a></div>
    </div>
@endsection
