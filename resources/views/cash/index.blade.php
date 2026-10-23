@extends('layouts.app')
@section('title', 'Caja')
@section('content')
<div class="page-heading">
    <div><span class="section-kicker">Operación · {{ $site->nombre }}</span><h1>Caja</h1><p>Apertura y consulta de turnos de caja de la sede activa.</p></div>
    <a class="button" href="{{ route('cash.index') }}">Consultar caja vigente</a>
</div>

@if($cashSession)
<section class="panel">
    <div class="panel-head"><h2><x-icon name="cash"/> Turno #{{ $cashSession->id_corte_caja }}</h2><span class="badge">{{ $cashSession->estado }}</span></div>
    <dl class="detail-grid">
        <div><dt>Responsable</dt><dd>{{ $cashSession->cashier?->name ?: 'Usuario no disponible' }}</dd></div>
        <div><dt>Sede</dt><dd>{{ $site->nombre }}</dd></div>
        <div style="grid-column:1 / -1"><dt>Apertura ({{ config('app.timezone') }})</dt><dd>{{ $cashSession->fecha_apertura?->format('d/m/Y H:i:s') }}</dd></div>
        @if($cashSession->fecha_cierre)<div style="grid-column:1 / -1"><dt>{{ $cashSession->estado === \App\Models\CashSession::CANCELLED ? 'Anulación' : 'Cierre' }} ({{ config('app.timezone') }})</dt><dd>{{ $cashSession->fecha_cierre->format('d/m/Y H:i:s') }}</dd></div>@endif
    </dl>
    @if($cashSession->observaciones)<div><strong>Observaciones de apertura</strong><p>{{ $cashSession->observaciones }}</p></div>@endif
    @if($cashSession->estado === \App\Models\CashSession::CANCELLED)
        <p>Esta apertura está anulada y se conserva para auditoría. No constituye un corte financiero.</p>
        <a class="button primary" href="{{ route('cash.index') }}">{{ $canStartOwnSession ? 'Ir a Caja para abrir un nuevo turno' : 'Volver a Caja' }}</a>
    @elseif($canCorrect)
        <div class="actions"><a class="button primary" href="{{ route('cash.fund.edit', $cashSession) }}">Modificar fondo inicial</a><a class="button danger" href="{{ route('cash.cancel.confirm', $cashSession) }}">Anular apertura</a></div>
    @elseif($correctionBlock)<p class="alert error">{{ $correctionBlock }}</p>@endif
</section>
<div class="kpi-grid">
    <article class="kpi-card"><div class="kpi-copy"><span>Fondo inicial</span><strong>{{ \App\Support\CashAmount::format($cashSession->fondo_inicial) }}</strong></div></article>
    <article class="kpi-card"><div class="kpi-copy"><span>Total de cobros confirmados</span><strong>{{ $summary['total'] !== null ? \App\Support\CashAmount::format($summary['total']) : 'Por conciliar' }}</strong><small>{{ $summary['count'] }} pago(s) confirmado(s)</small></div></article>
    <article class="kpi-card"><div class="kpi-copy"><span>Efectivo esperado</span><strong>{{ $summary['expected'] !== null ? \App\Support\CashAmount::format($summary['expected']) : 'Por conciliar' }}</strong><small>Fondo inicial + cobros confirmados en efectivo</small></div></article>
</div>
@if($summary['inconsistent'])<div class="alert error">Hay pagos o importes que requieren conciliación. Los pagos sin una cuenta válida de esta sede no se muestran y el efectivo esperado no se determina automáticamente.</div>@endif
<section class="panel">
    <div class="panel-head"><h2>Cobros asociados al turno</h2><span class="catalog-count">{{ $payments->total() }} registro(s)</span></div>
    <p>Se suman únicamente pagos con estado confirmado. Los demás estados se muestran sin incluirse en el total. Esta fase no registra ni modifica pagos.</p>
    <div class="table-wrap"><table class="catalog-table">
        <thead><tr><th>Pago</th><th>Fecha</th><th>Método</th><th>Importe</th><th>Estado</th><th>Referencia</th></tr></thead>
        <tbody>@forelse($payments as $payment)
        <tr><td>#{{ $payment->id_pago }}</td><td>{{ $payment->fecha_pago }}</td><td>{{ $payment->metodo_pago }}</td><td>{{ \App\Support\CashAmount::format((string) $payment->monto) }}</td><td>{{ $payment->estado }}</td><td>{{ $payment->referencia ?: '—' }}</td></tr>
        @empty<tr><td colspan="6"><div class="empty-state">No hay pagos asociados de esta sede. El total de cobros es 0.00.</div></td></tr>@endforelse</tbody>
    </table></div>
    @if($payments->hasPages())<div class="pagination">{{ $payments->links() }}</div>@endif
