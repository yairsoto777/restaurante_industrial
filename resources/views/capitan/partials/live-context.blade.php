@if($servicioEnVivo)
    <article class="ops-record ops-live-context"><div class="ops-record-head"><h3>{{ $servicioEnVivo->nombre }}</h3><x-ops.status :value="$servicioEnVivo->estado" /></div><p>{{ $fechaOperacion }} · {{ substr($servicioEnVivo->inicio_hora ?? '',0,5) }} – {{ substr($servicioEnVivo->fin_hora ?? '',0,5) }}</p><p>Capacidad: {{ $servicioEnVivo->capacidad ?? 'Sin datos' }} comensales · {{ $mesasServicio->sum('comensales') }} comensales en las atenciones mostradas</p></article>
@endif
<div class="ops-quick-actions"><strong>Seguimiento de la sede</strong><a class="button" href="{{ route('capitan.index',['modulo'=>'comandas']) }}">Consultar comidas</a><a class="button" href="{{ route('capitan.index',['modulo'=>'incidencias']) }}">Consultar incidencias</a></div>
