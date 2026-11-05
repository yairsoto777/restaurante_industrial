@extends('layouts.app')
@section('title', 'Comprobante de recepción')
@section('content')
    @php
        $user = auth()->user();
        $rolesForSite = $user->roles()->get()->filter(fn ($role) => (int) ($role->pivot->id_sede ?? 0) === (int) $site->id_sede);
        $isWarehouseManager = $isWarehouseManager ?? $rolesForSite->contains(fn ($role) => \App\Support\EmploymentRoles::matches($role->nombre, 'Jefe de almacen'));
        $isReceptionist = $rolesForSite->contains(fn ($role) => \App\Support\EmploymentRoles::matches($role->nombre, 'Recepcion de insumos'));
        $pendingState = (string) config('receptions.pending', 'Pendiente de revisión');
        $releasedStates = (array) config('receptions.released_states', []);
        $confirmedState = 'Confirmada';
        $status = (string) ($receipt->estado ?? 'Sin estado');
        $canReview = $isWarehouseManager && $status === $pendingState && isset($locations) && Route::has('receptions.review.confirm') && Route::has('receptions.review.return');
        $canCorrect = $isReceptionist && !$isWarehouseManager && (int) ($receipt->id_usuario ?? 0) === (int) $user->id_usuario
            && in_array($status, $releasedStates, true) && (bool) config('receptions.capture_enabled')
            && Route::has('receptions.corrections.create');
        $lineCount = $details->count();
        $discrepancyCount = 0;
        $incidentCount = 0;
        $acceptedLines = 0;
        foreach ($details as $item) {
            $meta = json_decode((string) ($item->observaciones ?? ''), true);
            $meta = is_array($meta) ? $meta : [];
            $capture = is_array($meta['captura'] ?? null) ? $meta['captura'] : [];
            $delivered = (float) ($item->cantidad_entregada ?? 0);
            $accepted = (float) ($item->cantidad_aceptada ?? 0);
            $missing = (float) ($meta['faltante_entrega'] ?? 0);
            if ($delivered > $accepted || $missing > 0) $discrepancyCount++;
            if (trim((string) ($capture['incidencia'] ?? '')) !== '') $incidentCount++;
            if ($accepted > 0) $acceptedLines++;
        }
    @endphp

    <div class="chef-page">
        <section class="chef-hero">
            <div class="chef-hero-icon"><x-icon name="box" /></div>
            <div class="chef-hero-copy">
                <span class="chef-eyebrow">{{ $site->nombre }} · {{ $isWarehouseManager ? 'Supervisión de almacén' : 'Recepción de insumos' }}</span>
                <h1>Comprobante de recepción #{{ $receipt->id_recepcion }}</h1>
                <p>Pedido #{{ $receipt->id_pedido }} · {{ $receipt->fecha_recepcion ?: 'Fecha no disponible' }}</p>
            </div>
        </section>

        @if(session('status'))
            <section class="chef-panel" role="status"><strong>{{ session('status') }}</strong></section>
        @endif
        @if($errors->any())
            <section class="chef-panel" role="alert">
                <strong>No se pudo completar la operación:</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </section>
        @endif

        <section class="chef-panel" aria-labelledby="reception-summary-title">
            <div class="chef-panel-heading">
                <h2 id="reception-summary-title">Resumen de la recepción</h2>
                <span class="chef-pill chef-pill-slate">{{ $status }}</span>
            </div>
            <div class="chef-form">
                <div><strong>Pedido:</strong> #{{ $receipt->id_pedido }}</div>
                <div><strong>Partidas capturadas:</strong> {{ $lineCount }}</div>
                <div><strong>Partidas con cantidad aceptada:</strong> {{ $acceptedLines }}</div>
                <div><strong>Partidas con diferencias:</strong> {{ $discrepancyCount }}</div>
                <div><strong>Incidencias descritas:</strong> {{ $incidentCount }}</div>
                @if(!empty($receipt->proveedor_nombre))<div><strong>Proveedor:</strong> {{ $receipt->proveedor_nombre }}</div>@endif
                @if(!empty($receipt->receptor_nombre))<div><strong>Registró:</strong> {{ trim(($receipt->receptor_nombre ?? '').' '.($receipt->receptor_apellido ?? '')) }}</div>@endif
                @if(!empty($receipt->fecha_confirmacion))<div><strong>Fecha de confirmación:</strong> {{ $receipt->fecha_confirmacion }}</div>@endif
            </div>
            @if($status === $confirmedState)
                <p role="status"><strong>Confirmada:</strong> la autorización de ingreso al inventario corresponde al Jefe de Almacén.</p>
            @elseif($status === $pendingState)
                <p><strong>Pendiente de revisión:</strong> esta captura aún no modifica las existencias.</p>
            @elseif(in_array($status, $releasedStates, true))
                <p><strong>Devuelta, cancelada o rechazada:</strong> esta recepción se conserva como historial y no se contabiliza como ingreso al inventario.</p>
            @else
                <p>Estado registrado: {{ $status }}. Consulta al responsable de almacén si requiere seguimiento.</p>
            @endif
            @if(!empty($receipt->observaciones))<p><strong>Observaciones generales:</strong> {{ $receipt->observaciones }}</p>@endif
        </section>

        <section class="chef-panel" aria-labelledby="reception-lines-title">
            <div class="chef-panel-heading"><h2 id="reception-lines-title">Mercancía inspeccionada</h2><span class="chef-count">{{ $lineCount }} partidas</span></div>
            <p>Las cantidades se muestran en la unidad propia de cada alimento. Se distinguen lo entregado, lo aceptado, lo rechazado y lo no entregado.</p>
            <div class="chef-table-wrap">
                <table class="chef-table">
                    <thead><tr>
                        <th>Alimento</th><th>Entregada</th><th>Aceptada</th><th>Rechazada</th><th>No entregada</th>
                        <th>Lote y caducidad</th><th>Resultado e incidencias</th>
                    </tr></thead>
                    <tbody>
                    @forelse($details as $detail)
                        @php
                            $meta = json_decode((string) ($detail->observaciones ?? ''), true);
                            $meta = is_array($meta) ? $meta : [];
                            $capture = is_array($meta['captura'] ?? null) ? $meta['captura'] : [];
                            $delivered = (float) ($detail->cantidad_entregada ?? 0);
                            $accepted = (float) ($detail->cantidad_aceptada ?? 0);
                            $rejected = max(0, $delivered - $accepted);
                            $missing = array_key_exists('faltante_entrega', $meta) ? (float) $meta['faltante_entrega'] : null;
                            $unit = (string) ($detail->unidad ?? '');
                            $observation = trim((string) ($capture['observaciones'] ?? ''));
                            $incident = trim((string) ($capture['incidencia'] ?? ''));
                            $legacyNote = ! $meta ? trim((string) ($detail->observaciones ?? '')) : '';
                        @endphp
                        <tr>
                            <td><strong>{{ $detail->insumo_nombre ?: 'Alimento no disponible' }}</strong></td>
                            <td>{{ number_format($delivered, 3, '.', ',') }} {{ $unit }}</td>
                            <td>{{ number_format($accepted, 3, '.', ',') }} {{ $unit }}</td>
                            <td>{{ number_format($rejected, 3, '.', ',') }} {{ $unit }}</td>
                            <td>{{ $missing === null ? 'Sin dato histórico' : number_format($missing, 3, '.', ',').' '.$unit }}</td>
                            <td><strong>{{ $detail->codigo_lote ?: 'Sin código de lote' }}</strong><div>{{ $detail->caducidad ?: 'Caducidad no indicada' }}</div></td>
                            <td>
                                <strong>{{ $detail->resultado_revision ?: 'Sin resultado' }}</strong>
                                @if($observation !== '')<p><strong>Observación:</strong> {{ $observation }}</p>@endif
                                @if($incident !== '')<p><strong>Incidencia:</strong> {{ $incident }}</p>@endif
                                @if($legacyNote !== '')<p>{{ $legacyNote }}</p>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><strong>Esta recepción no tiene partidas registradas.</strong></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if(isset($incidents) && $incidents->isNotEmpty())
                <h3>Seguimiento de incidencias</h3>
                <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Folio</th><th>Descripción</th><th>Estado</th><th>Respuesta</th></tr></thead>
                        <tbody>@foreach($incidents as $incident)
                            <tr><td>#{{ $incident->id_incidencia }}</td><td>{{ $incident->descripcion }}</td><td>{{ $incident->estado }}</td><td>{{ $incident->respuesta ?: 'Pendiente de respuesta' }}</td></tr>
                        @endforeach</tbody>
                    </table></div>
            @endif
        </section>

        @if($canReview)
            <section class="chef-panel" aria-labelledby="review-title">
                <div class="chef-panel-heading"><h2 id="review-title">Revisión exclusiva del Jefe de Almacén</h2></div>
                <p>Verifica mercancía, calidad, lotes, caducidad e incidencias antes de decidir. Confirmar suma al inventario únicamente las cantidades aceptadas.</p>
                <form method="POST" action="{{ route('receptions.review.confirm', $receipt->id_recepcion) }}" data-reception-decision>
                    @csrf
                    <div class="chef-form">
                        <label>Ubicación de ingreso
                            <select name="id_ubicacion" required>
                                <option value="">Selecciona una ubicación de esta sede</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id_ubicacion_inv }}" @selected((string) old('id_ubicacion') === (string) $location->id_ubicacion_inv)>{{ $location->nombre }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Observación de revisión
                            <textarea name="observacion_revision" minlength="5" maxlength="1000" required placeholder="Describe qué verificaste antes de confirmar">{{ old('observacion_revision') }}</textarea>
                        </label>
                    </div>
                    <button class="chef-button chef-button-primary" type="submit" data-confirm-message="¿Confirmar esta recepción? Solo la cantidad aceptada se sumará al inventario.">Confirmar ingreso al inventario</button>
                </form>
                <hr>
                <p>Si existen datos incorrectos, devuelve la captura para que la recepcionista registre una versión corregida. La captura original permanecerá en el historial.</p>
                <form method="POST" action="{{ route('receptions.review.return', $receipt->id_recepcion) }}" data-reception-decision>
                    @csrf
                    <label>Motivo de devolución
                        <textarea name="motivo" minlength="10" maxlength="1000" required placeholder="Explica qué debe corregir la recepcionista">{{ old('motivo') }}</textarea>
                    </label>
                    <div class="chef-form-actions"><button class="chef-button chef-button-secondary" type="submit" data-confirm-message="¿Devolver esta recepción para corrección?">Devolver para corrección</button></div>
                </form>
            </section>
        @elseif($isWarehouseManager && $status === $pendingState)
            <section class="chef-panel"><p>La revisión está pendiente, pero las rutas o ubicaciones necesarias no están disponibles. Revisa la configuración del módulo.</p></section>
        @endif

        @if($canCorrect)
            <section class="chef-panel" aria-labelledby="correction-title">
                <div class="chef-panel-heading"><h2 id="correction-title">Corregir recepción</h2></div>
                <p>Esta recepción fue devuelta, cancelada o rechazada. Puedes crear una nueva captura usando los datos anteriores como referencia; no se borrará ni modificará este comprobante.</p>
                <a class="chef-button chef-button-primary" href="{{ route('receptions.corrections.create', $receipt->id_recepcion) }}">Corregir y volver a enviar</a>
            </section>
        @endif

        <div class="chef-form-actions">
            @if(Route::has('receptions.orders.show'))<a class="chef-button chef-button-secondary" href="{{ route('receptions.orders.show', $receipt->id_pedido) }}">Volver al pedido</a>@endif
            @if(Route::has('receptions.index'))<a class="chef-button chef-button-secondary" href="{{ route('receptions.index') }}">Ver pedidos y recepciones</a>@endif
        </div>
    </div>
    <script>

        document.querySelectorAll('[data-reception-decision]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) return;
                const button = event.submitter;
                if (button?.dataset.confirmMessage && !window.confirm(button.dataset.confirmMessage)) {
                    event.preventDefault();
                    return;
                }
                if (button) {
                    button.disabled = true;
                    button.textContent = 'Procesando…';
                }
            });
        });
    </script>
@endsection
