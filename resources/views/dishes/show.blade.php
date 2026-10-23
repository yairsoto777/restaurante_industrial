@extends('layouts.app')
@section('title', $dish->nombre)
@section('content')
@php($canManage = auth()->user()->hasRole('Jefe de cocina'))
@php($dishImage = $dish->imagen_url ? (\Illuminate\Support\Str::startsWith($dish->imagen_url, ['http://', 'https://', '/']) ? $dish->imagen_url : \Illuminate\Support\Facades\Storage::url($dish->imagen_url)) : null)
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="dish-title">
        <div class="chef-hero-icon chef-dish-hero-image">@if($dishImage)<img src="{{ $dishImage }}" alt="Imagen de {{ $dish->nombre }}">@else<x-icon name="dish" />@endif</div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · {{ $dish->category?->nombre ?: 'Platillo' }}</span><h1 id="dish-title">{{ $dish->nombre }}</h1><p>{{ $dish->descripcion ?: 'Sin descripción registrada.' }}</p></div>
        <div class="chef-hero-actions"><a class="chef-button chef-button-secondary" href="{{ route('dishes.index') }}">Volver al catálogo</a>@if($canManage)<a class="chef-button chef-button-primary" href="{{ route('dishes.edit', $dish) }}">Editar platillo</a>@endif</div>
    </section>
    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Composición</span><h2>Ingredientes por porción</h2></div><span class="chef-count">{{ $dish->recipeDetails->count() }} ingredientes</span></div>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Insumo</th><th>Cantidad por porción</th><th>Unidad base</th></tr></thead><tbody>@forelse($dish->recipeDetails as $detail)<tr><td><strong>{{ $detail->ingredient?->nombre ?: 'Insumo no disponible' }}</strong></td><td>{{ $detail->cantidad_por_porcion }}</td><td>{{ $detail->ingredient?->unit?->nombre ?: 'Unidad no configurada' }}</td></tr>@empty<tr><td colspan="3"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="layers" /></span><strong>Este platillo no tiene receta registrada</strong><p>La receta se puede agregar desde “Editar platillo” cuando existan insumos con unidad base.</p></div></td></tr>@endforelse</tbody></table></div>
    </section>
</div>
@endsection
