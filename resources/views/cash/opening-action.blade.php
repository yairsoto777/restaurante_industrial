@extends('layouts.app')
@section('title', $cancel ? 'Anular apertura' : 'Modificar fondo inicial')
@section('content')
<div class="page-heading"><div><span class="section-kicker">Caja · {{ $site->nombre }}</span><h1>{{ $cancel ? 'Anular apertura' : 'Modificar fondo inicial' }}</h1><p>Turno #{{ $cashSession->id_corte_caja }} · Responsable: {{ auth()->user()->name }}</p></div></div>
<section class="panel form-panel">
    <p>Fondo inicial actual: <strong>{{ \App\Support\CashAmount::format($cashSession->fondo_inicial) }}</strong>. Estado: <strong>{{ $cashSession->estado }}</strong>.</p>
    @if($cancel)<div class="alert error">Confirma que esta apertura fue registrada por error. Se conservará con estado anulado y podrás abrir otro turno. Esta operación no realiza un corte financiero.</div>@endif
    <form method="POST" action="{{ $cancel ? route('cash.cancel', $cashSession) : route('cash.fund.update', $cashSession) }}" data-cash-action>
        @csrf
        <input type="hidden" name="revision" value="{{ $revision }}">
        <div class="form-grid">
            @unless($cancel)<label>Nuevo fondo inicial<input type="number" name="fondo_inicial" value="{{ old('fondo_inicial', $cashSession->fondo_inicial) }}" min="0" max="9999999999.99" step="0.01" inputmode="decimal" required></label>@endunless
            <label class="full">Motivo obligatorio<textarea name="motivo" required minlength="5" maxlength="1000">{{ old('motivo') }}</textarea></label>
        </div>
        @if($cancel)<label class="checkbox-line"><input type="checkbox" name="confirmacion" value="1" required> Confirmo que deseo anular esta apertura equivocada, sin eliminar su registro.</label>@endif
        <div class="actions">
            <a class="button" href="{{ route('cash.sessions.show', $cashSession) }}">Cancelar sin guardar</a>
            <button class="button {{ $cancel ? 'danger' : 'primary' }}" type="submit">{{ $cancel ? 'Confirmar anulación de apertura' : 'Guardar nuevo fondo' }}</button>
        </div>
    </form>
</section>
<script>
document.querySelector('[data-cash-action]')?.addEventListener('submit', (event) => {
    event.currentTarget.querySelector('button[type="submit"]').disabled = true;
});
</script>
@endsection
