@extends('layouts.app')
@section('title', $user->exists ? 'Editar usuario' : 'Crear usuario')
@section('content')
<div class="page-heading"><div><span class="section-kicker">Administración</span><h1>{{ $user->exists ? 'Editar usuario' : 'Crear usuario' }}</h1><p>Configura los datos de acceso, rol y sedes.</p></div></div>
<form class="panel form-grid" method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">@csrf @if($user->exists)@method('PUT')@endif
<label>Nombre<input name="nombre" value="{{ old('nombre', $user->nombre) }}" required></label><label>Apellido paterno<input name="apellido_p" value="{{ old('apellido_p', $user->apellido_p) }}" required></label>
<label>Apellido materno<input name="apellido_m" value="{{ old('apellido_m', $user->apellido_m) }}"></label><label>Correo<input type="email" name="correo" value="{{ old('correo', $user->correo) }}" required></label>
<label>Contraseña<input type="password" name="password" {{ $user->exists ? '' : 'required' }}><small>{{ $user->exists ? 'Déjala vacía para conservarla.' : 'Mínimo 8 caracteres.' }}</small></label><label>Confirmar contraseña<input type="password" name="password_confirmation"></label>
<label class="full">Rol<select name="id_rol" required><option value="">Selecciona un rol</option>@foreach($roles as $role)<option value="{{ $role->id_rol }}" @selected((string) old('id_rol', $user->roles->first()?->id_rol) === (string) $role->id_rol)>{{ $role->nombre }}</option>@endforeach</select></label>
<fieldset class="full"><legend>Sedes asignadas</legend><div class="checks">@foreach($sites as $site)<label class="checkbox"><input type="checkbox" name="sites[]" value="{{ $site->id_sede }}" @checked(in_array($site->id_sede, old('sites', $user->sites->pluck('id_sede')->all())))> {{ $site->nombre }}</label>@endforeach</div></fieldset>
<div class="actions full"><a class="button" href="{{ route('admin.users.index') }}">Cancelar</a><button class="button primary">Guardar usuario</button></div></form>
@endsection
