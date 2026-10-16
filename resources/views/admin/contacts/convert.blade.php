@extends('layouts.app')

@section('title', 'Convertir contacto a gerente')

@section('content')
@php
    $hasSites = $sites->isNotEmpty();
    $selectedSiteMode = old('site_mode', $hasSites ? 'existing' : 'new');
@endphp
<div class="page-heading">
    <div>
        <span class="section-kicker">Aprobación de servicio</span>
        <h1>Convertir contacto a gerente</h1>
        <p>Crea la cuenta responsable y asígnala a una sede con el rol fijo Gerente de sede.</p>
    </div>
    <a class="button" href="{{ route('admin.contacts.show', $contact) }}">Volver al detalle</a>
</div>

<div class="alert warning">La solicitud quedará aprobada únicamente si se crean correctamente la cuenta y su asignación de sede.</div>

<form class="panel form-grid" method="POST" action="{{ route('admin.contacts.convert.store', $contact) }}" data-site-mode onsubmit="return confirm('¿Confirmas la creación de esta cuenta de Gerente de sede?')">
    @csrf

    <fieldset class="full">
        <legend>Sede</legend>
        <div class="checks">
            @if($hasSites)
                <label class="checkbox">
                    <input type="radio" name="site_mode" value="existing" data-site-mode @checked($selectedSiteMode === 'existing')>
                    Seleccionar una sede existente
                </label>
            @endif
            <label class="checkbox">
                <input type="radio" name="site_mode" value="new" data-site-mode @checked($selectedSiteMode === 'new')>
                Crear una sede nueva
            </label>
        </div>
    </fieldset>

    <fieldset class="full" data-existing-site @if($selectedSiteMode !== 'existing') hidden @endif>
        <legend>Sede existente</legend>
        <label>
            Sede
            <select name="id_sede" data-required-when-existing @if($selectedSiteMode === 'existing') required @endif>
                <option value="">Selecciona una sede</option>
                @foreach($sites as $site)
                    <option value="{{ $site->id_sede }}" @selected((string) old('id_sede') === (string) $site->id_sede)>{{ $site->nombre }}{{ $site->direccion ? ' · '.$site->direccion : '' }}</option>
                @endforeach
            </select>
        </label>
    </fieldset>

    <fieldset class="full form-grid" data-new-site @if($selectedSiteMode !== 'new') hidden @endif>
        <legend>Nueva sede</legend>
        <label>
            Nombre de la sede
            <input name="sede_nombre" value="{{ old('sede_nombre') }}" maxlength="100" data-required-when-new @if($selectedSiteMode === 'new') required @endif>
        </label>
        <label>
            Teléfono
            <input type="tel" name="sede_telefono" value="{{ old('sede_telefono') }}" maxlength="20">
        </label>
        <label class="full">
            Dirección
            <input name="sede_direccion" value="{{ old('sede_direccion') }}" maxlength="255">
        </label>
        <label>
            Capacidad
            <input type="number" name="sede_capacidad" value="{{ old('sede_capacidad') }}" min="0" max="10000000">
        </label>
    </fieldset>

    <div class="full panel-head">
        <div>
            <span class="section-kicker">Cuenta de acceso</span>
            <h2>Gerente de sede</h2>
        </div>
    </div>
    <label>
        Nombre
        <input name="gerente_nombre" value="{{ old('gerente_nombre', $managerDefaults['nombre'] ?? '') }}" maxlength="100" required autocomplete="given-name">
    </label>
    <label>
        Apellido paterno
        <input name="gerente_apellido_p" value="{{ old('gerente_apellido_p', $managerDefaults['apellido_p'] ?? '') }}" maxlength="100" required autocomplete="family-name">
    </label>
    <label>
        Apellido materno
        <input name="gerente_apellido_m" value="{{ old('gerente_apellido_m', $managerDefaults['apellido_m'] ?? '') }}" maxlength="100" autocomplete="additional-name">
    </label>
    <label>
        Correo
        <input type="email" name="gerente_correo" value="{{ old('gerente_correo', $contact->correo) }}" maxlength="150" required autocomplete="email">
    </label>
    <label>
        Contraseña temporal
        <input type="password" name="gerente_password" minlength="8" maxlength="255" required autocomplete="new-password">
        <small>Usa al menos 8 caracteres y compártela por un medio seguro.</small>
    </label>
    <label>
        Rol
        <input value="Gerente de sede" disabled>
        <small>El rol es fijo y no puede modificarse en esta conversión.</small>
    </label>

    <div class="actions full">
        <a class="button" href="{{ route('admin.contacts.show', $contact) }}">Cancelar</a>
        <button class="button primary" type="submit">Aprobar y convertir a gerente</button>
    </div>
</form>
@endsection
