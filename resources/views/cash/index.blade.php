@extends('layouts.app')

@php
    if (! class_exists('CarbonCarbon')) {
        class_alias(\Carbon\Carbon::class, 'CarbonCarbon');
    }
@endphp

@section('title', 'Caja')

@push('styles')
    @vite('resources/css/cash.css')
@endpush

@section('content')
<div class="cash-page">
    <div class="cash-hero">
        <div>
            <span class="section-kicker">Operación financiera · {{ $site->nombre }}</span>
            <h1>Panel de caja</h1>
            <p>Consulta cuentas, registra cobros y controla el turno activo de la sede seleccionada.</p>
        </div>
        <div class="cash-hero-status {{ $register ? 'is-open' : 'is-closed' }}">
            <span class="cash-status-dot"></span>
            <div><small>Estado de caja</small><strong>{{ $register ? 'Turno abierto' : 'Sin turno abierto' }}</strong></div>
        </div>
    </div>

    <section class="cash-stat-grid" aria-label="Resumen de caja">
        <article class="cash-stat-card"><span class="cash-stat-icon blue"><x-icon name="layers" /></span><div><small>Cuentas pendientes</small><strong>{{ number_format($pendingCount) }}</strong><a href="{{ route('cash.accounts.index', ['estado' => 'abierta']) }}">Consultar cuentas</a></div></article>
        <article class="cash-stat-card"><span class="cash-stat-icon green"><x-icon name="check" /></span><div><small>Cuentas cobradas</small><strong>{{ number_format($paidCount) }}</strong><a href="{{ route('cash.accounts.index', ['estado' => 'pagada']) }}">Ver cobradas</a></div></article>
        <article class="cash-stat-card"><span class="cash-stat-icon purple"><x-icon name="cash" /></span><div><small>Recaudado en turno</small><strong>${{ number_format($revenue['total'] / 100, 2) }}</strong><span>Operaciones confirmadas</span></div></article>
        <article class="cash-stat-card"><span class="cash-stat-icon amber"><x-icon name="activity" /></span><div><small>Efectivo esperado</small><strong>${{ number_format($register ? ((float) $register->fondo_inicial + ($revenue['efectivo'] / 100) + (float) $register->total_otros) : 0, 2) }}</strong><span>Fondo + efectivo + movimientos</span></div></article>
    </section>

    <div class="cash-main-grid">
        <section class="cash-panel cash-register-panel">
            <div class="cash-panel-head"><div><span class="section-kicker">Turno actual</span><h2>{{ $register ? 'Caja abierta' : 'Abrir caja' }}</h2></div><x-icon name="cash" /></div>
            @if($register)
                <dl class="cash-detail-list">
                    <div><dt>Responsable</dt><dd>{{ (int) $register->id_usuario_cajero === (int) auth()->id() ? auth()->user()->name : 'Otro usuario autorizado' }}</dd></div>
                    <div><dt>Apertura</dt><dd>{{ \Carbon\Carbon::parse($register->fecha_apertura)->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Fondo inicial</dt><dd>${{ number_format((float) $register->fondo_inicial, 2) }}</dd></div>
                    <div><dt>Pagos en efectivo</dt><dd>${{ number_format($revenue['efectivo'] / 100, 2) }}</dd></div>
                </dl>
                @if(auth()->user()->canAdminister() || auth()->user()->isManager() || (int) $register->id_usuario_cajero === (int) auth()->user()->id_usuario)
                    <form method="POST" action="{{ route('cash.registers.close') }}" class="cash-form cash-close-form" data-confirm="¿Confirmas el cierre de esta caja? Esta operación no puede deshacerse.">
                        @csrf
                        <label><span>Efectivo contado</span><input type="number" name="efectivo_contado" min="0" step="0.01" required placeholder="0.00"></label>
                        <label><span>Observaciones <small>(opcional)</small></span><textarea name="observaciones" rows="2" maxlength="500" placeholder="Diferencias o notas del arqueo"></textarea></label>
                        <button class="cash-button danger" type="submit">Realizar cierre</button>
                    </form>
                @else
                    <p class="cash-muted">La caja está abierta por otro cajero. Puedes consultar sus operaciones, pero no modificar su turno.</p>
                @endif
            @else
                <p class="cash-muted">No existe un turno abierto para {{ $site->nombre }}. Registra el fondo inicial para habilitar cobros.</p>
                <form method="POST" action="{{ route('cash.registers.open') }}" class="cash-form">
                    @csrf
                    <label><span>Fondo inicial</span><input type="number" name="fondo_inicial" min="0" step="0.01" required placeholder="0.00"></label>
                    <button class="cash-button primary" type="submit">Abrir caja</button>
                </form>
            @endif
        </section>

        <section class="cash-panel">
            <div class="cash-panel-head"><div><span class="section-kicker">Accesos rápidos</span><h2>Operaciones</h2></div><x-icon name="activity" /></div>
            <div class="cash-quick-grid">
                <a class="cash-quick-link" href="{{ route('cash.accounts.index', ['estado' => 'abierta']) }}"><span class="cash-stat-icon blue"><x-icon name="cash" /></span><div><strong>Cobrar cuenta</strong><small>Revisa cuentas pendientes y cobra una operación.</small></div><x-icon name="chevron" /></a>
                <a class="cash-quick-link" href="{{ route('cash.movements') }}"><span class="cash-stat-icon purple"><x-icon name="activity" /></span><div><strong>Consultar movimientos</strong><small>Consulta cobros, referencias y turnos anteriores.</small></div><x-icon name="chevron" /></a>
                <a class="cash-quick-link" href="{{ route('cash.movements') }}#movimientos-caja"><span class="cash-stat-icon amber"><x-icon name="layers" /></span><div><strong>Entradas y salidas</strong><small>Registra movimientos autorizados de la caja.</small></div><x-icon name="chevron" /></a>
            </div>
        </section>
    </div>

    <section class="cash-panel">
        <div class="cash-panel-head"><div><span class="section-kicker">Cobros confirmados</span><h2>Últimas operaciones</h2></div><a class="cash-button secondary" href="{{ route('cash.movements') }}">Ver historial</a></div>
        <div class="cash-method-summary"><span>Efectivo <strong>${{ number_format($revenue['efectivo'] / 100, 2) }}</strong></span><span>Tarjeta <strong>${{ number_format($revenue['tarjeta'] / 100, 2) }}</strong></span><span>Transferencia <strong>${{ number_format($revenue['transferencia'] / 100, 2) }}</strong></span></div>
        <div class="cash-table-wrap"><table class="cash-table"><thead><tr><th>Folio</th><th>Cuenta</th><th>Servicio</th><th>Método</th><th>Importe</th><th>Fecha</th><th></th></tr></thead><tbody>
        @forelse($recentPayments as $payment)
            <tr><td>#{{ $payment->id_pago }}</td><td>#{{ $payment->id_cuenta }}</td><td>{{ $payment->servicio_nombre }}</td><td><span class="cash-pill {{ $payment->metodo_pago }}">{{ ucfirst($payment->metodo_pago) }}</span></td><td><strong>${{ number_format((float) $payment->monto, 2) }}</strong></td><td>{{ \Carbon\Carbon::parse($payment->fecha_pago)->format('d/m/Y H:i') }}</td><td><a class="cash-link" href="{{ route('cash.receipt', $payment->id_pago) }}">Comprobante</a></td></tr>
        @empty
            <tr><td colspan="7"><div class="cash-empty"><x-icon name="cash" /><strong>Aún no hay cobros en este turno</strong><span>Las operaciones confirmadas aparecerán aquí.</span></div></td></tr>
        @endforelse
        </tbody></table></div>
    </section>

    @if($register && $recentMovements->isNotEmpty())
        <section class="cash-panel"><div class="cash-panel-head"><div><span class="section-kicker">Auditoría del turno</span><h2>Últimos movimientos de caja</h2></div></div><div class="cash-table-wrap"><table class="cash-table"><thead><tr><th>Tipo</th><th>Concepto</th><th>Importe</th><th>Registró</th><th>Fecha</th></tr></thead><tbody>@foreach($recentMovements as $movement)<tr><td><span class="cash-pill {{ $movement->tipo === 'entrada' ? 'entrada' : 'salida' }}">{{ ucfirst($movement->tipo) }}</span></td><td>{{ $movement->concepto }}</td><td>${{ number_format((float) $movement->monto, 2) }}</td><td>{{ trim(($movement->usuario_nombre ?? '').' '.($movement->usuario_apellido ?? '')) ?: 'Sin nombre' }}</td><td>{{ CarbonCarbon::parse($movement->fecha)->format('d/m/Y H:i') }}</td></tr>@endforeach</tbody></table></div></section>
    @endif
</div>

@if(isset($cashSession) && $cashSession)
    <section class="cash-panel" aria-labelledby="opening-audit-title">
        <div class="cash-panel__header">
            <div>
                <p class="cash-eyebrow">Turno seleccionado</p>
                <h2 id="opening-audit-title">Apertura #{{ $cashSession->id_corte_caja }}</h2>
            </div>
            <div class="cash-actions">
                @if($canCorrect ?? false)
                    <a class="btn btn-primary" href="{{ route('cash.fund.edit', $cashSession) }}">Modificar fondo inicial</a>
                    <a class="btn btn-secondary" href="{{ route('cash.cancel.confirm', $cashSession) }}">Anular apertura</a>
                @elseif($correctionBlock ?? null)
                    <span class="cash-muted">{{ $correctionBlock }}</span>
                @endif
            </div>
        </div>
        <p>Fondo inicial: <strong>\${{ number_format((float) $cashSession->fondo_inicial, 2) }}</strong> ·
            Estado: <strong>{{ $cashSession->estado }}</strong></p>
        @if($cashSession->observaciones)
            <p style="grid-column:1 / -1"><strong>Observaciones de apertura</strong></p>
            <p>{{ $cashSession->observaciones }}</p>
        @endif
        @if($cashSession->estado === 'abierto')
            <p class="cash-muted">Error al capturar el fondo: si el importe fue incorrecto, puedes modificarlo mientras no existan movimientos.</p>
        @elseif($cashSession->estado === 'anulado')
            <p class="cash-muted">Apertura registrada por error. No constituye un corte financiero.</p>
        @endif
        @if(($auditEntries ?? collect())->isNotEmpty())
            <h3>Auditoría de apertura</h3>
            <ul class="cash-list">
                @foreach($auditEntries as $entry)
                    <li>{{ $entry->accion }} · {{ $entry->fecha }} · {{ $entry->descripcion ?? '' }}</li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
@if(isset($history) && $history->isNotEmpty())
    <section class="cash-panel" aria-labelledby="cash-history-title">
        <h2 id="cash-history-title">Historial de aperturas</h2>
        <ul class="cash-list">
            @foreach($history as $turno)
                <li>Turno #{{ $turno->id_corte_caja }} · {{ $turno->estado }} · <a href="{{ route('cash.sessions.show', $turno) }}">Consultar</a></li>
            @endforeach
        </ul>
    </section>
@endif

@endsection
