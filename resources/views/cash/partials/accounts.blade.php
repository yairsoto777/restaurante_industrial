<div class="ops-section-title"><h2>Por cobrar</h2><small>Hasta 6 cuentas · listado completo debajo</small></div>
<div class="ops-cards">
    @forelse($accounts->take(6) as $account)
        <article class="ops-record">
            <div class="ops-record-head"><h3>Mesa {{ $account->mesa }}</h3><x-ops.status value="Pendiente de cobro" /></div><p>Atención #{{ $account->id_atencion }} · {{ $account->servicio }}</p>
            @if($account->id_servicio_ofrecido)<p><strong>{{ $account->ofrecido_descripcion }}</strong><br>{{ $account->ofrecido_comensales }} comensales × ${{ number_format((float)$account->ofrecido_precio_unitario,2) }}</p>@else<p>{{ $account->items }}</p>@endif
            <strong class="ops-money">${{ number_format((float)$account->total,2) }}</strong>
            @if($shift)<button class="button primary" type="button" data-open-payment data-attention="{{ $account->id_atencion }}" data-total="{{ number_format((float)$account->total,2,'.','') }}" data-label="{{ $account->id_servicio_ofrecido ? 'Servicio ofrecido: '.$account->ofrecido_descripcion : $account->items }} · Mesa {{ $account->mesa }}">Cobrar servicio</button>@else<p>Abre la caja para cobrar esta cuenta.</p>@endif
        </article>
    @empty<x-ops.empty title="No hay cuentas pendientes" message="Las cuentas enviadas a Caja para esta sede aparecerán aquí." icon="cash" />@endforelse
</div>
