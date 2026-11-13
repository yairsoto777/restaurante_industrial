<div class="ops-section-title"><h2>Servicios de hoy</h2><a class="button" href="{{ route('services.index') }}">Consultar servicios</a></div>
<div class="ops-cards waiter-service-grid waiter-dashboard-services">
    @forelse($services as $waiterDayService)
        <article class="ops-record waiter-service-card" data-waiter-service-state="{{ $waiterDayService->estado }}"><x-capitan.service-photo :name="$waiterDayService->nombre" :index="$loop->index" /><div class="waiter-service-info"><div class="ops-record-head"><h3>{{ $waiterDayService->nombre }}</h3><x-ops.status :value="$waiterDayService->estado" /></div><p>{{ substr($waiterDayService->inicio_hora ?? '',0,5) }} – {{ substr($waiterDayService->fin_hora ?? '',0,5) }}</p><p>{{ (int)$waiterDayService->ocupacion }} / {{ $waiterDayService->capacidad ?? 'Sin capacidad' }} comensales registrados</p><a class="button" href="{{ route('waiter.index') }}">Ir a mi operación</a></div></article>
    @empty<x-ops.empty title="Sin servicios programados para hoy" message="Consulta Servicios para revisar la programación aprobada de tu sede." icon="calendar" />@endforelse
</div>
