<div class="ops-section-title"><h2>Seguimiento reciente</h2><small>Hasta 3 resultados de la consulta actual</small></div>
<div class="ops-cards">
    @foreach($incidents->take(3) as $incident)
        <article class="ops-record"><div class="ops-record-head"><h3>Incidencia #{{ $incident->id_incidencia }}</h3><x-ops.status :value="$incident->estado" /></div><p>{{ $incident->descripcion }}</p><p>{{ trim(($incident->usuario_nombre ?? '').' '.($incident->usuario_apellido ?? '')) ?: 'Sin nombre' }} · {{ $incident->fecha ? \Carbon\Carbon::parse($incident->fecha)->format('d/m/Y H:i') : 'Sin fecha' }}</p><p><strong>Respuesta:</strong> {{ $incident->respuesta ?: 'Sin respuesta registrada' }}</p><p><strong>Cierre:</strong> {{ $incident->fecha_cierre ?: 'Sin cierre registrado' }}</p></article>
    @endforeach
</div>
