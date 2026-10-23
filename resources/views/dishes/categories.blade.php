@extends('layouts.app')
@section('title', 'Administrar categorías de platillos')
@section('content')
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="dish-categories-title">
        <div class="chef-hero-icon"><x-icon name="layers" /></div>
        <div class="chef-hero-copy">
            <span class="chef-eyebrow">Platillos · Catálogo compartido</span>
            <h1 id="dish-categories-title">Administrar categorías</h1>
            <p>Las categorías no pertenecen a una sede: se comparten entre todos los establecimientos.</p>
        </div>
        <a class="chef-button chef-button-secondary" href="{{ route('dishes.index') }}">Volver a platillos</a>
    </section>

    @if(session('status'))
        <div class="alert success" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error" role="alert">
            <strong>No se pudo completar la operación.</strong>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="chef-panel chef-form-panel">
        <div class="chef-panel-heading">
            <div><span class="chef-kicker">Nueva categoría</span><h2>Agregar al catálogo</h2></div>
        </div>
        <form method="POST" action="{{ route('dishes.categories.store') }}" class="chef-form">
            @csrf
            <label class="chef-field chef-field-wide">
                <span>Nombre</span>
                <input type="text" name="nombre" value="{{ old('nombre') }}" maxlength="100" required placeholder="Nombre de la categoría">
                @error('nombre')<small class="chef-field-error">{{ $message }}</small>@enderror
            </label>
            <div class="chef-form-actions chef-field-wide">
                <button class="chef-button chef-button-primary" type="submit">Registrar categoría</button>
            </div>
        </form>
    </section>

    <section class="chef-panel" aria-labelledby="dish-categories-list-title">
        <div class="chef-panel-heading">
            <div><span class="chef-kicker">Catálogo global</span><h2 id="dish-categories-list-title">Categorías registradas</h2></div>
            <span class="chef-count">{{ $categories->total() }} {{ $categories->total() === 1 ? 'categoría' : 'categorías' }}</span>
        </div>
        <div class="chef-table-wrap">
            <table class="chef-table">
                <caption class="chef-sr-only">Categorías del catálogo compartido y platillos asociados.</caption>
                <thead><tr><th scope="col">Categoría</th><th scope="col">Platillos asociados</th><th scope="col">Acciones</th></tr></thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td><strong>{{ $category->nombre }}</strong></td>
                            <td>{{ $category->dishes_count }}</td>
                            <td>
                                @if((int) $category->dishes_count === 0)
                                    <form method="POST" action="{{ route('dishes.categories.destroy', $category) }}" onsubmit="return confirm('¿Eliminar esta categoría del catálogo compartido?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="chef-button chef-button-small chef-button-danger" type="submit">Eliminar</button>
                                    </form>
                                @else
                                    <span class="chef-pill chef-pill-slate" title="No se puede borrar mientras existan platillos asociados">En uso · {{ $category->dishes_count }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="layers" /></span><strong>Aún no hay categorías</strong><p>Registra una categoría para que el Jefe de cocina pueda seleccionarla al crear o editar un platillo. El catálogo se comparte entre sedes.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())<div class="chef-pagination">{{ $categories->links() }}</div>@endif
    </section>
</div>
@endsection
