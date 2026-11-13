@props(['target', 'label' => 'Buscar en el listado'])
<div class="ops-list-filter" data-ops-filter="{{ $target }}">
    <label><x-icon name="search" /><span>{{ $label }}</span><input type="search" placeholder="Escribe para filtrar…" aria-controls="{{ $target }}"></label>
    <button type="button" class="button" data-ops-clear>Limpiar</button>
    <small role="status" aria-live="polite" data-ops-filter-count></small>
</div>
