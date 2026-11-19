<div class="cw-media" data-cw-upload>
    <img src="{{ $current ?? asset('img/ui/warehouse-food.jpg') }}" alt="{{ $label }}" data-cw-preview data-original="{{ $current ?? asset('img/ui/warehouse-food.jpg') }}">
    <label>{{ $label }} <small>(opcional)</small><input type="file" @if($supported) name="{{ $field }}" @endif @disabled($disabled ?? false) accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-cw-file></label>
    <small>JPG, JPEG, PNG o WEBP · Máximo 4 MB. Sin archivo nuevo se conserva la imagen actual.</small>
    @unless($supported)<p class="cw-media-pending">Vista previa local: el guardado de esta imagen requiere habilitar el esquema pendiente de autorización.</p>@endunless
    <button class="cw-button ghost small" type="button" data-cw-restore>Conservar imagen actual</button><p class="cw-media-status" role="status" aria-live="polite"></p>
</div>
