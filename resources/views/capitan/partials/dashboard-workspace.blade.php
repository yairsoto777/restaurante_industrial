@push('styles')
<link rel="stylesheet" href="{{ asset('css/captain-dashboard.css') }}?v={{ filemtime(public_path('css/captain-dashboard.css')) }}">
@endpush
@php
    $dashboardTone=fn($state)=>match(mb_strtolower(trim((string)$state))){'disponible'=>'green','ocupada'=>'blue','reservada','apartada'=>'amber','con incidencia'=>'red','mantenimiento','en mantenimiento'=>'orange','fuera de servicio','inhabilitada'=>'gray','en limpieza'=>'sky',default=>'gray'};
    $dashboardDisabled=$mesasServicio->filter(fn($table)=>in_array(mb_strtolower(trim((string)$table->operativo)),['mantenimiento','en mantenimiento','fuera de servicio','inhabilitada'],true))->count();
    $dashboardOther=max(0,$total-$occupied-$available-$dashboardDisabled);
    $dashboardSlices=[];$dashboardCursor=0;
    foreach([[$occupied,'#3485d5'],[$available,'#24a77d'],[$dashboardDisabled,'#98a8ba'],[$dashboardOther,'#e4b52a']] as [$count,$color]){
        if(!$count)continue;$next=$dashboardCursor+($total?$count*100/$total:0);
        $dashboardSlices[]=$color.' '.$dashboardCursor.'% '.$next.'%';$dashboardCursor=$next;
    }
    $dashboardChart=implode(',',$dashboardSlices) ?: '#e8eef5 0% 100%';
    $dashboardGuests=collect($comensalesPorHora)->sum('total');
    $dashboardName=fn($person)=>trim(implode(' ',array_filter([$person->nombre,$person->apellido_p ?? '',$person->apellido_m ?? ''])));
