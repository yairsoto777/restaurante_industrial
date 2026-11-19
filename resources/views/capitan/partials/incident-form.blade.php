@php($incidentErrors=$errors->getBag('incident'))
<dialog id="incident-create" class="cw-dialog" @if($incidentErrors->any()) data-cw-reopen @endif>
<form method="POST" action="{{ route('capitan.incidencias.store') }}" enctype="multipart/form-data" data-cw-incident-form>
    @csrf
    <input type="hidden" name="fecha_jornada" value="{{ old('fecha_jornada',$fechaOperacion) }}">
    <div class="cw-section"><h2>Registrar incidencia</h2><button type="button" data-cw-close aria-label="Cerrar">×</button></div>
    <div class="cw-notice red" role="alert" tabindex="-1" data-cw-incident-errors @if(!$incidentErrors->any()) hidden @endif>
        @foreach($incidentErrors->all() as $message)<p>{{ $message }}</p>@endforeach
    </div>
    <label>Descripción<textarea name="descripcion" required minlength="10" maxlength="2000" placeholder="Describe la situación y la atención necesaria">{{ old('descripcion') }}</textarea></label>
    @if(\Illuminate\Support\Facades\Schema::hasColumn('incidencias','id_servicio'))
    <label>Servicio / jornada<select name="id_servicio"><option value="">Sin vincular</option>
        @foreach($servicios as $service)<option value="{{ $service->id_servicio }}" @selected((string)old('id_servicio',$jornada?->id_servicio)===(string)$service->id_servicio)>{{ $service->nombre }} · {{ $service->fecha }} · {{ substr($service->inicio_hora,0,5) }}–{{ substr($service->fin_hora,0,5) }}</option>@endforeach
    </select></label>
    @endif
    @if(\Illuminate\Support\Facades\Schema::hasColumn('incidencias','id_zona'))
    <label>Zona<select name="id_zona" data-cw-incident-zone><option value="">Sin vincular</option>
        @foreach($zonas as $zone)<option value="{{ $zone->id_zona }}" @selected((string)old('id_zona')===(string)$zone->id_zona)>{{ $zone->nombre }}</option>@endforeach
    </select></label>
    @endif
    @if(\Illuminate\Support\Facades\Schema::hasColumn('incidencias','id_mesa'))
    <label>Mesa<select name="id_mesa" data-cw-incident-table @disabled(!old('id_zona'))><option value="">Sin mesa específica</option>
        @foreach($mesasServicio->where('id_zona',old('id_zona')) as $table)<option value="{{ $table->id_mesa }}" @selected((string)old('id_mesa')===(string)$table->id_mesa)>{{ $table->zona }} · Mesa {{ $table->numero }}</option>@endforeach
    </select></label>
    <template data-cw-incident-table-options>
        @foreach($mesasServicio as $table)<option value="{{ $table->id_mesa }}" data-zone-id="{{ $table->id_zona }}">{{ $table->zona }} · Mesa {{ $table->numero }}</option>@endforeach
    </template>
    @endif
    <label>Evidencia · JPG, PNG o WEBP · máximo 4 MB<input type="file" name="evidencia" accept="image/jpeg,image/png,image/webp" data-cw-preview @disabled(!$incidentMedia)></label>
    <img class="cw-preview" alt="Vista previa de evidencia" hidden>
    @if(!$incidentMedia)<p>La evidencia permanente requiere autorizar la migración preparada.</p>@endif
    <button class="cw-btn">Guardar incidencia</button>
</form>
</dialog>
<script src="{{ asset('js/captain-incidents.js') }}?v={{ filemtime(public_path('js/captain-incidents.js')) }}" defer></script>
