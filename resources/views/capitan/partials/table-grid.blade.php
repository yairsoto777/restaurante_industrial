<section class="cz-panel captain-floor" aria-labelledby="ops-floor-title">
    <div class="cz-panelhead"><div><span>VISTA POR ZONAS</span><h2 id="ops-floor-title">Espacios del comedor</h2><small>Inventario físico; la ocupación se consulta en Servicio en vivo.</small></div><a class="button" href="{{ route('capitan.index', ['modulo'=>'servicios']) }}">Consultar ocupación</a></div>
    <div class="captain-floor-toolbar">
        <label><span>Buscar mesa o zona</span><input type="search" data-captain-map-search placeholder="Número de mesa o nombre de zona"></label>
        <label><span>Zona</span><select data-captain-map-zone><option value="">Todas las zonas</option>@foreach($zonas as $zona)<option value="{{ $zona->id_zona }}">{{ $zona->nombre }}</option>@endforeach</select></label>
        <span class="captain-map-count" data-captain-map-count role="status">{{ $mesas->count() }} mesas</span>
    </div>
    <div class="captain-floor-legend"><span><i class="captain-dot captain-dot-success"></i>Físicamente disponible</span><span><i class="captain-dot captain-dot-danger"></i>Mantenimiento</span><span><i class="captain-dot captain-dot-neutral"></i>Fuera de servicio / sin estado</span></div>
    <p class="ops-note">Representación simbólica por zona; no corresponde a un plano físico ni indica la ocupación.</p>
    @forelse($mesas->groupBy('zona_nombre') as $nombreZona => $mesasZona)
        <div data-ops-zone-group><div class="ops-section-title"><h3>{{ $nombreZona }}</h3><small>{{ $mesasZona->count() }} mesas · {{ $mesasZona->sum('capacidad') }} lugares</small></div>
        <div class="ops-cards">
            @foreach($mesasZona as $mesa)
                <article class="ops-record ops-table-card" data-captain-physical="{{ $mesa->estado_fisico }}" data-ops-floor-card data-zone="{{ $mesa->id_zona }}" data-search="{{ mb_strtolower('mesa '.$mesa->numero.' '.$mesa->zona_nombre) }}">
                    <div class="ops-record-head"><h3>Mesa {{ $mesa->numero }}</h3><x-icon name="plates" /></div>
                    <x-capitan.table-symbol :number="$mesa->numero" />
                    <p class="captain-table-zone">{{ $mesa->zona_nombre }}</p>
                    <p>{{ $mesa->capacidad }} lugares</p><div><small class="ops-note">Estado físico</small><br><x-ops.status :value="$mesa->estado_fisico" :physical="true" /></div>
                    @if($mesa->observaciones)<p>{{ $mesa->observaciones }}</p>@endif
                    <details><summary>Detalles de la mesa</summary><p>Zona: {{ $mesa->zona_nombre }} · Identificador #{{ $mesa->id_mesa }}</p><p>Consulta las atenciones vigentes antes de asignar un servicio.</p></details>
                    <button type="button" class="button" data-edit-table="{{ $mesa->id_mesa }}" data-zone="{{ $mesa->id_zona }}" data-number="{{ $mesa->numero }}" data-capacity="{{ $mesa->capacidad }}" data-status="{{ $mesa->estado_fisico }}" data-observations="{{ $mesa->observaciones }}">Editar mesa</button>
                </article>
            @endforeach
        </div></div>
    @empty<x-ops.empty title="Sin mesas registradas" message="Crea una zona y registra las mesas con las acciones de esta pantalla." icon="map" />@endforelse
</section>
