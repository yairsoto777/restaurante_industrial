@push('styles')
<link rel="stylesheet" href="{{ asset('css/captain-tables.css') }}?v={{ filemtime(public_path('css/captain-tables.css')) }}">
@endpush
@php
    $tableTone=fn($state)=>match(mb_strtolower(trim((string)$state))){'disponible'=>'green','ocupada'=>'amber','en limpieza'=>'blue','fuera de servicio'=>'red','mantenimiento','en mantenimiento'=>'orange','reservada'=>'purple',default=>'gray'};
@endphp
<div class="ctv-workspace">
    <header class="ctv-hero"><div><span class="ctv-eyebrow">NUTRILIADO · ORGANIZACIÓN DEL COMEDOR</span><h1>Gestión de mesas</h1><p>Administra el estado y la disponibilidad de las mesas del comedor.</p><span class="ctv-site"><x-icon name="building"/>{{ $sede->nombre }}</span></div><div class="ctv-hero-symbol" aria-hidden="true"><x-capitan.floor-table number="" :capacity="4"/></div></header>
    <form class="cw-filters ctv-filters" method="GET">
        <input type="hidden" name="modulo" value="mesas">
        <label><span><x-icon name="utensils"/>Servicio</span><select name="id_servicio"><option value="">Selección automática</option>@foreach($servicios as $service)<option value="{{ $service->id_servicio }}" @selected($jornada?->id_servicio===$service->id_servicio)>{{ $service->nombre }} · {{ substr($service->inicio_hora,0,5) }}–{{ substr($service->fin_hora,0,5) }}</option>@endforeach</select></label>
        <label><span><x-icon name="calendar"/>Jornada</span><input type="date" name="fecha" value="{{ $fechaOperacion }}" required></label>
        <button class="cw-btn cw-outline">Consultar</button><button class="cw-btn ctv-register" type="button" data-cw-open="table-create" @disabled($zonas->isEmpty())><x-icon name="plus"/>Registrar mesa</button>
    </form>
    <section class="ctv-metrics" aria-label="Disponibilidad de mesas">
        @foreach([
            ['Disponibles',$available,'Listas para recibir comensales','check','green'],
            ['Ocupadas',$occupied,'Con atención abierta','users','amber'],
            ['En limpieza',$mesasServicio->where('operativo','En limpieza')->count(),'Según el estado registrado','layers','blue'],
            ['Fuera de servicio',$mesasServicio->where('operativo','Fuera de servicio')->count(),'Sin disponibilidad operativa','alert','red'],
        ] as [$label,$number,$detail,$icon,$tone])<article class="ctv-metric ctv-{{ $tone }}"><span><x-icon :name="$icon"/></span><div><small>{{ $label }}</small><strong>{{ $number }}</strong><p>{{ $detail }}</p></div></article>@endforeach
    </section>
    <section class="ctv-directory" aria-labelledby="ctv-directory-title">
        <div class="ctv-directory-heading"><div><span class="ctv-eyebrow">DISTRIBUCIÓN DEL COMEDOR</span><h2 id="ctv-directory-title">Mesas registradas <span>{{ $total }}</span></h2></div><div class="ctv-views" aria-label="Tipo de visualización"><button type="button" data-cw-view="cards" aria-pressed="true"><x-icon name="layers"/>Tarjetas</button><button type="button" data-cw-view="table" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M3 14h18M9 4v16"/></svg>Tabla</button></div></div>
        <div class="ctv-secondary-filters"><label>Zona<select data-ctv-zone><option value="">Todas las zonas</option>@foreach($zonas as $zone)<option value="{{ $zone->id_zona }}">{{ $zone->nombre }}</option>@endforeach</select></label><label>Estado<select data-ctv-state><option value="">Todos los estados</option>@foreach($mesasServicio->pluck('operativo')->unique() as $state)<option value="{{ $state }}">{{ $state }}</option>@endforeach</select></label><label class="ctv-search">Buscar mesa<span><x-icon name="search"/><input type="search" data-ctv-search placeholder="Número de mesa…" aria-label="Buscar por número de mesa"></span></label><span class="ctv-visible"><strong data-ctv-visible>{{ $total }}</strong> mesas</span></div>
        <div class="ctv-zone-groups" data-cw-cards>
            @forelse($zonas as $zone)
            <section class="ctv-zone-group" data-ctv-group data-zone-id="{{ $zone->id_zona }}" style="--table-zone:{{ $zone->color }}"><header><h3><i></i>{{ $zone->nombre }}</h3><span>{{ $zone->tables->count() }} mesas · {{ $zone->tables->sum('capacidad') }} lugares</span></header><div class="ctv-grid">
                @foreach($zone->tables as $table)
                <article class="cw-map-table ctv-table-card ctv-{{ $tableTone($table->operativo) }}" data-ctv-record data-zone-id="{{ $table->id_zona }}" data-table-state="{{ $table->operativo }}" data-table-number="{{ $table->numero }}" data-cw-table-id="{{ $table->id_mesa }}">
                    <div class="ctv-card-title"><h4>Mesa {{ $table->numero }}</h4><span title="{{ $table->zona }}"><i></i>{{ $table->zona }}</span></div>
                    <button class="ctv-table-pick" type="button" data-cw-pick="{{ $table->id_mesa }}" aria-label="Ver detalles de mesa {{ $table->numero }} en {{ $table->zona }}"><x-capitan.floor-table :number="$table->numero" :capacity="$table->capacidad"/></button>
                    <span class="ctv-state"><i></i>{{ $table->operativo }}</span>
                    <p class="ctv-capacity"><x-icon name="users"/><strong>{{ $table->capacidad }}</strong> comensales</p>
                    <div class="ctv-physical"><span>Estado físico</span><strong>{{ $table->estado_fisico }}</strong></div>
                    <div class="ctv-waiter"><x-icon name="users"/><span>{{ $table->assigned->pluck('nombre')->implode(', ') ?: 'Sin mesero asignado' }}</span></div>
                    <footer><button type="button" data-cw-pick="{{ $table->id_mesa }}">Detalles</button><button type="button" data-cw-open="table-{{ $table->id_mesa }}">Editar mesa <span aria-hidden="true">↗</span></button></footer>
                    <template data-cw-detail>@include('capitan.partials.table-card-detail')</template>
                </article>
                @endforeach
                @if($zone->tables->isEmpty())<div class="ctv-empty-zone"><x-icon name="layers"/><p>Sin mesas registradas en esta zona.</p><button type="button" class="cw-btn cw-outline" data-cw-open="table-create">Registrar mesa</button></div>@endif
            </div></section>
            @empty<div class="ctv-empty"><x-icon name="map"/><h3>Primero necesitas una zona</h3><p>Configura las áreas del comedor para registrar sus mesas.</p><a class="cw-btn" href="{{ $link('zonas') }}">Ir a Gestión de zonas</a></div>@endforelse
        </div>
        <div class="ctv-table-wrap" data-cw-table hidden><table><thead><tr><th>Mesa</th><th>Zona</th><th>Capacidad</th><th>Estado físico</th><th>Estado operativo</th><th>Mesero asignado</th><th>Acciones</th></tr></thead><tbody>@foreach($mesasServicio as $table)<tr data-ctv-record data-zone-id="{{ $table->id_zona }}" data-table-state="{{ $table->operativo }}" data-table-number="{{ $table->numero }}"><td><strong>Mesa {{ $table->numero }}</strong></td><td>{{ $table->zona }}</td><td>{{ $table->capacidad }} comensales</td><td>{{ $table->estado_fisico }}</td><td><span class="ctv-state ctv-{{ $tableTone($table->operativo) }}"><i></i>{{ $table->operativo }}</span></td><td>{{ $table->assigned->pluck('nombre')->implode(', ') ?: 'Sin asignar' }}</td><td><button type="button" class="cw-btn cw-outline" data-cw-open="table-{{ $table->id_mesa }}">Editar</button></td></tr>@endforeach</tbody></table></div>
        <p class="ctv-no-results" data-ctv-no-results hidden>No hay mesas que coincidan con los filtros seleccionados.</p>
    </section>
    <dialog class="cw-dialog ctv-details-dialog" id="cw-table-details"><button type="button" data-cw-close aria-label="Cerrar detalles">×</button><div data-cw-selected></div></dialog>
    @include('capitan.partials.workspace-table-forms')
</div>
<script src="{{ asset('js/captain-tables.js') }}?v={{ filemtime(public_path('js/captain-tables.js')) }}" defer></script>
