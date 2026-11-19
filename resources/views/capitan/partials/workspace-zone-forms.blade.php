@foreach(collect([(object)['id_zona'=>null,'nombre'=>'']])->concat($zonas) as $zone)
@php
    $formKey=$zone->id_zona ? (string)$zone->id_zona : 'create';
    $restore=old('_zone_form')===$formKey;
    $zoneName=$restore ? old('nombre',$zone->nombre) : $zone->nombre;
    $zoneColor=$restore ? old('color',$zone->color ?? '#17375E') : ($zone->color ?? '#17375E');
    $zoneTables=$zone->id_zona ? $zone->tables : collect();
@endphp
<dialog class="cw-dialog czv-drawer" id="{{ $zone->id_zona ? 'zone-'.$zone->id_zona : 'zone-create' }}" @if($restore && $errors->any()) data-czv-reopen @endif>
    <form method="POST" enctype="multipart/form-data" action="{{ $zone->id_zona ? route('capitan.mesas.zonas.update',$zone->id_zona) : route('capitan.mesas.zonas.store') }}">
        @csrf @if($zone->id_zona)@method('PUT')@endif
        <input type="hidden" name="return_module" value="zonas"><input type="hidden" name="_zone_form" value="{{ $formKey }}">
        <header class="czv-drawer-header"><span class="czv-drawer-icon"><x-icon name="map"/></span><div><small>ORGANIZACIÓN DEL COMEDOR</small><h2>{{ $zone->id_zona ? 'Editar zona' : 'Crear zona' }}</h2></div><button type="button" data-cw-close aria-label="Cerrar panel de zona">×</button></header>
        <div class="czv-form-body">
            @if($restore && $errors->any())<div class="cw-notice red" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            <div class="cw-zone-preview czv-zone-preview" style="--zone:{{ $zoneColor }}"><img class="cw-preview czv-cover-preview" src="{{ \App\Support\ChefWorkspace::image($zone->imagen_url ?? null,'img/ui/captain-dining.webp') }}" alt="Vista previa de fotografía de zona"><div><span>VISTA PREVIA DE LA ZONA</span><h3 data-cw-zone-title>{{ $zoneName ?: 'Nombre de la zona' }}</h3></div></div>
            <section class="czv-form-section"><div class="czv-form-section-title"><span>01</span><h3>Información de la zona</h3></div>
                <label>Nombre de la zona <span class="czv-required" aria-hidden="true">*</span><input name="nombre" required minlength="3" maxlength="100" value="{{ $zoneName }}" placeholder="Ej. Terraza o comedor principal" data-cw-zone-name></label>
                <label>Descripción<textarea name="descripcion" maxlength="2000" placeholder="Describe el área, su ubicación o características…" @disabled(!$zoneMedia)>{{ $restore ? old('descripcion') : ($zone->descripcion ?? '') }}</textarea></label>
                <div class="czv-color-field"><label>Color de identificación<input type="color" name="color" value="{{ $zoneColor }}" data-cw-zone-color @disabled(!$zoneMedia)></label><p>Identifica esta zona en el comedor.<small>El color es independiente del estado de las mesas.</small></p></div>
            </section>
            <section class="czv-form-section"><div class="czv-form-section-title"><span>02</span><h3>Fotografía personalizada</h3></div>
                <label class="czv-upload"><span><x-icon name="plus"/><strong>Selecciona una fotografía</strong></span><small>JPG, JPEG, PNG o WEBP · máximo 4 MB</small><input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" data-cw-preview @disabled(!$zoneMedia)></label><p class="czv-field-note">La imagen se guarda con la zona. Al reemplazarla se conserva el archivo anterior.</p>
            </section>
            <section class="czv-form-section"><div class="czv-form-section-title"><span>03</span><h3>Mesas incluidas</h3></div>
                <div class="czv-tables-info"><x-icon name="layers"/><div><strong>{{ $zoneTables->count() }} mesas asociadas</strong><p>{{ $zoneTables->isNotEmpty() ? 'Mesas '.$zoneTables->pluck('numero')->sort()->implode(', ') : 'Después de guardar la zona puedes registrar sus mesas o asociar las existentes que permitan cambiar de zona.' }}</p></div></div>
                <a class="czv-tables-link" href="{{ $link('mesas') }}">Abrir Gestión de mesas <span aria-hidden="true">↗</span></a>
            </section>
        </div>
        <footer class="czv-drawer-footer"><button type="button" class="cw-btn cw-outline" data-cw-close>Cancelar</button><button class="cw-btn"><x-icon name="check"/>Guardar zona</button></footer>
    </form>
</dialog>
@endforeach
