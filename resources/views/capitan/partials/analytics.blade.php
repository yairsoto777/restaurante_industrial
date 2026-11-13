<div class="ops-section-title"><h2>Distribución del comedor</h2><small>Inventario de mesas por zona</small></div>
<div class="ops-cards">@forelse($mesas->groupBy('zona') as $nombreZona => $mesasZona)
    @php($percent = $mesas->count() ? round($mesasZona->count() / $mesas->count() * 100) : 0)
    <article class="ops-record"><div class="ops-record-head"><h3>{{ $nombreZona }}</h3><strong>{{ $percent }}%</strong></div><div class="ops-bar" role="meter" aria-label="Proporción de mesas de {{ $nombreZona }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}"><span style="width:{{ $percent }}%"></span></div><p>{{ $mesasZona->count() }} mesas · {{ $mesasZona->sum('capacidad') }} lugares</p></article>
@empty<x-ops.empty title="Sin mesas registradas" message="La distribución se mostrará cuando existan mesas en esta sede." icon="map" />@endforelse</div>
<div class="ops-section-title"><h2>Servicios de la fecha</h2><small>{{ $fechaOperacion }}</small></div>
<div class="ops-cards">@forelse($servicios as $servicio)<article class="ops-record"><h3>{{ $servicio->nombre }}</h3><x-ops.status :value="$servicio->estado" /><p>{{ $servicio->inicio_hora }} – {{ $servicio->fin_hora }} · Capacidad {{ $servicio->capacidad }}</p></article>@empty<x-ops.empty title="Sin servicios en la fecha" message="No hay programación registrada para la fecha consultada." icon="calendar" />@endforelse</div>
<div class="ops-section-title"><h2>Comensales por hora</h2><small>{{ $totalComensalesHoy }} registrados en la fecha</small></div>
<div class="ops-cards">@forelse($datosGrafica as $punto)
    <article class="ops-record"><div class="ops-record-head"><h3>{{ $punto['hora'] }}</h3><strong>{{ $punto['total'] }}</strong></div><div class="ops-bar" role="meter" aria-label="Comensales a las {{ $punto['hora'] }}" aria-valuemin="0" aria-valuemax="{{ $maxGrafica }}" aria-valuenow="{{ $punto['total'] }}"><span style="width:{{ $punto['total'] / $maxGrafica * 100 }}%"></span></div></article>
@empty<x-ops.empty title="Sin comensales en la fecha" message="La gráfica utilizará las atenciones registradas para los servicios de esta fecha." icon="chart" />@endforelse</div>
<p class="ops-note">Atenciones, comidas e incidencias conservan el alcance de sus consultas actuales: hasta 100 registros por módulo.</p>
