<section class="chef-panel" id="mesas-asignadas">
    <div class="chef-panel-heading"><div><span class="chef-kicker">Tu espacio de trabajo</span><h2>Mesas asignadas / Iniciar atención</h2><p>Selecciona el servicio y registra los comensales en la tarjeta de tu mesa.</p></div><a class="button" href="#servicios-ofrecidos">Continuar una atención</a></div>
    <div class="waiter-state-legend"><span><i class="waiter-dot success"></i>Disponible</span><span><i class="waiter-dot occupied"></i>Con atención activa</span><span><i class="waiter-dot warning"></i>Mantenimiento</span><span><i class="waiter-dot neutral"></i>Fuera de servicio / sin estado</span></div>
    <div class="ops-cards waiter-tables-grid" id="directorio-atencion">
        @forelse($tables as $table)
            <article class="ops-record ops-table-card waiter-table" id="mesa-{{ $table->id_mesa }}" data-waiter-physical="{{ $table->estado_fisico }}" data-waiter-occupied="{{ $table->active_attention_id ? 'true' : 'false' }}">
                <div class="ops-record-head"><span class="ops-table-number">Mesa {{ $table->numero }}</span><x-icon name="plates" /></div>
                <x-capitan.table-symbol :number="$table->numero" />
                <p>{{ $table->zona }} · {{ $table->capacidad }} lugares</p>
                <div class="waiter-physical-status"><small class="ops-note">Estado físico</small><br><x-ops.status :value="$table->estado_fisico" :physical="true" /></div>
                <div class="waiter-occupancy"><small class="ops-note">Ocupación</small><br><x-ops.status :value="$table->active_attention_id ? 'Ocupada' : (mb_strtolower(trim((string)$table->estado_fisico)) === 'disponible' ? 'Disponible' : 'Sin atención activa')" /></div>
                @if($table->observaciones)<p class="waiter-table-observations"><strong>Observaciones:</strong> {{ $table->observaciones }}</p>@endif
                @if($table->active_attention_id)
                    <a class="button" href="#servicios-ofrecidos">Atención #{{ $table->active_attention_id }} · Ver seguimiento</a>
                @else
                    <form method="POST" action="{{ route('waiter.attention.store') }}" class="chef-filters waiter-table-attention-form">
                        @csrf
                        <label>Servicio programado<select name="id_servicio" required><option value="">Servicio programado</option>@foreach($services as $service)<option value="{{ $service->id_servicio }}">{{ $service->nombre }} · {{ $service->fecha }}</option>@endforeach</select></label>
                        <input type="hidden" name="id_mesa" value="{{ $table->id_mesa }}">
                        <label>Comensales<input type="number" name="comensales" min="1" max="{{ $table->capacidad }}" placeholder="Comensales" required></label>
                        <button class="chef-button chef-button-primary" type="submit">Iniciar atención</button>
                    </form>
                @endif
            </article>
        @empty
            <x-ops.empty title="No tienes mesas asignadas" message="Las mesas aparecerán cuando el capitán asigne una zona a tu usuario en esta sede." icon="map" />
        @endforelse
    </div>
</section>
