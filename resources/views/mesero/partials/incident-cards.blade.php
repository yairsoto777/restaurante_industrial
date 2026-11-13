<div class="ops-section-title"><h2>Seguimiento reciente</h2><a class="button primary" href="#registrar-incidencia">Registrar incidencia</a></div>
<div class="ops-cards waiter-incident-cards">
    @forelse($incidents->take(3) as $waiterIncidentCard)
        <article class="ops-record" data-waiter-incident-state="{{ $waiterIncidentCard->estado }}">
            <div class="ops-record-head"><h3>Incidencia #{{ $waiterIncidentCard->id_incidencia }}</h3><x-ops.status :value="$waiterIncidentCard->estado ?: 'Sin estado definido'" /></div>
            <p class="waiter-incident-description">{{ $waiterIncidentCard->descripcion }}</p>
            <p>{{ trim(($waiterIncidentCard->usuario_nombre ?? '').' '.($waiterIncidentCard->usuario_apellido ?? '')) ?: 'Sin nombre' }}<br>{{ $waiterIncidentCard->fecha ? \Carbon\Carbon::parse($waiterIncidentCard->fecha)->format('d/m/Y H:i') : 'Sin fecha' }}</p>
            <div class="waiter-incident-response"><small>Respuesta registrada</small><p>{{ $waiterIncidentCard->respuesta ?: 'Sin respuesta registrada' }}</p></div>
            <p><strong>Cierre:</strong> {{ $waiterIncidentCard->fecha_cierre ?: 'Sin cierre registrado' }}</p>
        </article>
    @empty<x-ops.empty title="Sin incidencias en esta consulta" message="Utiliza el formulario para registrar una incidencia o ajusta los filtros del historial." icon="alert" />@endforelse
</div>