@endphp
<div class="dp-workspace">
    <header class="dp-hero dashboard-banner dashboard-banner--captain"><div><span class="dp-eyebrow">NUTRILIADO · OPERACIÓN Y ATENCIÓN</span><h1>Panel principal</h1><p>Capitán de meseros · Control y supervisión del comedor</p><div class="dp-hero-meta"><span><x-icon name="building"/>{{ $sede->nombre }}</span><span>Hola, {{ auth()->user()->nombre }}</span></div></div><div class="dp-hero-icon" aria-hidden="true"><x-icon name="layers"/></div></header>
    <form class="cw-filters dp-service-bar" method="GET"><input type="hidden" name="modulo" value="panel">
        <label><span><x-icon name="utensils"/>Servicio</span><select name="id_servicio"><option value="">Selección automática</option>@foreach($servicios as $service)<option value="{{ $service->id_servicio }}" @selected($jornada?->id_servicio===$service->id_servicio)>{{ $service->nombre }}</option>@endforeach</select></label>
        <label><span><x-icon name="calendar"/>Fecha de operación / Jornada</span><input type="date" name="fecha" value="{{ $fechaOperacion }}" required></label><button class="cw-btn cw-outline">Consultar</button>
        <div class="dp-service-facts"><div><span><x-icon name="activity"/>Estado del servicio</span><strong class="cw-badge {{ $CW::tone($jornada->estado ?? null) }}">{{ $jornada->estado ?? 'Sin jornada' }}</strong></div><div><span><x-icon name="clock"/>Horario</span><strong>{{ $jornada?substr($jornada->inicio_hora,0,5).' – '.substr($jornada->fin_hora,0,5).' h':'Sin horario' }}</strong></div></div>
    </form>
    <section class="dp-metrics" aria-label="Resumen operativo del comedor">
        @foreach([
            ['Zonas registradas',$zonas->count(),'Áreas del comedor','Distribución del comedor','map','blue'],
            ['Mesas disponibles',$available,'Listas para atender','En la jornada consultada','plates','green'],
            ['Mesas ocupadas',$occupied,'Atenciones abiertas',$percent.'% de ocupación','users','amber'],
            ['Incidencias',$metricas['incidencias'],'Abiertas de la sede','Seguimiento pendiente','alert','red'],
        ] as [$label,$number,$description,$badge,$icon,$tone])<article class="dp-metric dp-{{ $tone }}"><div><span class="dp-metric-title">{{ $label }}</span><strong>{{ $number }}</strong><p>{{ $description }}</p><small>{{ $badge }}</small></div><span class="dp-metric-icon"><x-icon :name="$icon"/></span></article>@endforeach
    </section>
    <div class="dp-main-grid">
        <div class="dp-analysis">
            <section class="dp-card dp-occupancy" aria-labelledby="dp-occupancy-title"><header class="dp-card-heading"><span><x-icon name="chart"/></span><div><h2 id="dp-occupancy-title">Ocupación del comedor</h2><p>Distribución de mesas de la jornada</p></div></header>
                <div class="dp-donut" style="--dashboard-chart:{{ $dashboardChart }}" role="img" aria-label="{{ $percent }}% de ocupación: {{ $occupied }} ocupadas, {{ $available }} disponibles, {{ $dashboardDisabled }} inhabilitadas{{ $dashboardOther?', '.$dashboardOther.' en otros estados':'' }}"><div><strong>{{ $percent }}<span>%</span></strong><small>de ocupación</small><span>{{ $occupied }} de {{ $total }} mesas</span></div></div>
                <dl class="dp-occupancy-legend"><div class="dp-blue"><dt><i></i>Ocupadas</dt><dd>{{ $occupied }}</dd></div><div class="dp-green"><dt><i></i>Disponibles</dt><dd>{{ $available }}</dd></div><div class="dp-gray"><dt><i></i>Inhabilitadas</dt><dd>{{ $dashboardDisabled }}</dd></div>@if($dashboardOther)<div class="dp-amber"><dt><i></i>Otros estados</dt><dd>{{ $dashboardOther }}</dd></div>@endif</dl>
                <a class="dp-card-link" href="{{ $link('comedor') }}">Supervisar comedor <span aria-hidden="true">↗</span></a>
            </section>
            <section class="dp-card dp-service-summary" aria-labelledby="dp-summary-title"><header class="dp-card-heading"><span><x-icon name="utensils"/></span><div><h2 id="dp-summary-title">Resumen del servicio</h2><p>Información real de la operación</p></div></header>
                @if($jornada)<div class="dp-service-cover"><img src="{{ \App\Support\ChefWorkspace::serviceImage($jornada) }}" alt="Portada de {{ $jornada->nombre }}" loading="lazy"><div><small>{{ \Carbon\Carbon::parse($jornada->fecha)->locale('es')->translatedFormat('d M Y') }}</small><h3>{{ $jornada->nombre }}</h3></div><span class="cw-badge {{ $CW::tone($jornada->estado) }}">{{ $jornada->estado }}</span></div>@else<div class="dp-summary-empty"><x-icon name="calendar"/><p>Sin jornada autorizada para esta fecha.</p></div>@endif
                <dl class="dp-summary-rows"><div><dt><span><x-icon name="users"/></span><div>Comensales registrados<small>En la fecha consultada</small></div></dt><dd>{{ $dashboardGuests }}</dd></div><div><dt><span><x-icon name="layers"/></span>Mesas totales</dt><dd>{{ $total }}</dd></div><div><dt><span><x-icon name="users"/></span>Meseros asignados</dt><dd>{{ $assigned->count() }}</dd></div>@if($jornada)<div><dt><span><x-icon name="plates"/></span>Capacidad del servicio</dt><dd>{{ $jornada->capacidad ?? '—' }}</dd></div>@endif<div><dt><span><x-icon name="check"/></span>Cobertura de atención</dt><dd>{{ $coverage }}%</dd></div><div><dt><span><x-icon name="calendar"/></span>Servicios autorizados<small>De la fecha consultada</small></dt><dd>{{ $servicios->count() }}</dd></div></dl>
                <a class="dp-card-link" href="{{ $link('servicios') }}">Consultar servicios <span aria-hidden="true">↗</span></a>
            </section>
        </div>
        <section class="dp-card dp-dining" aria-labelledby="dp-dining-title"><header class="dp-card-heading dp-dining-heading"><span><x-icon name="map"/></span><div><h2 id="dp-dining-title">Vista del comedor</h2><p>Zonas, mesas y disponibilidad del servicio</p></div><a href="{{ $link('comedor') }}" class="cw-btn cw-outline"><x-icon name="activity"/>Ver en vivo</a></header>
            <div class="dp-map-controls"><div class="dp-map-legend">@foreach($mesasServicio->pluck('operativo')->unique() as $state)<span class="dp-{{ $dashboardTone($state) }}"><i></i>{{ $state }}</span>@endforeach</div><label class="dp-map-search"><x-icon name="search"/><input type="search" data-cw-search placeholder="Buscar mesa o zona…" aria-label="Buscar mesa, zona o mesero"></label></div>
            <div class="dp-map-zones">
            @forelse($zonas as $zone)
                @php
                    $zoneIncident=$incidenciasMapa->contains(fn($i)=>empty($i->fecha_cierre) && !in_array(mb_strtolower(trim($i->estado ?? '')),['resuelta','resuelto','cerrada'],true) && ((isset($i->id_zona) && (int)$i->id_zona===(int)$zone->id_zona) || $zone->tables->pluck('id_mesa')->contains($i->id_mesa ?? null)));
                    $zoneStatus=$zoneIncident?'Con incidencia':($zone->staff->isNotEmpty()?'Con atención':($zone->tables->isEmpty()?'Sin mesas':'Sin asignar'));
                    $zoneTone=$zoneIncident?'red':($zone->staff->isNotEmpty()?'green':($zone->tables->isEmpty()?'gray':'amber'));
                @endphp
                <section class="dp-map-zone" style="--dashboard-zone:{{ $zone->color }}"><header><span class="dp-zone-photo">@if($zone->imagen_url ?? null)<img src="{{ \App\Support\ChefWorkspace::image($zone->imagen_url) }}" alt="{{ $zone->nombre }}" loading="lazy">@else<x-icon name="map"/>@endif</span><div><h3>{{ $zone->nombre }}</h3><p title="{{ $zone->descripcion ?? '' }}">{{ $zone->descripcion ?: $zone->tables->count().' mesas · '.$zone->tables->sum('capacidad').' lugares' }}</p></div><span class="dp-zone-status dp-{{ $zoneTone }}"><i></i>{{ $zoneStatus }}</span></header>
                    <div class="dp-zone-subline"><span><i></i>{{ $zone->tables->count() }} mesas</span><span>{{ $zone->occupancy }}% ocupación</span></div>
                    <div class="dp-table-grid">
                    @foreach($zone->tables as $table)<article class="cw-map-table dp-table dp-{{ $dashboardTone($table->operativo) }}" data-cw-record data-cw-table-id="{{ $table->id_mesa }}" data-zone-id="{{ $table->id_zona }}"><button type="button" data-cw-pick="{{ $table->id_mesa }}" title="Mesa {{ $table->numero }} · {{ $zone->nombre }} · {{ $table->capacidad }} lugares · {{ $table->operativo }}" aria-label="Ver detalles de mesa {{ $table->numero }}, {{ $zone->nombre }}, {{ $table->operativo }}"><span class="cw-sr">Mesa {{ $table->numero }} · {{ $zone->nombre }} · {{ $table->assigned->pluck('nombre')->implode(', ') }}</span><span class="dp-table-face"><strong>{{ $table->numero }}</strong></span><small>{{ $table->operativo }}</small></button><template data-cw-detail>@include('capitan.partials.table-card-detail',['tableTone'=>$dashboardTone])</template></article>@endforeach
                    @if($zone->tables->isEmpty())<div class="dp-map-empty"><x-icon name="plates"/><span>Sin mesas registradas</span></div>@endif
                    </div>
                </section>
            @empty<div class="dp-map-empty"><x-icon name="map"/><h3>Aún no hay zonas registradas</h3><p>Las áreas y mesas de esta sede aparecerán aquí.</p><a class="cw-btn cw-outline" href="{{ $link('zonas') }}">Gestionar zonas</a></div>@endforelse
            </div>
            <footer class="dp-map-footer"><x-icon name="map"/>Distribución visual ilustrativa · selecciona una mesa para ver sus detalles.</footer>
        </section>
    </div>
    <div class="dp-bottom-grid">
        <section class="dp-card"><header class="dp-card-heading"><span><x-icon name="users"/></span><div><h2>Meseros en servicio</h2><p>Equipo asignado a la jornada</p></div><a class="dp-heading-link" href="{{ $link('asignaciones') }}">Gestionar ↗</a></header><div class="dp-staff-grid">@forelse($asignaciones->groupBy('id_usuario_mesero') as $personAssignments)@php($person=$personAssignments->first())<article class="dp-person"><x-capitan.avatar :person="$person"/><div><strong>{{ $dashboardName($person) }}</strong><small>{{ $personAssignments->pluck('zona')->unique()->implode(', ') }}</small><span>{{ $mesasServicio->whereIn('id_zona',$personAssignments->pluck('id_zona'))->count() }} mesas a cargo</span></div><i>Asignado</i></article>@empty<div class="dp-inline-empty"><x-icon name="users"/><p>Sin meseros asignados a esta jornada.</p></div>@endforelse</div></section>
        <section class="dp-card"><header class="dp-card-heading"><span><x-icon name="activity"/></span><div><h2>Actividad reciente</h2><p>Atenciones registradas en la sede</p></div></header><div class="dp-activity-list">@forelse($atenciones->take(5) as $activity)<article><span class="dp-activity-icon"><x-icon name="plates"/></span><div><strong>Mesa {{ $activity->mesa }} · {{ $activity->servicio }}</strong><small>{{ $activity->inicio_hora ?? 'Sin hora registrada' }} · {{ $activity->comensales ?? '—' }} comensales</small></div><span class="cw-badge {{ $CW::tone($activity->estado) }}">{{ $activity->estado }}</span></article>@empty<div class="dp-inline-empty"><x-icon name="clock"/><p>Sin movimientos registrados.</p></div>@endforelse</div></section>
    </div>
    <section class="dp-quick-actions"><div><x-icon name="layers"/><strong>Acciones rápidas</strong></div><nav aria-label="Acciones rápidas del Capitán">@foreach(['zonas'=>['map','Crear zona'],'mesas'=>['plates','Registrar mesa'],'asignaciones'=>['users','Asignar mesero'],'comedor'=>['activity','Ver comedor en vivo'],'incidencias'=>['alert','Registrar incidencia']] as $module=>[$icon,$label])<a href="{{ $link($module) }}"><x-icon :name="$icon"/>{{ $label }}<span aria-hidden="true">↗</span></a>@endforeach</nav></section>
    <dialog class="cw-dialog dp-table-dialog" id="cw-table-details"><button type="button" data-cw-close aria-label="Cerrar detalles de mesa">×</button><div data-cw-selected></div></dialog>
</div>
