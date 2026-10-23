@extends('layouts.app')
@section('title', $dish->exists ? 'Editar platillo' : 'Registrar platillo')
@section('content')
@php
    $canSave = $categories->isNotEmpty();
    $recipeRows = old('recipe', $dish->exists ? $dish->recipeDetails->map(fn ($detail) => ['id_insumo' => $detail->id_insumo, 'cantidad_por_porcion' => $detail->cantidad_por_porcion])->all() : []);
@endphp
<div class="chef-page chef-page-narrow">
    <section class="chef-hero" aria-labelledby="dish-form-title">
        <div class="chef-hero-icon"><x-icon name="dish" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">Platillos · {{ $site->nombre }}</span><h1 id="dish-form-title">{{ $dish->exists ? 'Editar platillo' : 'Registrar platillo' }}</h1><p>Los platillos pertenecen a esta sede y deben tener una categoría existente.</p></div>
        <a class="chef-button chef-button-secondary" href="{{ route('dishes.index') }}">Volver a platillos</a>
    </section>
    @if(! $canSave)<div class="chef-notice chef-notice-warning"><x-icon name="alert" /><span>El catálogo de categorías está vacío y debes seleccionar una existente para guardar este platillo. <a class="chef-link-muted" href="{{ route('dishes.categories.index') }}">Administrar categorías</a></span></div>@endif
    <section class="chef-panel chef-form-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Ficha del platillo</span><h2>{{ $dish->exists ? $dish->nombre : 'Datos básicos' }}</h2></div></div>
        <form method="POST" action="{{ $dish->exists ? route('dishes.update', $dish) : route('dishes.store') }}" class="chef-form" @if($dish->exists) data-confirm="Al guardar se reemplazará la lista actual de ingredientes de la receta por la que aparece aquí. ¿Continuar?" @endif>
            @csrf @if($dish->exists) @method('PUT') @endif
            <label class="chef-field chef-field-wide"><span>Nombre</span><input name="nombre" value="{{ old('nombre', $dish->nombre) }}" required maxlength="150" placeholder="Nombre del platillo">@error('nombre')<small class="chef-field-error">{{ $message }}</small>@enderror</label>
            <label class="chef-field chef-field-wide"><span>Categoría</span><select name="id_categoria" required @disabled(! $canSave)><option value="">Selecciona una categoría</option>@foreach($categories as $category)<option value="{{ $category->id_categoria }}" @selected((string) old('id_categoria', $dish->id_categoria) === (string) $category->id_categoria)>{{ $category->nombre }}</option>@endforeach</select>@error('id_categoria')<small class="chef-field-error">{{ $message }}</small>@enderror</label>
            <label class="chef-field chef-field-wide"><span>Descripción <small>(opcional)</small></span><textarea name="descripcion" maxlength="2000" rows="4" placeholder="Descripción del platillo">{{ old('descripcion', $dish->descripcion) }}</textarea>@error('descripcion')<small class="chef-field-error">{{ $message }}</small>@enderror</label>

            <div class="chef-subsection-heading chef-field-wide"><div><span class="chef-kicker">Receta</span><h3>Ingredientes por porción <small>(opcional)</small></h3></div></div>
            @if($ingredients->isEmpty())<div class="chef-notice chef-field-wide"><x-icon name="box" /><span>No hay insumos con unidad base válida para construir la receta. Puedes guardar el platillo sin receta cuando exista una categoría.</span></div>@else
                <div class="chef-recipe-list chef-field-wide" id="recipe-rows">
                    @foreach($recipeRows as $i => $row)
                        <div class="chef-recipe-row"><label class="chef-field"><span>Insumo</span><select name="recipe[{{ $i }}][id_insumo]"><option value="">Selecciona un insumo</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id_insumo }}" @selected((string) ($row['id_insumo'] ?? '') === (string) $ingredient->id_insumo)>{{ $ingredient->nombre }} · {{ $ingredient->unit?->nombre }}</option>@endforeach</select></label><label class="chef-field"><span>Cantidad por porción · {{ $ingredients->first()?->unit?->nombre }}</span><input type="number" name="recipe[{{ $i }}][cantidad_por_porcion]" value="{{ $row['cantidad_por_porcion'] ?? '' }}" min="0.001" max="999999999.999" step="0.001" placeholder="0.000"></label><button class="chef-button chef-button-danger chef-remove-recipe" type="button">Quitar</button></div>
                    @endforeach
                </div>
                <button class="chef-button chef-button-secondary chef-field-wide" type="button" id="add-recipe"><x-icon name="plus" />Agregar ingrediente</button>
                @error('recipe')<small class="chef-field-error chef-field-wide">{{ $message }}</small>@enderror
            @endif
            @error('recipe.*.id_insumo')<small class="chef-field-error chef-field-wide">{{ $message }}</small>@enderror
            @error('recipe.*.cantidad_por_porcion')<small class="chef-field-error chef-field-wide">{{ $message }}</small>@enderror
            <div class="chef-form-actions chef-field-wide"><a class="chef-button chef-button-secondary" href="{{ route('dishes.index') }}">Cancelar</a><button class="chef-button chef-button-primary" type="submit" @disabled(! $canSave)>{{ $dish->exists ? 'Guardar cambios' : 'Guardar platillo' }}</button></div>
        </form>
    </section>
</div>
@if($ingredients->isNotEmpty())
<template id="recipe-row-template"><div class="chef-recipe-row"><label class="chef-field"><span>Insumo</span><select name="recipe[__INDEX__][id_insumo]" required><option value="">Selecciona un insumo</option>@foreach($ingredients as $ingredient)<option value="{{ $ingredient->id_insumo }}">{{ $ingredient->nombre }} · {{ $ingredient->unit?->nombre }}</option>@endforeach</select></label><label class="chef-field"><span>Cantidad por porción</span><input type="number" name="recipe[__INDEX__][cantidad_por_porcion]" min="0.001" max="999999999.999" step="0.001" required placeholder="0.000"></label><button class="chef-button chef-button-danger chef-remove-recipe" type="button">Quitar</button></div></template>
<script>
let recipeIndex = {{ count($recipeRows) }};
const recipeList = document.getElementById('recipe-rows');
document.getElementById('add-recipe')?.addEventListener('click', () => {
    const template = document.getElementById('recipe-row-template').innerHTML.replaceAll('__INDEX__', String(recipeIndex++));
    recipeList.insertAdjacentHTML('beforeend', template);
});
document.addEventListener('click', (event) => {
    if (event.target.closest('.chef-remove-recipe')) event.target.closest('.chef-recipe-row').remove();
});
</script>
@endif
<script>document.querySelectorAll('form[data-confirm]').forEach((form)=>form.addEventListener('submit',(event)=>{if(!window.confirm(form.dataset.confirm))event.preventDefault()}));</script>
@endsection
