@extends('layouts.app')
@section('title', 'Caja')
@section('content')
<div class="chef-page"><section class="chef-hero"><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Caja</span><h1>Cobro de mesas</h1><p>Consulta las comandas abiertas y registra el pago de cada atención.</p></div></section>
<section class="chef-panel"><div class="chef-panel-heading"><h2>Cuentas pendientes</h2><span class="chef-count">{{ $accounts->count() }}</span></div>
<div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Mesa</th><th>Servicio</th><th>Consumo</th><th>Total</th><th>Cobrar</th></tr></thead><tbody>
@forelse($accounts as $account)<tr><td>Mesa {{ $account->mesa }}</td><td>{{ $account->servicio }}</td><td>{{ $account->items }}</td><td><strong>${{ number_format((float) $account->total, 2) }}</strong></td><td><form method="POST" action="{{ route('cash.charge', $account->id_atencion) }}">@csrf<select name="metodo" required><option value="">Método</option><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option></select><button class="chef-button chef-button-primary">Registrar cobro</button></form></td></tr>@empty<tr><td colspan="5">No hay cuentas pendientes.</td></tr>@endforelse
</tbody></table></div></section></div>
@endsection
