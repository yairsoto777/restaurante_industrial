@push('styles')
<link rel="stylesheet" href="{{ asset('css/captain-zones.css') }}?v={{ filemtime(public_path('css/captain-zones.css')) }}">
@endpush
<div class="czv-workspace">
    <header class="czv-hero">
        <div><span class="czv-eyebrow">NUTRILIADO · ORGANIZACIÓN DEL COMEDOR</span><h1>Gestión de zonas</h1><p>Organiza las áreas del comedor, sus mesas y asigna al personal de servicio.</p><span class="czv-site"><x-icon name="building"/>{{ $sede->nombre }}</span></div>
        <div class="czv-hero-icon" aria-hidden="true"><x-icon name="map"/></div>
    </header>
    <form class="cw-filters czv-filters" method="GET">
        <input type="hidden" name="modulo" value="zonas">
        <label><span><x-icon name="utensils"/>Servicio</span><select name="id_servicio"><option value="">Selección automática</option>@foreach($servicios as $service)<option value="{{ $service->id_servicio }}" @selected($jornada?->id_servicio===$service->id_servicio)>{{ $service->nombre }} · {{ substr($service->inicio_hora,0,5) }}–{{ substr($service->fin_hora,0,5) }}</option>@endforeach</select></label>
        <label><span><x-icon name="calendar"/>Jornada</span><input type="date" name="fecha" value="{{ $fechaOperacion }}" required></label>
        <button class="cw-btn cw-outline czv-consult">Consultar</button>
        <button type="button" class="cw-btn czv-create" data-cw-open="zone-create"><x-icon name="plus"/>Crear zona</button>
    </form>
    <section class="czv-metrics" aria-label="Resumen de zonas y atención">
        @foreach([
            ['Total de zonas',$zonas->count(),'Áreas registradas en la sede','map','blue'],
            ['Mesas en servicio',$mesasServicio->where('estado_fisico','Disponible')->count(),'Mesas físicamente disponibles','layers','green'],
            ['Meseros asignados',$assigned->count(),'En la jornada consultada','users','purple'],
            ['Ocupación actual',$percent.'%',$occupied.' de '.$total.' mesas ocupadas','chart','amber'],
        ] as [$label,$number,$detail,$icon,$tone])
        <article class="czv-metric czv-metric-{{ $tone }}"><span class="czv-metric-icon"><x-icon :name="$icon"/></span><div><span>{{ $label }}</span><strong>{{ $number }}</strong><small>{{ $detail }}</small></div></article>
        @endforeach
    </section>
    <section class="czv-registered" aria-labelledby="czv-list-title">
        <div class="czv-list-heading"><div><span class="czv-eyebrow">DISTRIBUCIÓN Y ATENCIÓN</span><h2 id="czv-list-title">Zonas registradas <span>{{ $zonas->count() }}</span></h2></div><div class="czv-local-filters"><label><span class="cw-sr">Filtrar zona</span><select data-cw-zone-filter><option value="">Todas las zonas</option>@foreach($zonas as $zone)<option value="{{ $zone->id_zona }}">{{ $zone->nombre }}</option>@endforeach</select></label><label class="czv-search"><x-icon name="search"/><input type="search" data-cw-search placeholder="Buscar zona, mesa o mesero…" aria-label="Buscar zona, mesa o mesero"></label></div></div>
        @if(!$zoneMedia)<p class="cw-notice">Las fotografías, descripción y color personalizado requieren las columnas de zonas.</p>@endif
        <div class="czv-zone-list">
        @forelse($zonas as $zone)
            @php
                $numbers=$zone->tables->pluck('numero')->sort()->values();
                $consecutive=$numbers->count()>1 && (int)$numbers->last()-(int)$numbers->first()+1===$numbers->count();
                $tableLabel=$numbers->isEmpty()?'Sin mesas asociadas':($consecutive?'Mesas '.$numbers->first().' – '.$numbers->last():'Mesa'.($numbers->count()>1?'s ':' ').$numbers->take(6)->implode(', ').($numbers->count()>6?'…':''));
                $hasIncident=$incidenciasMapa->contains(fn($i)=>empty($i->fecha_cierre) && !in_array(mb_strtolower(trim($i->estado ?? '')),['resuelta','resuelto','cerrada'],true) && ((isset($i->id_zona) && (int)$i->id_zona===(int)$zone->id_zona) || $zone->tables->pluck('id_mesa')->contains($i->id_mesa ?? null)));
                $status=$hasIncident?'Con incidencia':($zone->staff->isNotEmpty()?'Atención asignada':($zone->tables->isEmpty()?'Sin mesas':'Sin asignar'));
                $tone=$hasIncident?'orange':($zone->staff->isNotEmpty()?'green':($zone->tables->isEmpty()?'gray':'yellow'));
            @endphp
            <article class="czv-zone" data-cw-record data-zone-id="{{ $zone->id_zona }}" style="--zone:{{ $zone->color }}">
                <div class="czv-zone-cover"><img src="{{ \App\Support\ChefWorkspace::image($zone->imagen_url ?? null,'img/ui/captain-dining.webp') }}" alt="{{ ($zone->imagen_url ?? null) ? 'Fotografía de '.$zone->nombre : 'Imagen decorativa del comedor' }}" loading="lazy"><div class="czv-zone-cover-label"><i></i><strong>{{ $zone->nombre }}</strong></div></div>
                <div class="czv-zone-content">
                    <div class="czv-zone-title"><h3>{{ $zone->nombre }}</h3><span>{{ $zone->tables->sum('capacidad') }} lugares</span></div>
                    <p class="czv-description">{{ $zone->descripcion ?? 'Sin descripción registrada.' }}</p>
                    <div class="czv-zone-details">
                        <div><span class="czv-field-title"><x-icon name="layers"/>Mesas incluidas</span><strong title="{{ $numbers->implode(', ') }}">{{ $tableLabel }}</strong><a href="{{ $link('mesas') }}">{{ $zone->tables->isEmpty()?'Registrar mesas':'Consultar mesas' }} <span aria-hidden="true">↗</span></a></div>
                        <div class="czv-zone-count"><span class="czv-field-title">Cantidad de mesas</span><strong>{{ $zone->tables->count() }}</strong><small>{{ $zone->occupancy }}% de ocupación</small></div>
                        <div><span class="czv-field-title"><x-icon name="users"/>Mesero asignado</span><div class="czv-staff">
                            @forelse($zone->staff->unique('id_usuario_mesero')->take(2) as $person)<div class="czv-staff-person"><x-capitan.avatar :person="$person"/><strong title="{{ trim($person->nombre.' '.$person->apellido_p.' '.$person->apellido_m) }}">{{ $person->nombre }} {{ $person->apellido_p }}</strong></div>@empty<span class="czv-unassigned">Sin asignar</span>@endforelse
                            @if($zone->staff->unique('id_usuario_mesero')->count()>2)<small>+{{ $zone->staff->unique('id_usuario_mesero')->count()-2 }} meseros asignados</small>@endif
                        </div></div>
                    </div>
                </div>
                <div class="czv-zone-actions"><span class="cw-badge {{ $tone }}"><i class="czv-status-dot"></i>{{ $status }}</span><button type="button" class="cw-btn cw-outline" data-cw-open="zone-{{ $zone->id_zona }}"><x-icon name="layers"/>Editar</button><a class="cw-btn" href="{{ $link('asignaciones') }}"><x-icon name="users"/>Asignar mesero</a></div>
            </article>
        @empty
            <div class="czv-empty"><span><x-icon name="map"/></span><h3>Tu comedor comienza con una zona</h3><p>Crea la primera área para organizar sus mesas y la atención del equipo.</p><button class="cw-btn" data-cw-open="zone-create"><x-icon name="plus"/>Crear zona</button></div>
        @endforelse
        </div>
    </section>
    @include('capitan.partials.workspace-zone-forms')
</div>
<script src="{{ asset('js/captain-zones.js') }}?v={{ filemtime(public_path('js/captain-zones.js')) }}" defer></script>
