@push('styles')
<link rel="stylesheet" href="{{ asset('css/captain-live.css') }}?v={{ filemtime(public_path('css/captain-live.css')) }}">
@endpush
@php
    $liveTables=$mesasServicio->unique('id_mesa');
    $liveCovered=$liveTables->whereIn('id_zona',$asignaciones->pluck('id_zona'))->count();
    $liveCoverage=$liveTables->count()?round($liveCovered*100/$liveTables->count()):0;
    $liveTone=fn($state)=>match(mb_strtolower(trim((string)$state))){'disponible'=>'green','ocupada'=>'amber','reservada','apartada'=>'blue','en limpieza'=>'sky','mantenimiento','en mantenimiento'=>'orange','fuera de servicio'=>'red',default=>'gray'};
    $liveIncidentTone=fn($state)=>match(mb_strtolower(trim((string)$state))){'pendiente','abierta'=>'amber','en proceso'=>'blue','resuelta','resuelto','cerrada'=>'green','rechazada','crítica','critica'=>'red',default=>'gray'};
    $liveName=fn($person)=>trim(implode(' ',array_filter([$person->nombre,$person->apellido_p ?? '',$person->apellido_m ?? ''])));
@endphp
<div class="lv-workspace">
    <header class="lv-hero"><div><span class="lv-eyebrow">NUTRILIADO · SUPERVISIÓN DEL SERVICIO</span><h1>Comedor en vivo</h1><p>Supervisa en tiempo real la ocupación, atención y operación de las mesas del servicio seleccionado.</p><span class="lv-site"><x-icon name="building"/>{{ $sede->nombre }}</span></div><div class="lv-hero-icon" aria-hidden="true"><x-icon name="activity"/></div></header>
    <form class="cw-filters lv-service-bar" method="GET"><input type="hidden" name="modulo" value="comedor">
        <label><span><x-icon name="utensils"/>Servicio</span><select name="id_servicio"><option value="">Selección automática</option>@foreach($servicios as $service)<option value="{{ $service->id_servicio }}" @selected($jornada?->id_servicio===$service->id_servicio)>{{ $service->nombre }}</option>@endforeach</select></label>
        <label><span><x-icon name="calendar"/>Jornada</span><input type="date" name="fecha" value="{{ $fechaOperacion }}" required></label><button class="cw-btn cw-outline">Consultar</button>
        <div class="lv-service-facts"><div><span><x-icon name="activity"/>Estado del servicio</span><strong class="cw-badge {{ $CW::tone($jornada->estado ?? null) }}">{{ $jornada->estado ?? 'Sin jornada' }}</strong></div><div><span><x-icon name="clock"/>Horario activo</span><strong>{{ $jornada?substr($jornada->inicio_hora,0,5).' – '.substr($jornada->fin_hora,0,5).' h':'Sin horario' }}</strong></div></div>
    </form>
    <section class="cw-live-banner lv-metrics" aria-label="Resumen del servicio">
        @foreach([
            ['Mesas totales',$liveTables->count(),'Inventario del comedor','layers','blue'],
            ['Disponibles',$liveTables->where('operativo','Disponible')->count(),'Listas para atender','check','green'],
            ['Ocupadas',$liveTables->where('operativo','Ocupada')->count(),'Atenciones abiertas','users','amber'],
            ['En limpieza',$liveTables->where('operativo','En limpieza')->count(),'Estado registrado','plates','sky'],
            ['Fuera de servicio',$liveTables->where('operativo','Fuera de servicio')->count(),'Mantenimiento: '.$liveTables->where('estado_fisico','Mantenimiento')->count(),'alert','red'],
            ['Meseros activos',$asignaciones->pluck('id_usuario_mesero')->unique()->count(),'Asignados a la jornada','users','mint'],
            ['Cobertura',$liveCoverage.'%',$liveCovered.' mesas cubiertas','chart','purple'],
        ] as [$label,$number,$detail,$icon,$tone])<article class="lv-metric lv-{{ $tone }}"><span><x-icon :name="$icon"/></span><div><small>{{ $label }}</small><strong>{{ $number }}</strong><p>{{ $detail }}</p></div></article>@endforeach
    </section>
    <div class="lv-controls"><div class="lv-zone-filter"><x-icon name="map"/><label><span class="cw-sr">Filtrar por zona</span><select data-cw-zone-filter><option value="">Todas las zonas</option>@foreach($zonas as $zone)<option value="{{ $zone->id_zona }}">{{ $zone->nombre }}</option>@endforeach</select></label></div><label class="lv-search"><x-icon name="search"/><input type="search" data-cw-search placeholder="Buscar mesa, zona o mesero…" aria-label="Buscar mesa, zona o mesero"></label><span class="lv-updated"><i></i><span data-cw-updated>Actualizado {{ now()->format('H:i:s') }} · cada 30 s</span></span></div>
    <div class="cw-live-layout lv-layout">
        <section class="lv-map-panel" aria-labelledby="lv-map-title">
            <header class="lv-map-heading"><span><x-icon name="map"/></span><div><h2 id="lv-map-title">Estado del comedor por zonas</h2><p>Selecciona una mesa para supervisar su ocupación y atención.</p></div><span class="lv-live-label"><i></i>En vivo</span></header>
            <div class="lv-legend" aria-label="Leyenda de estados">@foreach($liveTables->pluck('operativo')->unique() as $state)<span class="lv-{{ $liveTone($state) }}"><i></i>{{ $state }}</span>@endforeach</div>
            <div class="lv-floor">
            @forelse($zonas as $zone)
                <section class="cw-floor-zone lv-zone" data-zone-id="{{ $zone->id_zona }}" style="--live-zone:{{ $zone->color }}">
                    <header class="lv-zone-header"><span class="lv-zone-thumb">@if($zone->imagen_url ?? null)<img src="{{ \App\Support\ChefWorkspace::image($zone->imagen_url) }}" alt="{{ $zone->nombre }}" loading="lazy">@else<x-icon name="map"/>@endif</span><div><h3><i></i>{{ $zone->nombre }}</h3><p>{{ $zone->tables->count() }} mesas · {{ $zone->tables->sum('capacidad') }} lugares</p></div><span class="lv-zone-occupancy"><strong>{{ $zone->occupancy }}%</strong><small>ocupación</small></span></header>
                    <div class="lv-zone-team"><span>Atención</span><div>@forelse($zone->staff->unique('id_usuario_mesero')->take(3) as $person)<span class="lv-zone-waiter" title="{{ $liveName($person) }}"><x-capitan.avatar :person="$person"/><span>{{ $person->nombre }}</span></span>@empty<span class="lv-zone-unassigned">Sin mesero asignado</span>@endforelse @if($zone->staff->unique('id_usuario_mesero')->count()>3)<span class="lv-more">+{{ $zone->staff->unique('id_usuario_mesero')->count()-3 }}</span>@endif</div></div>
                    <div class="lv-table-grid">
                    @foreach($zone->tables as $table)
                        <article class="cw-map-table lv-table-card lv-{{ $liveTone($table->operativo) }}" data-cw-record data-cw-table-id="{{ $table->id_mesa }}" data-zone-id="{{ $table->id_zona }}" data-state="{{ $table->operativo }}">
                            <button type="button" class="lv-table-pick" data-cw-pick="{{ $table->id_mesa }}" aria-pressed="false" aria-label="Seleccionar mesa {{ $table->numero }}, {{ $zone->nombre }}, {{ $table->operativo }}"><strong>Mesa {{ $table->numero }}</strong><x-capitan.floor-table :number="$table->numero" :capacity="$table->capacidad"/><span class="lv-state"><i></i>{{ $table->operativo }}</span><span class="lv-table-capacity"><x-icon name="users"/>{{ $table->capacidad }} lugares</span></button>
                            <div class="lv-table-physical"><span>Físico</span><strong>{{ $table->estado_fisico }}</strong></div><p class="lv-table-waiter" title="{{ $table->assigned->pluck('nombre')->implode(', ') }}"><x-icon name="users"/>{{ $table->assigned->pluck('nombre')->implode(', ') ?: 'Sin asignar' }}</p>
                            <template data-cw-detail>@include('capitan.partials.live-table-detail')</template>
                        </article>
                    @endforeach
                    @if($zone->tables->isEmpty())<div class="lv-empty-zone"><x-icon name="plates"/><p>Sin mesas registradas</p></div>@endif
                    </div>
                </section>
            @empty<div class="lv-empty-zone"><x-icon name="map"/><h3>Sin zonas registradas</h3><p>El mapa mostrará las áreas y mesas de esta sede cuando estén configuradas.</p></div>@endforelse
            </div>
            <footer class="lv-map-footer"><span><x-icon name="map"/>Distribución ilustrativa automática</span><small>No representa un plano arquitectónico.</small></footer>
        </section>
        <aside class="lv-supervision" aria-labelledby="lv-supervision-title">
            <header class="lv-supervision-header"><span><x-icon name="activity"/></span><div><h2 id="lv-supervision-title">Panel de supervisión</h2><p>Atención y operación del comedor</p></div></header>
            <section class="lv-supervision-block lv-selected-block"><h3><x-icon name="plates"/>Mesa seleccionada</h3><div data-cw-selected aria-live="polite"><div class="lv-selection-empty"><span><x-icon name="layers"/></span><strong>Una vista de cada detalle</strong><p>Selecciona una mesa del mapa para consultar sus detalles.</p></div></div></section>
            <section class="lv-supervision-block"><div class="lv-block-heading"><h3><x-icon name="users"/>Meseros en servicio</h3><span>{{ $asignaciones->pluck('id_usuario_mesero')->unique()->count() }}</span></div>
                @forelse($asignaciones->groupBy('id_usuario_mesero') as $personAssignments)
                    @php($person=$personAssignments->first())
                    <article class="lv-staff-person"><x-capitan.avatar :person="$person"/><div><strong>{{ $liveName($person) }}</strong><small>{{ $personAssignments->pluck('zona')->unique()->implode(', ') }}</small><span>{{ $liveTables->whereIn('id_zona',$personAssignments->pluck('id_zona'))->count() }} mesas a cargo <i>Asignado</i></span></div></article>
                @empty<div class="lv-sidebar-empty"><x-icon name="users"/><p>No hay meseros asignados a esta jornada.</p></div>@endforelse
            </section>
            <section class="lv-supervision-block"><div class="lv-block-heading"><h3><x-icon name="alert"/>Incidencias recientes</h3><a href="{{ $link('incidencias') }}">Ver todas ↗</a></div>
                @forelse($incidenciasMapa->take(5) as $incident)<article class="lv-incident lv-{{ $liveIncidentTone($incident->estado) }}"><span class="lv-incident-icon"><x-icon name="alert"/></span><div><header><strong>#{{ $incident->id_incidencia }}</strong><span class="lv-state">{{ ucfirst($incident->estado) }}</span></header><p title="{{ $incident->descripcion }}">{{ $incident->descripcion }}</p><small><x-icon name="clock"/>{{ $incident->fecha?\Carbon\Carbon::parse($incident->fecha)->locale('es')->translatedFormat('d M Y, H:i'):'Sin fecha registrada' }}</small></div></article>@empty<div class="lv-sidebar-empty"><x-icon name="check"/><p>Sin incidencias registradas para esta consulta.</p></div>@endforelse
            </section>
        </aside>
    </div>
</div>
