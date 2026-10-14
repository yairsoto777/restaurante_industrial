@extends('layouts.app')
@section('title', $user->exists ? 'Editar usuario' : 'Crear usuario')
@section('content')
<form class="panel form-grid" method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">@csrf @if($user->exists)@method('PUT')@endif
<div class="panel-head full"><div><p class="eyebrow">Cuenta y permisos</p><h2>{{ $user->exists ? 'Actualizar información' : 'Nuevo usuario' }}</h2></div></div>
<label>Nombre<input name="name" value="{{ old('name', $user->name) }}" required></label><label>Correo<input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
<label>Contraseña<input type="password" name="password" {{ $user->exists ? '' : 'required' }}><small>{{ $user->exists ? 'Déjala vacía para conservarla.' : 'Mínimo 8 caracteres.' }}</small></label><label>Confirmar contraseña<input type="password" name="password_confirmation"></label>
<label>Rol<select name="role" required>@foreach($roles as $role)<option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
<label>Estado<select name="active"><option value="1" @selected(old('active', $user->active ?? true))>Activo</option><option value="0" @selected(!old('active', $user->active ?? true))>Inactivo</option></select></label>
<fieldset class="full"><legend>Sedes asignadas</legend><p>Estas sedes determinan el alcance operativo del gerente.</p><div class="checks">@forelse($sites as $site)<label class="checkbox"><input type="checkbox" name="sites[]" value="{{ $site->id }}" @checked(in_array($site->id, old('sites', $user->sites->pluck('id')->all())))> {{ $site->name }}</label>@empty<span>Primero crea una sede.</span>@endforelse</div></fieldset>
<div class="actions full"><a class="button" href="{{ route('admin.users.index') }}">Cancelar</a><button class="button primary">Guardar usuario</button></div></form>
@endsection
