@php
    $ownAttention=$table->active && (int)$table->active->id_usuario_mesero===(int)auth()->id()
        && (int)$table->active->id_servicio===(int)$service?->id_servicio;
    $cardTone=$table->operativo==='En atención'?'blue':$tone($table->operativo);
    $restriction=$table->active?'Atención no disponible para este usuario o jornada.':
        ($service?->estado!=='En curso'?'La jornada no está en curso.':'Mesa no disponible para atención.');
@endphp
<article class="ww-table-compact ww-{{ $cardTone }}" data-ww-table data-zone="{{ $zone->id_zona }}" data-state="{{ $table->operativo }}" data-number="{{ $table->numero }}">
    <header class="ww-compact-heading">
        <strong>Mesa {{ $table->numero }}</strong>
        <span class="ww-badge ww-{{ $cardTone }}">{{ $table->operativo }}</span>
    </header>
    <div class="ww-compact-body">
        <div class="ww-compact-symbol"><x-waiter.table-symbol :number="$table->numero" :capacity="$table->capacidad"/></div>
        <div class="ww-compact-info">
            <span class="ww-compact-zone" title="{{ $zone->nombre }}">{{ $zone->nombre }}</span>
            <small>Capacidad: {{ $table->capacidad }} lugares</small>
            <span class="ww-compact-guests"><x-icon name="users"/>Comensales: <strong>{{ $table->active?->comensales??0 }} / {{ $table->capacidad }}</strong></span>
        </div>
    </div>
    <footer>
        @if($table->can_open)
            <button class="ww-button ww-primary" type="button" data-ww-open="attention-{{ $table->id_mesa }}"><x-icon name="plus"/>Registrar atención</button>
        @elseif($ownAttention)
            <button class="ww-button ww-primary" type="button" data-ww-open="detail-{{ $table->active->id_atencion }}"><x-icon name="eye"/>Ver atención</button>
        @else
            <button class="ww-button ww-compact-disabled" type="button" disabled aria-label="No disponible. {{ $restriction }}"><x-icon name="alert"/>No disponible</button>
            <small class="ww-compact-hint">{{ $table->active?'Atención en curso':($service?->estado!=='En curso'?'Fuera de jornada':$table->estado_fisico) }}</small>
        @endif
    </footer>
</article>