</section>
@elseif($canOpen)
<section class="panel form-panel">
    <h2>No tienes una caja abierta en esta sede</h2>
    <p>Se permite una caja abierta por Cajero y sede. El responsable será {{ auth()->user()->name }}.</p>
    <form method="POST" action="{{ route('cash.open') }}" id="cash-opening-form">
        @csrf
        <div class="form-grid">
            <label>Fondo inicial<input type="number" name="fondo_inicial" value="{{ old('fondo_inicial', '0.00') }}" min="0" max="9999999999.99" step="0.01" required inputmode="decimal"><small>Importe no negativo, con un máximo de dos decimales.</small></label>
            <label>Observaciones<textarea name="observaciones" maxlength="2000">{{ old('observaciones') }}</textarea></label>
        </div>
        <div class="actions"><button class="button primary" type="submit">Abrir caja</button></div>
    </form>
</section>
@elseif($canSupervise)
<section class="panel"><h2>Supervisión de caja</h2><p>Puedes consultar los turnos de esta sede. La apertura corresponde a un usuario con rol Cajero asignado a la sede activa.</p></section>
@endif

@if($sessions->isNotEmpty())
<section class="panel">
    <h2>{{ $canSupervise ? 'Turnos abiertos en esta sede' : 'Tus turnos abiertos en esta sede' }}</h2>
    @if(!$canSupervise && $sessions->count() > 1)<div class="alert error">Existen varias aperturas previas. Solicita revisión al gerente; no se crearán nuevas aperturas mientras haya una vigente.</div>@endif
    <div class="table-wrap"><table class="catalog-table"><thead><tr><th>Turno</th><th>Responsable</th><th>Apertura</th><th>Fondo</th><th>Acción</th></tr></thead><tbody>
    @foreach($sessions as $session)<tr><td>#{{ $session->id_corte_caja }}</td><td>{{ $session->cashier?->name ?: 'Usuario no disponible' }}</td><td>{{ $session->fecha_apertura?->format('d/m/Y H:i:s') }}</td><td>{{ \App\Support\CashAmount::format($session->fondo_inicial) }}</td><td><a class="button small" href="{{ route('cash.sessions.show', $session) }}">Consultar turno</a></td></tr>@endforeach
    </tbody></table></div>
</section>
@elseif($canSupervise && !$cashSession)
<section class="empty-panel"><h2>No hay turnos abiertos en esta sede.</h2></section>
@endif

@if($cashSession)
<section class="panel">
    <h2>Historial de la apertura y sus correcciones</h2>
    <div class="table-wrap"><table class="catalog-table"><thead><tr><th>Fecha ({{ config('app.timezone') }})</th><th>Acción</th><th>Usuario</th><th>Fondo anterior</th><th>Fondo posterior</th><th>Motivo</th></tr></thead><tbody>
    @forelse($auditEntries as $entry)
        @php
            $before = json_decode($entry->datos_anteriores ?? '{}', true) ?: [];
            $after = json_decode($entry->datos_nuevos ?? '{}', true) ?: [];
            $actionLabel = match($entry->accion) { 'modificar_fondo_caja' => 'Modificación de fondo', 'anular_apertura_caja' => 'Anulación de apertura', default => 'Apertura' };
        @endphp
        <tr><td>{{ $entry->fecha }}</td><td>{{ $actionLabel }}</td><td>#{{ $entry->id_usuario }}</td><td>{{ $before['fondo_inicial'] ?? '—' }}</td><td>{{ $after['fondo_inicial'] ?? '—' }}</td><td>{{ $after['motivo'] ?? '—' }}</td></tr>
    @empty<tr><td colspan="6">No hay entradas de auditoría de apertura disponibles.</td></tr>@endforelse
    </tbody></table></div>
    @if($auditEntries->hasPages())<div class="pagination">{{ $auditEntries->links() }}</div>@endif
</section>
@endif
<section class="panel">
    <h2>Historial de turnos de {{ $site->nombre }}</h2>
    <div class="table-wrap"><table class="catalog-table"><thead><tr><th>Turno</th><th>Estado</th><th>Responsable</th><th>Apertura</th><th>Fondo</th><th>Acción</th></tr></thead><tbody>
    @forelse($history as $session)<tr><td>#{{ $session->id_corte_caja }}</td><td>{{ $session->estado }}</td><td>{{ $session->cashier?->name ?: 'Usuario no disponible' }}</td><td>{{ $session->fecha_apertura?->format('d/m/Y H:i:s') }}</td><td>{{ \App\Support\CashAmount::format($session->fondo_inicial) }}</td><td><a class="button small" href="{{ route('cash.sessions.show', $session) }}">Consultar historial</a></td></tr>
    @empty<tr><td colspan="6">No hay turnos registrados para tu acceso en esta sede.</td></tr>@endforelse
    </tbody></table></div>
    @if($history->hasPages())<div class="pagination">{{ $history->links() }}</div>@endif
</section>
<section class="panel"><h2>Cierre de caja pendiente de habilitación</h2><p>El cierre financiero se habilitará cuando esté integrado el control de pagos. La anulación de una apertura equivocada sin movimientos es una operación distinta y conserva su historial.</p></section>
<script>
    const openingForm = document.getElementById('cash-opening-form');
    openingForm?.addEventListener('submit', () => {
        openingForm.querySelector('button[type="submit"]').disabled = true;
    });
</script>
@endsection
