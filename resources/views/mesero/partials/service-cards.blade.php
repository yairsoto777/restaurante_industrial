<div class="ops-section-title"><h2>Servicios en esta consulta</h2><small>Vista rápida de la página actual</small></div>
<div class="ops-cards waiter-service-grid">
    @forelse($services->take(4) as $service)
        <article class="ops-record waiter-service-card" data-waiter-service-state="{{ $service->estado }}"><x-capitan.service-photo :name="$service->nombre" :index="$loop->index" /><div class="waiter-service-info"><div class="ops-record-head"><h3>{{ $service->nombre }}</h3><x-ops.status :value="$service->estado" /></div><p><x-icon name="calendar" /> {{ \Illuminate\Support\Carbon::parse($service->fecha)->format('d/m/Y') }} · {{ substr($service->inicio_hora ?? '',0,5) }} – {{ substr($service->fin_hora ?? '',0,5) }}</p><dl><div><dt>Capacidad</dt><dd>{{ $service->capacidad }} comensales</dd></div><div><dt>Mis atenciones</dt><dd>{{ $service->mis_atenciones }}</dd></div></dl><a class="button" href="{{ route('waiter.index') }}">Ir a mi operación</a></div></article>
    @empty<x-ops.empty title="Sin servicios en esta consulta" message="Ajusta los filtros del listado para consultar los servicios aprobados de tu sede." icon="calendar" />@endforelse
</div>
