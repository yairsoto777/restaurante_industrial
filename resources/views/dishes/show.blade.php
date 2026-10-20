@extends('layouts.app')
@section('title', $dish->nombre)
@section('content')
<div class="page-heading"><div><span class="section-kicker">Platillo · {{ $site->nombre }}</span><h1>{{ $dish->nombre }}</h1><p>{{ $dish->category?->nombre }} · {{ $dish->descripcion ?: 'Sin descripción' }}</p></div>@if(auth()->user()->hasRole('Jefe de cocina'))<a class="button primary" href="{{ route('dishes.edit', $dish) }}">Editar</a>@endif</div><section class="panel"><div class="panel-head"><h2>Ingredientes por porción</h2></div><div class="table-wrap"><table class="catalog-table"><thead><tr><th>Insumo</th><th>Cantidad</th><th>Unidad</th></tr></thead><tbody>@forelse($dish->recipeDetails as $detail)<tr><td>{{ $detail->ingredient?->nombre }}</td><td>{{ $detail->cantidad_por_porcion }}</td><td>{{ $detail->ingredient?->unit?->nombre }}</td></tr>@empty<tr><td colspan="3">Receta incompleta.</td></tr>@endforelse</tbody></table></div></section>
@endsection
