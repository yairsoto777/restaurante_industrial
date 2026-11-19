@extends('almacen.workspace.layout')

@section('title', 'Ubicaciones de almacén')
@section('banner', 'inventory')
@section('banner-title', 'Ubicaciones de ' . $site->nombre)
@section('banner-copy', 'Selecciona una ubicación activa al confirmar una recepción de la sede.')

@section('workspace')
    @if(!$available)
        <div class="wh-message orange"><p>El catálogo requiere la actualización del esquema de ubicaciones.</p></div>
    @else
        <section class="wh-card">
            <h2>Registrar ubicación</h2>
            <form class="wh-form" method="POST" action="{{ route('warehouse.locations.store') }}">
                @csrf
                <label>Nombre <input name="nombre" value="{{ old('nombre') }}" maxlength="100" required></label>
                <label>Código
                    <input name="codigo" value="{{ old('codigo') }}" maxlength="40" pattern="[A-Za-z0-9_\-]+" required>
                    <small>Usa letras, números, guion o guion bajo.</small>
                </label>
                <label>Tipo <input name="tipo" value="{{ old('tipo') }}" maxlength="60" placeholder="Ej. Refrigeración" required></label>
                <label>Estado
                    <select name="estado" required>
                        <option @selected(old('estado', 'Activa') === 'Activa')>Activa</option>
                        <option @selected(old('estado') === 'Inactiva')>Inactiva</option>
                    </select>
                </label>
                <button class="wh-button green" type="submit">Registrar ubicación</button>
            </form>
        </section>
        <section class="wh-card">
            <h2>Ubicaciones registradas</h2>
            <div class="wh-table-wrap">
                <table class="wh-table">
                    <thead><tr><th>Nombre</th><th>Código</th><th>Tipo</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse($locations as $location)
                            <tr><td>{{ $location->nombre }}</td><td>{{ $location->codigo ?? '—' }}</td><td>{{ $location->tipo ?? '—' }}</td><td>{{ $location->estado }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="wh-empty">Aún no hay ubicaciones registradas en esta sede.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
