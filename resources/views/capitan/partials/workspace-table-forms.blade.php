@foreach(collect([(object)['id_mesa'=>null,'numero'=>'','capacidad'=>4,'id_zona'=>null,'estado_fisico'=>'Disponible']])->concat($mesasServicio) as $table)
@php
    $key=$table->id_mesa ? (string)$table->id_mesa : 'create';
    $restore=old('_table_form')===$key;
    $number=$restore?old('numero',$table->numero):$table->numero;
    $capacity=$restore?old('capacidad',$table->capacidad):$table->capacidad;
    $physical=$restore?old('estado_fisico',$table->estado_fisico):$table->estado_fisico;
@endphp
<dialog class="cw-dialog ctv-drawer" id="{{ $table->id_mesa ? 'table-'.$table->id_mesa : 'table-create' }}" @if($restore && $errors->any()) data-ctv-reopen @endif>
    <form method="POST" action="{{ $table->id_mesa ? route('capitan.mesas.mesas.update',$table->id_mesa) : route('capitan.mesas.mesas.store') }}">
        @csrf @if($table->id_mesa)@method('PUT')@endif
        <input type="hidden" name="return_module" value="mesas"><input type="hidden" name="_table_form" value="{{ $key }}">
        <header class="ctv-drawer-header"><span><x-icon name="plates"/></span><div><small>ORGANIZACIÓN DEL COMEDOR</small><h2>{{ $table->id_mesa?'Editar mesa':'Registrar mesa' }}</h2></div><button type="button" data-cw-close aria-label="Cerrar panel de mesa">×</button></header>
        <div class="ctv-form-body">
            @if($restore && $errors->any())<div class="cw-notice red" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div class="ctv-form-preview ctv-{{ $tableTone($physical) }}" data-ctv-preview><div><span class="ctv-eyebrow">VISTA PREVIA</span><h3 data-ctv-preview-name>{{ $number ? 'Mesa '.$number : 'Tu nueva mesa' }}</h3><p><strong data-ctv-preview-capacity>{{ $capacity }}</strong> comensales</p><span class="ctv-state" data-ctv-preview-state>{{ $physical }}</span></div><x-capitan.floor-table :number="$number ?: '＋'" :capacity="$capacity"/></div>
            <section class="ctv-form-section"><h3><span>01</span>Información de la mesa</h3><div class="ctv-form-grid">
                <label>Número {{ !$table->id_mesa?'inicial de mesa':'de mesa' }}<input type="number" min="1" max="9999" name="numero" required value="{{ $number }}" placeholder="Ej. 25"></label>
                <label>Capacidad por mesa<input type="number" min="1" max="100" name="capacidad" required value="{{ $capacity }}" data-cw-capacity></label>
                <label>Zona<select name="id_zona" required><option value="" disabled @selected(!$table->id_zona && !$restore)>Selecciona una zona</option>@foreach($zonas as $zone)<option value="{{ $zone->id_zona }}" @selected((string)($restore?old('id_zona'):$table->id_zona)===(string)$zone->id_zona)>{{ $zone->nombre }}</option>@endforeach</select></label>
                <label>Estado físico<select name="estado_fisico" required>@foreach(['Disponible','Mantenimiento','Fuera de servicio'] as $state)<option @selected($physical===$state)>{{ $state }}</option>@endforeach</select></label>
            </div><p class="ctv-form-note">El estado físico y la ocupación son independientes. La disponibilidad operativa se obtiene de las atenciones.</p></section>
            @if(!$table->id_mesa)<section class="ctv-form-section"><h3><span>02</span>Registro consecutivo</h3><label>Cantidad de mesas<input type="number" name="cantidad" min="1" max="200" value="{{ $restore?old('cantidad',1):1 }}"></label><div class="ctv-consecutive"><x-icon name="layers"/><p data-ctv-consecutive aria-live="polite">Indica el número inicial y la cantidad para consultar el intervalo.</p></div></section>@endif
            <section class="ctv-form-section"><h3><span>{{ $table->id_mesa?'02':'03' }}</span>Información adicional</h3><label>Observaciones<textarea name="observaciones" maxlength="500" placeholder="Características o notas sobre la mesa…">{{ $restore?old('observaciones'):($table->observaciones ?? '') }}</textarea></label><p class="ctv-form-note">Las mesas con historial conservan su zona original.</p></section>
        </div>
        <footer class="ctv-drawer-footer"><button type="button" class="cw-btn cw-outline" data-cw-close>Cancelar</button><button class="cw-btn"><x-icon name="check"/>Guardar</button></footer>
    </form>
</dialog>
@endforeach
