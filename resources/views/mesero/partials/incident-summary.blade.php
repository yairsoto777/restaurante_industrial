<div class="ops-section-title"><h2>Estados de esta consulta</h2><small>Conteos de la página actual · los totales de sede se conservan arriba</small></div>
<section class="ops-metrics waiter-state-metrics" aria-label="Incidencias por estado en esta página">
    @foreach($incidents->groupBy(fn($waiterIncident) => $waiterIncident->estado ?: 'Sin estado definido') as $waiterIncidentState => $waiterIncidentsInState)
        <x-ops.metric :label="$waiterIncidentState" :value="$waiterIncidentsInState->count()" icon="alert" note="En la página actual" data-waiter-state="{{ $waiterIncidentState }}" />
    @endforeach
</section>
