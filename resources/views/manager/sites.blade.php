@extends('layouts.app')
@section('title', 'Mis sedes')
@section('content')<div class="cards">@forelse($sites as $site)<article class="panel"><h2>{{ $site->nombre }}</h2><p>{{ $site->direccion ?: 'Sin dirección registrada' }}</p></article>@empty<section class="panel"><h2>Sin sedes asignadas</h2><p>Solicita a un administrador que asigne al menos una sede a tu cuenta.</p></section>@endforelse</div>@endsection
