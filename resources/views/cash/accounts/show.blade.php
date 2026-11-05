@extends('layouts.app')

@section('title', 'Cuenta #'.$account->id_cuenta)

@push('styles')
    @vite('resources/css/cash.css')
@endpush

@section('content')
<div class="cash-page">
    <div class="cash-page-heading"><div><span class="section-kicker">Cuenta #{{ $account->id_cuenta }} · {{ $site->nombre }}</span><h1>Detalle de cuenta</h1><p>Mesa {{ $account->mesa_numero ?: 'sin definir' }} · {{ $account->servicio_nombre }} · {{ $account->grupo_nombre ?: 'Sin grupo' }}</p></div><a class="cash-button secondary" href="{{ route('cash.accounts.index') }}">Volver a cuentas</a></div>
    <div class="cash-detail-layout">
        <section class="cash-panel"><div class="cash-panel-head"><div><span class="section-kicker">Consumo registrado</span><h2>Conceptos de la cuenta</h2></div><span class="cash-status-pill {{ $account->estado }}">{{ $account->estado === 'abierta' ? 'Pendiente de cobro' : ucfirst($account->estado) }}</span></div>
            @if(!$calculationReady)<div class="cash-alert warning">La cuenta no puede cobrarse porque uno o más conceptos no tienen un precio unitario confiable registrado.</div>@endif
            <div class="cash-table-wrap"><table class="cash-table"><thead><tr><th>Concepto</th><th>Estado</th><th>Cantidad</th><th>Precio unitario</th><th>Subtotal</th></tr></thead><tbody>@forelse($lines as $line)<tr><td><strong>{{ $line->platillo_nombre }}</strong><small class="cash-cell-note">Comanda #{{ $line->id_comanda_detalle }}</small></td><td>{{ $line->estado ?: 'Sin estado' }}</td><td>{{ $line->cantidad }}</td><td>{{ $line->precio_unitario !== null ? '$'.number_format((float) $line->precio_unitario, 2) : 'Sin precio' }}</td><td>{{ $line->precio_unitario !== null ? '$'.number_format($line->lineTotalCents / 100, 2) : '—' }}</td></tr>@empty<tr><td colspan="5"><div class="cash-empty"><x-icon name="layers" /><strong>La cuenta no tiene conceptos</strong><span>No es posible registrar un cobro sin detalle de consumo.</span></div></td></tr>@endforelse</tbody></table></div>
            <div class="cash-total-box"><span>Subtotal calculado</span><strong>${{ number_format($subtotalCents / 100, 2) }}</strong><span>Total a cobrar</span><strong>${{ number_format($totalCents / 100, 2) }}</strong></div>
        </section>
        <aside class="cash-panel cash-payment-panel"><div class="cash-panel-head"><div><span class="section-kicker">Registrar cobro</span><h2>Forma de pago</h2></div><x-icon name="cash" /></div>
            @if($account->estado === 'abierta' && $calculationReady && $totalCents > 0)
                @if(!$register)<div class="cash-alert warning">No hay una caja abierta para esta sede. Abre un turno antes de cobrar.</div>@endif
                <form method="POST" action="{{ route('cash.payments.store', $account->id_cuenta) }}" class="cash-form" data-cash-payment data-total="{{ $totalCents / 100 }}">
                    @csrf
                    <label><span>Método de pago</span><select name="metodo_pago" id="cash-method" required><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option></select></label>
                    <label><span>Importe recibido</span><input type="number" name="monto_recibido" id="cash-received" min="0" step="0.01" required value="{{ old('monto_recibido', number_format($totalCents / 100, 2, '.', '')) }}"></label>
                    <div class="cash-change" id="cash-change">Cambio: $0.00</div>
                    <label id="cash-reference-field"><span>Referencia <small>(obligatoria para transferencia)</small></span><input type="text" name="referencia" maxlength="100" value="{{ old('referencia') }}" placeholder="Folio o referencia externa"></label>
                    <button class="cash-button primary full" type="submit" @disabled(!$register)>Confirmar cobro</button>
                    <small class="cash-muted">Los pagos con tarjeta y transferencia son registros administrativos; no se procesan datos bancarios.</small>
                </form>
            @elseif($account->estado !== 'abierta')
                <div class="cash-alert success">Esta cuenta ya fue procesada. Puedes consultar o imprimir el comprobante de sus pagos.</div>
            @endif
        </aside>
    </div>
    @if($payments->isNotEmpty())<section class="cash-panel"><div class="cash-panel-head"><div><span class="section-kicker">Historial</span><h2>Pagos de esta cuenta</h2></div></div><div class="cash-table-wrap"><table class="cash-table"><thead><tr><th>Folio</th><th>Método</th><th>Importe</th><th>Fecha</th><th></th></tr></thead><tbody>@foreach($payments as $payment)<tr><td>#{{ $payment->id_pago }}</td><td><span class="cash-pill {{ $payment->metodo_pago }}">{{ ucfirst($payment->metodo_pago) }}</span></td><td>${{ number_format((float) $payment->monto, 2) }}</td><td>{{ \Carbon\Carbon::parse($payment->fecha_pago)->format('d/m/Y H:i') }}</td><td><a class="cash-link" href="{{ route('cash.receipt', $payment->id_pago) }}">Ver comprobante</a></td></tr>@endforeach</tbody></table></div></section>@endif
</div>
@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-cash-payment]');
    if (!form) return;
    const method = document.getElementById('cash-method');
    const received = document.getElementById('cash-received');
    const reference = document.getElementById('cash-reference-field');
    const change = document.getElementById('cash-change');
    const total = Number(form.dataset.total || 0);
    const update = () => {
        const isCash = method.value === 'efectivo';
        reference.hidden = isCash;
        reference.querySelector('input').required = method.value === 'transferencia';
        const amount = Number(received.value || 0);
        change.textContent = `Cambio: $${(isCash ? Math.max(0, amount - total) : 0).toFixed(2)}`;
    };
    method.addEventListener('change', update);
    received.addEventListener('input', update);
    update();
})();
</script>
@endpush
@endsection
