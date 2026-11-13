@if(in_array($modulo, ['atenciones','comandas','incidencias']))
    @php
        $registros = match($modulo) { 'atenciones'=>$atenciones, 'comandas'=>$comandas, default=>$incidencias };
    @endphp
    <div class="ops-metrics" aria-label="Estados de los registros cargados">
        @foreach($registros->groupBy(fn($registro) => $registro->estado ?: 'Sin estado') as $estado => $grupoEstado)
            <x-ops.metric :label="$estado" :value="$grupoEstado->count()" note="En los registros cargados" :icon="$modulo === 'incidencias' ? 'alert' : 'activity'" data-captain-state="{{ $estado }}" />
        @endforeach
    </div>
    <div class="ops-section-title"><h2>{{ $modulo === 'incidencias' ? 'Seguimiento reciente' : 'Últimos registros' }}</h2><small>Vista rápida · hasta 4 registros</small></div>
    <div class="ops-cards captain-tracker" data-captain-module="{{ $modulo }}">
        @forelse($registros->take(4) as $registro)
            <article class="ops-record" data-captain-state="{{ $registro->estado }}">
                <span class="captain-record-icon"><x-icon :name="$modulo === 'incidencias' ? 'alert' : ($modulo === 'comandas' ? 'dish' : 'plates')" /></span>
                <div class="ops-record-head"><h3>{{ $modulo === 'atenciones' ? 'Atención #'.$registro->id_atencion : ($modulo === 'comandas' ? 'Pedido #'.$registro->id_comanda : 'Incidencia #'.$registro->id_incidencia) }}</h3><x-ops.status :value="$registro->estado" /></div>
                @if($modulo === 'atenciones')<p>{{ $registro->servicio }} · Mesa {{ $registro->mesa }}</p><p>{{ $registro->comensales ?? 0 }} comensales · {{ $registro->inicio_hora ?: 'Sin hora de inicio' }}</p>
                @elseif($modulo === 'comandas')<p>Atención #{{ $registro->id_atencion }}</p><p>Apertura: {{ $registro->fecha_apertura ?: 'Sin fecha' }}</p><p>Cierre: {{ $registro->fecha_cierre ?: 'Sin cierre registrado' }}</p>
                @else<p>{{ $registro->descripcion }}</p><p>{{ $registro->fecha }} · Responsable #{{ $registro->id_usuario ?? '—' }}</p><p>Respuesta: {{ $registro->respuesta ?: 'Sin respuesta registrada' }}</p><p>Cierre: {{ $registro->fecha_cierre ?: 'Sin cierre registrado' }}</p>@endif
            </article>
        @empty<x-ops.empty title="Sin registros para mostrar" message="Los registros de esta sede aparecerán aquí cuando exista actividad." />@endforelse
    </div>
@elseif($modulo === 'asignaciones')
    <div class="ops-metrics"><x-ops.metric label="Asignaciones activas" :value="$asignaciones->count()" icon="users" /><x-ops.metric label="Zonas con personal" :value="$asignaciones->pluck('zona')->unique()->count()" icon="map" /><x-ops.metric label="Meseros de la sede" :value="$meseros->count()" icon="group" /></div>
    <div class="ops-section-title"><h2>Equipo por zona</h2><small>Asignaciones activas de la sede</small></div>
    <div class="ops-cards captain-team">@forelse($zonas as $zona)<article class="ops-record"><h3><x-icon name="users" /> {{ $zona->nombre }}</h3>@forelse($asignaciones->where('zona',$zona->nombre) as $asignacion)<div class="captain-team-member"><span class="captain-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($asignacion->nombre,0,1).mb_substr($asignacion->apellido_p ?? '',0,1)) }}</span><p><strong>{{ trim($asignacion->nombre.' '.$asignacion->apellido_p.' '.$asignacion->apellido_m) }}</strong><br>Asignado: {{ $asignacion->fecha_inicio }}</p></div>@empty<p>Sin meseros asignados. Utiliza el formulario inferior.</p>@endforelse</article>@empty<x-ops.empty title="Sin zonas disponibles" message="Registra una zona desde Mesas y zonas antes de asignar personal." icon="map" />@endforelse</div>
@elseif($modulo === 'grupos')
    <div class="ops-metrics"><x-ops.metric label="Grupos registrados" :value="$grupos->count()" icon="group" /><x-ops.metric label="Comensales previstos" :value="$grupos->sum('comensales_pre')" icon="users" /><x-ops.metric label="Servicios asociados" :value="$grupos->pluck('servicio')->unique()->count()" icon="calendar" /></div>
    <div class="ops-cards captain-groups">@forelse($grupos as $grupo)<article class="ops-record"><span class="captain-record-icon"><x-icon name="group" /></span><span class="section-kicker">Grupo #{{ $grupo->id_grupo }}</span><h3>{{ $grupo->nombre ?: 'Sin nombre' }}</h3><p>{{ $grupo->servicio }}</p><dl><div><dt>Comensales previstos</dt><dd>{{ $grupo->comensales_pre ?? 0 }}</dd></div><div><dt>Horario</dt><dd>{{ substr($grupo->inicio_hora ?? '',0,5) }} – {{ substr($grupo->fin_hora ?? '',0,5) }}</dd></div></dl></article>@empty<x-ops.empty title="Sin grupos programados" message="Los grupos registrados para los servicios de esta sede aparecerán aquí." icon="group" />@endforelse</div>
@endif
