@extends('layouts.app')
@section('title', 'Platillos')
@section('content')
@php($canManage = auth()->user()->hasRole('Jefe de cocina'))
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="dishes-title">
        <div class="chef-hero-icon"><x-icon name="dish" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Cocina</span><h1 id="dishes-title">Platillos</h1><p>Consulta el catálogo de la sede y revisa las recetas asociadas a cada platillo.</p></div>
        @if($canManage)<div class="chef-hero-actions"><a class="chef-button chef-button-secondary" href="{{ route('dishes.categories.index') }}"><x-icon name="layers" />Administrar categorías</a><a class="chef-button chef-button-primary" href="{{ route('dishes.create') }}"><x-icon name="dish" />Registrar platillo</a></div>@endif
    </section>

    <section class="chef-stat-grid chef-stat-grid-three" aria-label="Resumen de platillos">
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="dish" /></span><div><strong>{{ $totalCount }}</strong><span>Platillos de la sede</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-green"><x-icon name="check" /></span><div><strong>{{ $withRecipeCount }}</strong><span>Con receta</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="layers" /></span><div><strong>{{ $categories->count() }}</strong><span>Categorías disponibles</span></div></article>
    </section>

    @if($canManage && $categories->isEmpty())<div class="chef-notice chef-notice-warning"><x-icon name="alert" /><span>El catálogo de categorías está vacío. Registra una categoría compartida para poder crear o editar platillos. <a class="chef-link-muted" href="{{ route('dishes.categories.index') }}">Administrar categorías</a></span></div>@endif

    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Catálogo de la sede</span><h2>Platillos registrados</h2></div><span class="chef-count">{{ $dishes->total() }} resultados</span></div>
        <form class="chef-filters" method="GET" action="{{ route('dishes.index') }}">
            <label class="chef-search"><span class="chef-sr-only">Buscar platillo</span><x-icon name="search" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre"></label>
            <label><span class="chef-sr-only">Filtrar por categoría</span><select name="category"><option value="">Todas las categorías</option>@foreach($categories as $category)<option value="{{ $category->id_categoria }}" @selected((string) request('category') === (string) $category->id_categoria)>{{ $category->nombre }}</option>@endforeach</select></label>
            <button class="chef-button chef-button-secondary" type="submit">Filtrar</button>
            @if(request('search') || request('category'))<a class="chef-link-muted" href="{{ route('dishes.index') }}">Limpiar</a>@endif
        </form>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Platillo</th><th>Categoría</th><th>Receta</th><th>Descripción</th><th>Acciones</th></tr></thead><tbody>
            @forelse($dishes as $dish)
                @php($dishImage = $dish->imagen_url ? (\Illuminate\Support\Str::startsWith($dish->imagen_url, ['http://', 'https://', '/']) ? $dish->imagen_url : \Illuminate\Support\Facades\Storage::url($dish->imagen_url)) : null)
                <tr><td><div class="chef-dish-cell"><span class="chef-dish-thumb">@if($dishImage)<img src="{{ $dishImage }}" alt="" loading="lazy">@else<x-icon name="dish" />@endif</span><strong>{{ $dish->nombre }}</strong></div></td><td>{{ $dish->category?->nombre ?: 'Sin categoría' }}</td><td>@if($dish->recipe_details_count > 0)<span class="chef-pill chef-pill-green">{{ $dish->recipe_details_count }} ingredientes</span>@else<span class="chef-pill chef-pill-slate">Sin receta</span>@endif</td><td>{{ $dish->descripcion ?: '—' }}</td><td><div class="chef-row-actions"><a class="chef-button chef-button-small chef-button-secondary" href="{{ route('dishes.show', $dish) }}">Ver detalle</a>@if($canManage)<a class="chef-button chef-button-small chef-button-secondary" href="{{ route('dishes.edit', $dish) }}">Editar</a>@endif</div></td></tr>
            @empty
                <tr><td colspan="5"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="dish" /></span><strong>{{ request('search') || request('category') ? 'No hay platillos con esos filtros' : 'El catálogo de la sede está vacío' }}</strong><p>{{ request('search') || request('category') ? 'Prueba con otra búsqueda o categoría.' : ($categories->isEmpty() ? 'Cuando exista una categoría válida, el Jefe de cocina podrá registrar platillos para esta sede.' : 'Registra el primer platillo para comenzar a preparar los menús.') }}</p>@if($canManage && $categories->isNotEmpty())<a class="chef-button chef-button-primary" href="{{ route('dishes.create') }}">Registrar platillo</a>@elseif($canManage)<a class="chef-button chef-button-secondary" href="{{ route('dishes.categories.index') }}">Administrar categorías</a>@endif</div></td></tr>
            @endforelse
        </tbody></table></div>
        @if($dishes->hasPages())<div class="chef-pagination">{{ $dishes->links() }}</div>@endif
    </section>
</div>
@endsection
