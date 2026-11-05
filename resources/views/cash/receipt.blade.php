@extends('layouts.app')

@section('title', 'Comprobante de cobro #'.$payment->id_pago)

@push('styles')
    @vite('resources/css/cash.css')
@endpush

@section('content')
<div class="cash-page">
    <div class="cash-page-heading no-print"><div><span class="section-kicker">Caja · Operación #{{ $payment->id_pago }}</span><h1>Comprobante administrativo</h1><p>Este documento acredita el registro interno del cobro; no es factura fiscal ni CFDI.</p></div><div class="cash-heading-actions"><button class="cash-button primary" type="button" onclick="window.print()">Imprimir</button><a class="cash-button secondary" href="{{ route('cash.accounts.show', $payment->id_cuenta) }}">Volver a cuenta</a></div></div>
    <article class="cash-receipt">
        <header class="cash-receipt-head"><div><strong>NUTRILIADO</strong><span>Comprobante administrativo de cobro</span></div><div><small>Folio de operación</small><strong>#{{ $payment->id_pago }}</strong></div></header>
        <dl class="cash-receipt-meta"><div><dt>Sede</dt><dd>{{ $site->nombre }}</dd></div><div><dt>Cuenta</dt><dd>#{{ $payment->id_cuenta }}</dd></div><div><dt>Atención</dt><dd>#{{ $payment->id_atencion }}</dd></div><div><dt>Servicio</dt><dd>{{ $payment->servicio_nombre }}</dd></div><div><dt>Fecha y hora</dt><dd>{{ \Carbon\Carbon::parse($payment->fecha_pago)->format('d/m/Y H:i') }}</dd></div><div><dt>Método</dt><dd>{{ ucfirst($payment->metodo_pago) }}</dd></div></dl>
        <div class="cash-table-wrap"><table class="cash-table"><thead><tr><th>Concepto</th><th>Cantidad</th><th>Precio unitario</th><th>Importe</th></tr></thead><tbody>@foreach($lines as $line)<tr><td>{{ $line->platillo_nombre }}</td><td>{{ $line->cantidad }}</td><td>${{ number_format((float) $line->precio_unitario, 2) }}</td><td>${{ number_format($line->lineTotalCents / 100, 2) }}</td></tr>@endforeach</tbody></table></div>
        <div class="cash-receipt-total"><span>Total pagado</span><strong>${{ number_format((float) $payment->monto, 2) }}</strong></div>
        @if($payment->referencia)<p class="cash-receipt-reference">Referencia externa: {{ $payment->referencia }}</p>@endif
        <footer>Registró: {{ trim(($payment->cajero_nombre ?? '').' '.($payment->cajero_apellido ?? '')) ?: 'Usuario no disponible' }} · Documento interno sin valor fiscal.</footer>
    </article>
</div>
@endsection
