@extends('layouts.app')

@section('title', 'Solicitar puesto')

@section('content')
<div class="page-heading">
    <div><span class="section-kicker">Solicitud de empleo</span><h1>Solicitar puesto</h1><p>Selecciona una sede y el puesto que deseas solicitar.</p></div>
    <a class="button" href="{{ route('user.applications.index') }}">Volver a mis solicitudes</a>
</div>

@if($sites->isEmpty() || $roles->isEmpty())
    <div class="alert warning">No hay sedes o puestos disponibles para enviar una solicitud en este momento.</div>
@endif

<section class="panel form-panel">
    <div class="panel-head"><div><span class="section-kicker">Datos de la solicitud</span><h2>Información del puesto</h2></div></div>
    <form method="POST" action="{{ route('user.applications.store') }}" class="form-grid employment-form">
        @csrf
        <label>Usuario<input value="{{ auth()->user()->name }}" disabled><small>La solicitud quedará vinculada automáticamente a tu cuenta.</small></label>
        <label>Fecha de solicitud<input value="{{ now()->format('d/m/Y H:i') }}" disabled><small>La fecha se registrará automáticamente al enviar.</small></label>
        <label>Sede solicitada
            <select name="id_sede" required>
                <option value="">Selecciona una sede</option>
                @foreach($sites as $site)<option value="{{ $site->id_sede }}" @selected((string) old('id_sede') === (string) $site->id_sede)>{{ $site->nombre }}</option>@endforeach
            </select>
        </label>
        <label>Rol solicitado
            <select name="id_rol_solicitado" required>
                <option value="">Selecciona un puesto</option>
                @foreach($roles as $role)<option value="{{ $role->id_rol }}" @selected((string) old('id_rol_solicitado', request('id_rol')) === (string) $role->id_rol)>{{ \App\Support\EmploymentRoles::displayName($role->nombre) }}</option>@endforeach
            </select>
        </label>
        <label class="full">Mensaje, motivo u observaciones
            <textarea name="mensaje" rows="6" maxlength="1000" required placeholder="Cuéntanos brevemente por qué te interesa este puesto y qué experiencia puedes aportar.">{{ old('mensaje') }}</textarea>
            <small>Máximo 1,000 caracteres.</small>
        </label>
        <div class="actions full">
            <a class="button" href="{{ route('user.applications.index') }}">Cancelar</a>
            <button class="button primary" type="submit" @disabled($sites->isEmpty() || $roles->isEmpty())>Enviar solicitud</button>
        </div>
    </form>
</section>
@endsection
