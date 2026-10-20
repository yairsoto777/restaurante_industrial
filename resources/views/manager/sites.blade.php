@extends('layouts.app')

@section('title', $isOnboarding ? 'Configura tu comedor' : 'Gestión de sede')

@section('content')
<div class="page-heading">
    <div>
        <span class="section-kicker">{{ $isOnboarding ? 'Configuración inicial' : 'Configuración de empresa' }}</span>
        <h1>{{ $isOnboarding ? 'Configura tu comedor' : 'Gestiona tu comedor' }}</h1>
        <p>{{ $isOnboarding ? 'Completa la información inicial de tu empresa para comenzar a administrar tu operación.' : 'Actualiza la identidad y los datos generales de tu comedor.' }}</p>
    </div>
</div>

@if($isOnboarding)
    <div class="alert warning">Tu cuenta está activa, pero debes completar esta configuración antes de entrar al panel operativo.</div>
@endif

<form class="form-stack" method="POST" action="{{ route('manager.sites.save') }}" enctype="multipart/form-data">
    @csrf
    <section class="panel form-grid">
        <div class="full panel-head"><div><span class="section-kicker">1. Identidad</span><h2>Nombre e imagen</h2></div></div>
        <label>Nombre de la empresa / comedor<input name="nombre" value="{{ old('nombre', $site?->nombre) }}" maxlength="100" required></label>
        <label>Lema o descripción corta<input name="lema" value="{{ old('lema', $site?->lema) }}" maxlength="255"></label>
        <label class="full">
            Logo
            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
            <small>JPG, JPEG, PNG o WEBP. Máximo 2 MB.</small>
        </label>
        @if($site?->logo_url)
            <div class="full"><img src="{{ Storage::url($site->logo_url) }}" alt="Logo actual de {{ $site->nombre }}" style="max-width:180px;max-height:100px;object-fit:contain"></div>
        @endif
    </section>

    <section class="panel form-grid">
        <div class="full panel-head"><div><span class="section-kicker">2. Información general</span><h2>Datos del comedor</h2></div></div>
        <label class="full">Dirección<input name="direccion" value="{{ old('direccion', $site?->direccion) }}" maxlength="255" required></label>
        <label>Teléfono<input type="tel" name="telefono" value="{{ old('telefono', $site?->telefono) }}" maxlength="20" required></label>
        <label>Capacidad<input type="number" name="capacidad" value="{{ old('capacidad', $site?->capacidad) }}" min="1" max="10000000" required></label>
    </section>

    <section class="panel form-grid">
        <div class="full panel-head"><div><span class="section-kicker">3. Apariencia y operación</span><h2>Personalización básica</h2></div></div>
        <label>Color principal<input type="color" name="color" value="{{ old('color', $site?->color ?: '#0b5ed7') }}" required></label>
        <label>
            Tipo de sede
            <select name="id_tipo" required>
                <option value="">Selecciona un tipo</option>
                @foreach($siteTypes as $type)
                    <option value="{{ $type->id_tipo }}" @selected((string) old('id_tipo', $site?->id_tipo) === (string) $type->id_tipo)>{{ $type->nombre }}</option>
                @endforeach
            </select>
        </label>
    </section>

    <div class="actions">
        @unless($isOnboarding)<a class="button" href="{{ route('dashboard') }}">Cancelar</a>@endunless
        <button class="button primary" type="submit">{{ $isOnboarding ? 'Guardar y comenzar' : 'Guardar cambios' }}</button>
    </div>
</form>
@endsection
