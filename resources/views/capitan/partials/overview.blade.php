<section class="ops-quick-actions captain-dashboard-actions" aria-label="Acciones del capitán">
    <strong>Buen día, {{ auth()->user()->nombre }} · {{ $sede->nombre }}</strong>
    <a class="button primary" href="{{ route('capitan.index', ['modulo'=>'servicios']) }}"><x-icon name="activity" /> Supervisar servicio</a>
    <a class="button" href="{{ route('capitan.index', ['modulo'=>'asignaciones']) }}"><x-icon name="users" /> Asignar meseros</a>
    <a class="button" href="{{ route('capitan.mesas.index') }}"><x-icon name="map" /> Mesas y zonas</a>
</section>
<section class="ops-metrics captain-photo-metrics" aria-label="Resumen operativo">
    <x-ops.metric label="Servicios de la fecha" :value="$serviciosHoy" icon="calendar" />
    <x-ops.metric label="Mesas registradas" :value="$mesasTotales" icon="map" />
    <x-ops.metric label="Meseros de la sede" :value="$meserosTotales" icon="users" />
    <x-ops.metric label="Comensales de la fecha" :value="$totalComensalesHoy" icon="group" />
    <x-ops.metric label="Atenciones abiertas" :value="$atencionesAbiertas" icon="plates" tone="warning" />
    <x-ops.metric label="Incidencias sin cierre" :value="$incidenciasAbiertas" icon="alert" tone="warning" />
</section>
<div class="ops-section-title"><h2>Programación del comedor</h2><small>{{ $fechaOperacion }}</small></div>
<div class="ops-cards captain-programming">
    @forelse($servicios as $servicio)
        <article class="ops-record captain-service-card"><x-capitan.service-photo :name="$servicio->nombre" :index="$loop->index" /><div class="captain-service-info"><div class="ops-record-head"><h3>{{ $servicio->nombre }}</h3><x-ops.status :value="$servicio->estado" /></div><span class="captain-service-date">{{ $fechaOperacion }}</span><p><x-icon name="clock" /> {{ substr($servicio->inicio_hora ?? '',0,5) }} – {{ substr($servicio->fin_hora ?? '',0,5) }} · {{ $servicio->capacidad ?? 'Sin capacidad registrada' }} comensales</p></div></article>
    @empty
        <x-ops.empty title="Sin servicios programados" message="Consulta Servicio en vivo para seleccionar otra fecha y revisar la operación." icon="calendar" />
    @endforelse
</div>
<section class="panel"><div class="panel-head"><div><h2>Ocupación por zona</h2><p>Mesas con atención abierta para {{ $fechaOperacion }}.</p></div></div>
<div class="ops-cards">@forelse($resumenZonasServicio as $zona)
    @php($percent = $zona->total > 0 ? round($zona->ocupadas / $zona->total * 100) : 0)
    <article class="ops-record"><div class="ops-record-head"><h3>{{ $zona->zona }}</h3><strong>{{ $percent }}%</strong></div><div class="ops-bar" role="meter" aria-label="Ocupación {{ $zona->zona }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}"><span style="width:{{ $percent }}%"></span></div><p>{{ $zona->ocupadas }} ocupadas · {{ $zona->disponibles }} disponibles · {{ $zona->por_cobrar }} por cobrar / {{ $zona->total }} mesas</p></article>
@empty<x-ops.empty title="Sin zonas registradas" message="Organiza el comedor desde Mesas y zonas." icon="map" />@endforelse</div></section>
