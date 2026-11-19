@forelse($zones as $zone)
@php($zoneTables=$tables->where('id_zona',$zone->id_zona))
<section class="ww-zone" style="--zone:{{ preg_match('/^#[0-9a-f]{6}$/i',$zone->color??'')?$zone->color:'#17375e' }}" data-ww-zone="{{ $zone->id_zona }}"><header><img src="{{ $image($zone->imagen_url??null) }}" alt=""><div><h3>{{ $zone->nombre }}</h3><small>{{ $zoneTables->count() }} mesas · {{ $zoneTables->sum('capacidad') }} lugares</small></div><span class="ww-zone-line"></span></header><div class="ww-tables {{ $module==='mesas'?'ww-tables-compact':'' }}">
@forelse($zoneTables as $table)
@if($module==='mesas')
@include('mesero.workspace.table-card-compact')
@else
<article class="ww-table ww-{{ $tone($table->operativo) }}" data-ww-table data-zone="{{ $zone->id_zona }}" data-state="{{ $table->operativo }}" data-number="{{ $table->numero }}"><div class="ww-table-top"><strong>Mesa {{ $table->numero }}</strong><span class="ww-badge ww-{{ $tone($table->operativo) }}">{{ $table->operativo }}</span></div><x-waiter.table-symbol :number="$table->numero" :capacity="$table->capacidad"/><small>{{ $zone->nombre }}</small><div class="ww-table-meta"><span><x-icon name="users"/>{{ $table->capacidad }} lugares</span><span>{{ $table->active?->comensales??0 }} comensales</span></div><small>Estado físico: {{ $table->estado_fisico }}</small>
@if($table->can_open)<button class="ww-button ww-primary" type="button" data-ww-open="attention-{{ $table->id_mesa }}"><x-icon name="plus"/>Registrar atención</button>
@elseif($table->active && (int)$table->active->id_usuario_mesero===(int)auth()->id() && (int)$table->active->id_servicio===(int)$service?->id_servicio)<button class="ww-button" type="button" data-ww-open="detail-{{ $table->active->id_atencion }}"><x-icon name="eye"/>Continuar atención</button>
@else<span class="ww-restriction">{{ $table->active?'Atención en curso; no admite otra apertura.':($service?->estado!=='En curso'?'La jornada no está en curso.':'Mesa no disponible.') }}</span>@endif
</article>
@endif
@empty<p class="ww-empty">No hay mesas registradas en esta zona.</p>@endforelse
</div></section>
@empty<div class="ww-empty"><x-icon name="map"/><h3>Sin mesas asignadas</h3><p>El Capitán configura tus zonas y mesas para cada jornada.</p></div>@endforelse
