@php
    $imageUrl = \App\Support\WarehouseSupplierImage::url($imageSupplier ?? null);
    $imageName = $imageSupplier->nombre ?? 'Nuevo proveedor';
@endphp
<span class="wh-supplier-avatar" @isset($imagePreview) data-supplier-preview @endisset>
    <span data-supplier-initials aria-label="{{ $imageName }}">{{ \App\Support\WarehouseSupplierImage::initials($imageName) }}</span>
    <img @if($imageUrl) src="{{ $imageUrl }}" @else hidden @endif alt="Logo o imagen de {{ $imageName }}" data-supplier-image data-current-src="{{ $imageUrl }}" onerror="this.hidden=true" loading="lazy">
</span>
