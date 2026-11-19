<form method="POST" enctype="multipart/form-data" action="{{ isset($supplierRecord) ? route('orders.suppliers.update',$supplierRecord->id_proveedor) : route('orders.suppliers.store') }}" class="wh-form" data-supplier-form>
    @csrf @isset($supplierRecord) @method('PUT') @endisset
    <input type="hidden" name="form_supplier" value="{{ $supplierRecord->id_proveedor ?? 'new' }}">
    <fieldset class="wh-supplier-visual"><legend>Identidad visual del proveedor</legend><div class="wh-supplier-visual-grid">
    <div class="wh-supplier-upload" data-supplier-upload>
        @include('almacen.workspace.supplier-image', ['imageSupplier'=>$supplierRecord ?? null, 'imagePreview'=>true])
        <div><label><span>Logotipo <small>(opcional)</small></span><input type="file" @if($supplierImageAvailable) name="imagen" @endif accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-supplier-file aria-describedby="supplier-image-help-{{ $supplierRecord->id_proveedor ?? 'new' }}"></label>
        <p id="supplier-image-help-{{ $supplierRecord->id_proveedor ?? 'new' }}" class="wh-note">JPG, JPEG, PNG o WEBP. Máximo 4 MB.</p>
        @if(!$supplierImageAvailable)<p class="wh-supplier-preview-notice">Vista previa disponible. La imagen aún no se puede guardar; puedes registrar los datos del proveedor.</p>@endif
        @isset($supplierRecord)<button type="button" class="wh-button ghost" data-supplier-restore>Conservar imagen actual</button>@else<button type="button" class="wh-button ghost" data-supplier-restore>Quitar selección</button>@endisset
        <p class="wh-supplier-image-status" data-supplier-image-status role="status" aria-live="polite"></p>
        </div>
    </div>
    <div class="wh-supplier-upload wh-supplier-cover-upload" data-supplier-upload>
        @php($currentCover = \App\Support\WarehouseSupplierImage::coverUrl($supplierRecord ?? null))
        <span class="wh-supplier-cover-preview"><img src="{{ $currentCover }}" alt="Portada del proveedor" data-supplier-image data-current-src="{{ $currentCover }}" onerror="this.hidden=true"></span>
        <div><label><span>Portada <small>(opcional)</small></span><input type="file" @if($supplierCoverAvailable) name="portada" @endif accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-supplier-file aria-describedby="supplier-cover-help-{{ $supplierRecord->id_proveedor ?? 'new' }}"></label>
        <p id="supplier-cover-help-{{ $supplierRecord->id_proveedor ?? 'new' }}" class="wh-note">JPG, JPEG, PNG o WEBP. Máximo 4 MB.</p>
        @if(!$supplierCoverAvailable)<p class="wh-supplier-preview-notice">Vista previa de portada. Su guardado está pendiente de habilitación.</p>@endif
        <button type="button" class="wh-button ghost" data-supplier-restore>{{ isset($supplierRecord) ? 'Conservar portada actual' : 'Quitar selección' }}</button>
        <p class="wh-supplier-image-status" data-supplier-image-status role="status" aria-live="polite"></p></div>
    </div>
    </div><p class="wh-note wh-supplier-visual-help">Si no seleccionas nuevas imágenes, se conservan las actuales. Sin portada propia se utiliza una fotografía de abastecimiento.</p></fieldset>
    <label>Nombre de la empresa<input name="nombre" required maxlength="150" value="{{ old('form_supplier') === (string)($supplierRecord->id_proveedor ?? 'new') ? old('nombre') : ($supplierRecord->nombre ?? '') }}" autocomplete="organization"></label>
    <div class="wh-form-grid"><label>Teléfono<input type="tel" name="telefono" maxlength="20" value="{{ old('form_supplier') === (string)($supplierRecord->id_proveedor ?? 'new') ? old('telefono') : ($supplierRecord->telefono ?? '') }}" autocomplete="tel"></label><label>Correo electrónico<input type="email" name="correo" maxlength="150" value="{{ old('form_supplier') === (string)($supplierRecord->id_proveedor ?? 'new') ? old('correo') : ($supplierRecord->correo ?? '') }}" autocomplete="email"></label></div>
    <p class="wh-note">El catálogo de proveedores es compartido. Los pedidos, recepciones e incidencias se consultan únicamente para {{ $site->nombre }}.</p>
    <footer class="wh-actions"><button type="button" class="wh-button secondary" data-close-dialog>Cancelar</button><button class="wh-button" type="submit">{{ isset($supplierRecord) ? 'Guardar cambios' : 'Registrar proveedor' }}</button></footer>
</form>
