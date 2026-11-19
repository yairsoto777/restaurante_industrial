@push('styles')
<link rel="stylesheet" href="{{ asset('css/captain-assignments.css') }}?v={{ filemtime(public_path('css/captain-assignments.css')) }}">
@endpush
@php
    $assignmentEditable=$jornadaSupported && $jornada && in_array($jornada->estado,['Programado','En curso'],true);
    $firstZone=$zonas->first();
    $firstAssignment=$firstZone?->staff->first();
    $availableWaiters=$meseros->whereNotIn('id_usuario',$assigned)->values();
    $assignmentTotal=$mesasServicio->unique('id_mesa')->count();
    $assignmentCovered=$mesasServicio->whereIn('id_zona',$asignaciones->pluck('id_zona'))->unique('id_mesa')->count();
    $assignmentCoverage=$assignmentTotal?round($assignmentCovered*100/$assignmentTotal):0;
    $waiterName=fn($person)=>trim(implode(' ',array_filter([$person->nombre,$person->apellido_p ?? '',$person->apellido_m ?? ''])));
@endphp
<div class="av-workspace" data-av-editable="{{ $assignmentEditable?'1':'0' }}" data-av-feedback-key="captain-assignment-{{ $sede->id_sede }}-{{ $jornada?->id_servicio }}">
    <header class="av-hero"><div><span class="av-eyebrow">NUTRILIADO · COORDINACIÓN DEL SERVICIO</span><h1>Asignación de meseros</h1><p>Distribuye y gestiona a los meseros por zonas y mesas del servicio seleccionado.</p><span class="av-site"><x-icon name="building"/>{{ $sede->nombre }}</span></div><div class="av-hero-icon" aria-hidden="true"><x-icon name="users"/></div></header>
    <form class="cw-filters av-service-bar" method="GET"><input type="hidden" name="modulo" value="asignaciones">
        <label><span><x-icon name="utensils"/>Servicio</span><select name="id_servicio"><option value="">Selección automática</option>@foreach($servicios as $service)<option value="{{ $service->id_servicio }}" @selected($jornada?->id_servicio===$service->id_servicio)>{{ $service->nombre }}</option>@endforeach</select></label>
        <label><span><x-icon name="calendar"/>Fecha / jornada</span><input type="date" name="fecha" value="{{ $fechaOperacion }}" required></label><button class="cw-btn cw-outline">Consultar</button>
        <div class="av-service-fact"><span><x-icon name="activity"/>Estado del servicio</span><strong class="cw-badge {{ $CW::tone($jornada->estado ?? null) }}">{{ $jornada->estado ?? 'Sin jornada' }}</strong></div><div class="av-service-fact"><span><x-icon name="clock"/>Horario</span><strong>{{ $jornada ? substr($jornada->inicio_hora,0,5).' – '.substr($jornada->fin_hora,0,5) : 'Sin horario' }}</strong></div>
    </form>
    @if(!$jornadaSupported)<p class="cw-notice">Asignaciones generales en consulta. La asignación por jornada requiere el esquema correspondiente.</p>@elseif(!$assignmentEditable)<p class="av-readonly"><x-icon name="clock"/>Selecciona una jornada programada o en curso para gestionar las asignaciones.</p>@endif
    <div class="av-layout">
        <section class="av-distribution" aria-labelledby="av-distribution-title">
            <div class="av-section-heading"><div><span class="av-eyebrow">ORGANIZACIÓN DEL EQUIPO</span><h2 id="av-distribution-title">Distribución por zonas <span>{{ $zonas->count() }}</span></h2></div><span class="av-coverage-total">{{ $assignmentCoverage }}% cobertura</span></div>
            <div class="av-zone-filters"><label><span class="cw-sr">Filtrar zona</span><select data-av-zone-filter><option value="">Todas las zonas</option>@foreach($zonas as $zone)<option value="{{ $zone->id_zona }}">{{ $zone->nombre }}</option>@endforeach</select></label><label class="av-search"><x-icon name="search"/><input type="search" data-av-search placeholder="Buscar zona, mesa o mesero…" aria-label="Buscar zona, mesa o mesero"></label></div>
            <div class="av-zones">
            @forelse($zonas as $zone)
                @php
                    $zoneCount=$zone->tables->unique('id_mesa')->count();
                    $zoneCovered=$zone->tables->filter(fn($table)=>$table->assigned->isNotEmpty())->unique('id_mesa')->count();
                    $zoneTone=$zoneCount===0?'gray':($zoneCovered===0?'orange':($zoneCovered<$zoneCount?'amber':'green'));
                    $zoneCoverageText=$zoneCount===0?'Sin mesas registradas':($zoneCovered===0?'Sin asignar':($zoneCovered<$zoneCount?'Cobertura parcial':'Cubierta'));
                @endphp
                <article id="av-zone-{{ $zone->id_zona }}" class="av-zone-card {{ $loop->first?'is-selected':'' }}" data-av-zone-card="{{ $zone->id_zona }}" style="--av-zone:{{ $zone->color }}" role="group" aria-label="Asignación de {{ $zone->nombre }}">
                    <div class="av-zone-photo"><img src="{{ \App\Support\ChefWorkspace::image($zone->imagen_url ?? null,'img/ui/captain-dining.webp') }}" alt="{{ ($zone->imagen_url ?? null)?'Fotografía de '.$zone->nombre:'Imagen decorativa del comedor' }}" loading="lazy"><span><i></i>{{ $zone->nombre }}</span></div>
                    <div class="av-zone-content"><div class="av-zone-heading"><h3><button type="button" class="av-zone-choice" data-av-zone-choice aria-pressed="{{ $loop->first?'true':'false' }}">{{ $zone->nombre }}</button></h3><span class="av-selected-label">Seleccionada</span></div><p class="av-zone-tables" title="{{ $zone->tables->pluck('numero')->implode(', ') }}"><x-icon name="layers"/>{{ $zoneCount?'Mesas '.$zone->tables->pluck('numero')->take(8)->implode(', ').($zoneCount>8?'…':''):'Sin mesas asociadas' }}</p><div class="av-zone-facts"><span><strong>{{ $zoneCount }}</strong> mesas</span><span><x-icon name="users"/><strong>{{ $zone->tables->sum('capacidad') }}</strong> comensales</span></div>
                        <div class="av-zone-staff">@forelse($zone->staff->unique('id_usuario_mesero') as $person)<button type="button" class="av-person" data-av-assignment="{{ $person->id_zona_mesero }}" data-av-assignment-zone="{{ $zone->id_zona }}" title="Gestionar asignación de {{ $waiterName($person) }}"><x-capitan.avatar :person="$person"/><span><strong>{{ $waiterName($person) }}</strong><small>{{ $zoneCount }} mesas a cargo · {{ $person->fecha_inicio?'Desde '.\Carbon\Carbon::parse($person->fecha_inicio)->locale('es')->translatedFormat('d M, H:i'):'Sin fecha registrada' }}</small></span><x-icon name="chevron"/></button>@empty<div class="av-no-waiter"><span><x-icon name="users"/></span><div><strong>Sin mesero asignado</strong><small>Selecciona esta zona para organizar la atención.</small></div></div>@endforelse</div>
                    </div>
                    <div class="av-zone-coverage av-{{ $zoneTone }}"><span><x-icon :name="$zoneCovered>0?'check':'users'"/></span><strong>{{ $zoneCoverageText }}</strong><small>{{ $zoneCovered }} de {{ $zoneCount }} mesas cubiertas</small></div>
                </article>
                <template data-av-zone-options="{{ $zone->id_zona }}">@foreach($zone->staff as $person)<option value="{{ $person->id_zona_mesero }}" data-retire-url="{{ route('capitan.asignaciones.retirar',$person->id_zona_mesero) }}" data-person-name="{{ $waiterName($person) }}">{{ $waiterName($person) }}</option>@endforeach</template>
                <template data-av-zone-profiles="{{ $zone->id_zona }}">@foreach($zone->staff as $person)<div data-av-profile-id="{{ $person->id_zona_mesero }}"><x-capitan.avatar :person="$person"/><div><strong>{{ $waiterName($person) }}</strong><small>{{ $zone->nombre }} · {{ $zoneCount }} mesas</small><small>{{ $person->fecha_inicio?'Desde '.\Carbon\Carbon::parse($person->fecha_inicio)->locale('es')->translatedFormat('d M Y, H:i'):'Sin fecha registrada' }}</small></div></div>@endforeach</template>
            @empty<div class="av-empty"><x-icon name="map"/><h3>No hay zonas configuradas</h3><p>Registra las áreas del comedor para distribuir a tu equipo.</p><a href="{{ $link('zonas') }}" class="cw-btn">Gestionar zonas</a></div>@endforelse
            </div>
            <p class="av-empty" data-av-empty-filter hidden>No se encontraron zonas con esos filtros.</p>
            <section class="av-metrics" aria-label="Cobertura real de la jornada">@foreach([['Mesas totales',$assignmentTotal,'layers','blue'],['Meseros asignados',$assigned->count(),'users','mint'],['Mesas cubiertas',$assignmentCovered,'check','green'],['Cobertura del servicio',$assignmentCoverage.'%','chart','purple']] as [$label,$number,$icon,$tone])<article class="av-metric av-{{ $tone }}"><span><x-icon :name="$icon"/></span><div><strong>{{ $number }}</strong><small>{{ $label }}</small></div></article>@endforeach</section>
        </section>
        <aside class="av-management" aria-labelledby="av-management-title">
            <header><span><x-icon name="users"/></span><div><h2 id="av-management-title">Gestionar asignación</h2><p>Personal de la jornada</p></div></header>
            <div class="av-panel-body">
                <div class="av-feedback" data-av-feedback role="status" tabindex="-1" hidden></div><button type="button" class="cw-btn cw-outline av-refresh" data-av-refresh hidden>Actualizar asignaciones</button>
                <form method="POST" action="{{ route('capitan.asignaciones.store') }}" data-av-assign-form>
                    @csrf<input type="hidden" name="id_servicio" value="{{ $jornada?->id_servicio }}"><input type="hidden" name="fecha" value="{{ $fechaOperacion }}">
                    <label>Zona seleccionada<select name="id_zona" data-av-selected-zone required @disabled($zonas->isEmpty())>@foreach($zonas as $zone)<option value="{{ $zone->id_zona }}">{{ $zone->nombre }}</option>@endforeach</select></label>
                    <div class="av-current-section"><label>Asignación actual<select data-av-current><option value="">Sin asignación</option>@foreach($firstZone?->staff ?? collect() as $person)<option value="{{ $person->id_zona_mesero }}" data-retire-url="{{ route('capitan.asignaciones.retirar',$person->id_zona_mesero) }}" data-person-name="{{ $waiterName($person) }}">{{ $waiterName($person) }}</option>@endforeach</select></label><div class="av-current-profile" data-av-current-profile><p>Selecciona una asignación para consultar sus datos.</p></div></div>
                    <label>Mesero disponible<select name="id_usuario_mesero" required data-av-waiter @disabled(!$assignmentEditable || $availableWaiters->isEmpty())><option value="">Selecciona un mesero</option>@foreach($availableWaiters as $person)<option value="{{ $person->id_usuario }}">{{ $waiterName($person) }}</option>@endforeach</select></label>
                    <div class="av-new-profile" data-av-new-profile><span><x-icon name="users"/></span><div><strong>Selecciona un mesero disponible</strong><small>{{ $availableWaiters->count() }} sin asignar en esta jornada</small></div></div>
                    <div class="av-buttons"><button class="cw-btn av-assign" data-av-assign @disabled(!$assignmentEditable || $zonas->isEmpty() || $availableWaiters->isEmpty())><x-icon name="plus"/>Asignar mesero</button><button type="button" class="cw-btn av-change" data-av-change disabled><x-icon name="layers"/>Cambiar asignación</button><button type="button" class="cw-btn av-remove" data-av-remove disabled><x-icon name="users"/>Retirar mesero</button></div>
                </form>
                <form method="POST" action="{{ $firstAssignment?route('capitan.asignaciones.retirar',$firstAssignment->id_zona_mesero):'' }}" data-av-retire-form hidden>@csrf @method('PATCH')<input type="hidden" name="id_servicio" value="{{ $jornada?->id_servicio }}"><input type="hidden" name="fecha" value="{{ $fechaOperacion }}"></form>
                @foreach($availableWaiters as $person)<template data-av-waiter-profile="{{ $person->id_usuario }}"><x-capitan.avatar :person="$person"/><div><strong>{{ $waiterName($person) }}</strong><small>Sin asignación en la jornada seleccionada</small></div></template>@endforeach
                <div class="av-info"><span aria-hidden="true">i</span><p>Los meseros son registrados por el Gerente de sede desde Gestión de usuarios. En este apartado únicamente puedes asignar, cambiar o retirar meseros de las zonas correspondientes al servicio seleccionado.</p></div>
            </div>
        </aside>
    </div>
</div>
<script src="{{ asset('js/captain-assignments.js') }}?v={{ filemtime(public_path('js/captain-assignments.js')) }}" defer></script>
