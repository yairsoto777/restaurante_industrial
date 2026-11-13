<section class="captain-live-floor" aria-labelledby="captain-live-floor-title">
    <div class="ops-section-title"><div><span class="section-kicker">MONITOREO DEL COMEDOR</span><h2 id="captain-live-floor-title">Mesas del servicio</h2></div><span class="captain-snapshot"><x-icon name="clock" /> Fecha consultada: {{ $fechaOperacion }}</span></div>
    <div class="captain-floor-toolbar"><label>Buscar mesa, zona o mesero<input type="search" data-captain-live-search placeholder="Escribe para localizar una mesa"></label><label>Estado operativo<select data-captain-live-status><option value="">Todos los estados</option><option value="disponible">Disponible</option><option value="ocupada">Ocupada</option><option value="por-cobrar">Por cobrar</option><option value="fuera-servicio">Fuera de servicio</option></select></label></div>
    <div class="captain-floor-legend"><span><i class="captain-dot captain-dot-success"></i>Disponible</span><span><i class="captain-dot captain-dot-warning"></i>Ocupada / por cobrar</span><span><i class="captain-dot captain-dot-danger"></i>Mantenimiento</span><span><i class="captain-dot captain-dot-neutral"></i>Fuera de servicio</span></div>
    <div class="ops-cards captain-live-grid" tabindex="0" role="region" aria-label="Tarjetas de mesas del servicio">
        @forelse($mesasServicio as $mesaEnTablero)
            @php
                $estadoEnTablero = mb_strtolower(trim((string) $mesaEnTablero->atencion_estado)) === 'pendiente de pago'
                    ? 'Por cobrar'
                    : ($mesaEnTablero->id_atencion !== null ? 'Ocupada'
                        : (mb_strtolower(trim((string) $mesaEnTablero->estado_fisico)) === 'disponible' ? 'Disponible' : ($mesaEnTablero->estado_fisico ?: 'Fuera de servicio')));
            @endphp
            <article class="ops-record ops-table-card" data-captain-live-card="{{ $mesaEnTablero->id_mesa }}" data-captain-state="{{ $estadoEnTablero }}" data-captain-physical="{{ $mesaEnTablero->estado_fisico }}">
                <div class="ops-record-head"><h3>Mesa {{ $mesaEnTablero->numero }}</h3><x-ops.status :value="$estadoEnTablero" /></div>
                <x-capitan.table-symbol :number="$mesaEnTablero->numero" />
                <p class="captain-table-zone">{{ $mesaEnTablero->zona }} · {{ $mesaEnTablero->capacidad }} lugares</p>
                <dl><div><dt>Comensales</dt><dd>{{ $mesaEnTablero->comensales ?? '—' }}</dd></div><div><dt>Atención</dt><dd>{{ $mesaEnTablero->id_atencion ? '#'.$mesaEnTablero->id_atencion : 'Sin atención' }}</dd></div></dl>
                <p><x-icon name="users" /> {{ $mesaEnTablero->mesero ?: 'Sin asignar' }}</p>
                <p>Comanda: {{ $mesaEnTablero->comanda_estado ?: ($mesaEnTablero->id_atencion ? 'Sin comanda' : 'Sin atención') }}</p>
                <p class="captain-physical-note">Estado físico: {{ $mesaEnTablero->estado_fisico ?: 'Sin estado' }}</p>
            </article>
        @empty<x-ops.empty title="Sin mesas registradas" message="Registra las mesas de esta sede desde Mesas y zonas." icon="map" />@endforelse
    </div>
    <p class="ops-note" data-captain-live-empty hidden>No se encontraron mesas con los filtros seleccionados.</p>
</section>
