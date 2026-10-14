@extends('layouts.app')
@section('title', 'Selección de sede')
@section('content')
<div class="page-heading">
    <div><span class="section-kicker">Contexto operativo</span><h1>Selecciona una sede</h1><p>La información del panel se filtrará con la sede activa.</p></div>
</div>
<div class="site-choice-grid">
    @forelse($sites as $site)
        <form method="POST" action="{{ route('sites.select.update') }}" class="site-choice {{ $currentSite?->id_sede === $site->id_sede ? 'is-current' : '' }}">
            @csrf
            <input type="hidden" name="id_sede" value="{{ $site->id_sede }}">
            <div class="site-choice-icon"><x-icon name="building" /></div>
            <div><strong>{{ $site->nombre }}</strong><span>{{ $site->direccion ?: 'Sin dirección registrada' }}</span><small>Capacidad: {{ $site->capacidad ?? 'Sin datos' }}</small></div>
            <button type="submit" class="button {{ $currentSite?->id_sede === $site->id_sede ? '' : 'primary' }}">{{ $currentSite?->id_sede === $site->id_sede ? 'Sede actual' : 'Seleccionar' }}</button>
        </form>
    @empty
        <div class="empty-panel"><x-icon name="building" /><h2>Sin sedes disponibles</h2><p>No tienes sedes asignadas actualmente.</p></div>
    @endforelse
</div>
@endsection
