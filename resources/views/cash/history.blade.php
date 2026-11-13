@extends('layouts.app')
@section('title', 'Historial de caja')
@section('content')
<div class="cashier-page cash-history-page">
    <div class="page-heading">
        <div><span class="section-kicker">{{ $site->nombre }}</span><h1>Historial de turnos</h1><p>Aperturas, ingresos en efectivo y arqueos de esta sede.</p></div>
        <a class="button primary" href="{{ route('cash.index') }}">Volver a Caja</a>
    </div>

    @if(auth()->user()->hasRole('Cajero'))
        <section class="ops-metrics cash-history-summary" aria-label="Resumen del historial cargado"><x-ops.metric label="Turnos registrados" :value="$shifts->count()" icon="cash" /><x-ops.metric label="Turnos abiertos" :value="$shifts->where('estado','abierto')->count()" icon="clock" tone="warning" /><x-ops.metric label="Turnos con diferencia" :value="$shifts->filter(fn($record) => $record->diferencia !== null && (float)$record->diferencia !== 0.0)->count()" icon="alert" tone="danger" /></section>
        <x-ops.filter target="ops-shifts-table" label="Buscar turno, fecha o cajero" />
    @endif
    <section class="panel cash-history-ledger">
        <div class="panel-head"><div><span class="section-kicker">Historial de caja</span><h2>Conciliación por turno</h2><p class="panel-subtitle">El saldo esperado se calcula con el fondo inicial, el efectivo recibido y el cambio entregado.</p></div><span class="badge">{{ $shifts->count() }} turnos</span></div>
        <div class="table-wrap">
            <table id="ops-shifts-table">
                <thead><tr><th>Turno / apertura</th><th>Cajero de apertura</th><th>Fondo inicial</th><th>Efectivo ingresado</th><th>Cambio entregado</th><th>Saldo esperado</th><th>Efectivo contado</th><th>Diferencia</th></tr></thead>
                <tbody>
                    @forelse($shifts as $shift)
                        @php($difference = $shift->diferencia === null ? null : (float) $shift->diferencia)
                        <tr>
                            <td><strong class="cash-history-id">#{{ $shift->id_turno_caja }}</strong><small><span class="cash-history-date-label">Apertura · </span>{{ $shift->abierta_at }}</small><small><span class="cash-history-date-label">{{ $shift->estado === 'abierto' ? 'Estado · ' : 'Cierre · ' }}</span>{{ $shift->estado === 'abierto' ? 'Abierto' : ($shift->cerrada_at ?: 'Cierre sin fecha') }}</small></td>
                            <td>{{ $shift->usuario_apertura ?: 'Usuario no disponible' }}</td>
                            <td>${{ number_format((float) $shift->fondo_inicial, 2) }}</td>
                            <td>${{ number_format((float) $shift->efectivo_ingresado, 2) }}</td>
                            <td>${{ number_format((float) $shift->cambio_entregado, 2) }}</td>
                            <td><strong>${{ number_format((float) $shift->saldo_esperado, 2) }}</strong></td>
                            <td>{{ $shift->efectivo_contado === null ? '—' : '$'.number_format((float) $shift->efectivo_contado, 2) }}</td>
                            <td>
                                @if($difference === null)
                                    <span class="cash-difference pending">Pendiente de cierre</span>
                                @elseif($difference < 0)
                                    <span class="cash-difference loss">Faltante −${{ number_format(abs($difference), 2) }}</span>
                                @elseif($difference > 0)
                                    <span class="cash-difference gain">A favor +${{ number_format($difference, 2) }}</span>
                                @else
                                    <span class="cash-difference balanced">Cuadrado $0.00</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">Todavía no hay turnos registrados en esta sede.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
<style>
    .cash-difference{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:11px;font-weight:800;white-space:nowrap}
    .cash-difference.gain{color:#14743e;background:#e8f7ee}
    .cash-difference.loss{color:#ad302b;background:#fff0ee}
    .cash-difference.balanced{color:#526174;background:#edf1f6}
    .cash-difference.pending{color:#8a6416;background:#fff7df}
    @media(max-width:850px){.cashier-page .table-wrap{overflow-x:auto}.cashier-page table{min-width:1050px}}
</style>
@endsection
