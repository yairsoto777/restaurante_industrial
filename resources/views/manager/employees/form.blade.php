@extends('layouts.app')

@php($isEditing = $employee->exists)

@section('title', $isEditing ? 'Editar empleado' : 'Registrar empleado')

@section('content')
<div class="page-heading">
    <div>
        <span class="section-kicker">Gestión de personal</span>
        <h1>{{ $isEditing ? 'Editar empleado' : 'Registrar empleado' }}</h1>
        <p>La cuenta quedará asignada automáticamente a {{ $currentSite->nombre }}.</p>
    </div>
    <a class="button" href="{{ route('manager.employees.index') }}">Volver</a>
</div>

<form
    class="panel form-grid"
    method="POST"
    action="{{ $isEditing ? route('manager.employees.update', $employee) : route('manager.employees.store') }}"
>
    @csrf
    @if($isEditing)
        @method('PUT')
    @endif

    <label>
        Nombre
        <input name="nombre" value="{{ old('nombre', $user->nombre) }}" maxlength="100" autocomplete="given-name" required>
    </label>
    <label>
        Apellido paterno
        <input name="apellido_p" value="{{ old('apellido_p', $user->apellido_p) }}" maxlength="100" autocomplete="family-name" required>
    </label>
    <label>
        Apellido materno
        <input name="apellido_m" value="{{ old('apellido_m', $user->apellido_m) }}" maxlength="100" autocomplete="additional-name">
    </label>
    <label>
        Correo electrónico
        <input type="email" name="correo" value="{{ old('correo', $user->correo) }}" maxlength="150" autocomplete="email" required>
    </label>
    <label>
        Contraseña temporal
        <span style="display:block;position:relative">
            <input type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" style="padding-right:42px" {{ $isEditing ? '' : 'required' }}>
            <button type="button" data-toggle-password="password" aria-label="Mostrar contraseña" aria-pressed="false" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);border:0;background:transparent;padding:6px;line-height:1;cursor:pointer;color:inherit">◉</button>
        </span>
        <small>{{ $isEditing ? 'Déjala vacía para conservar la contraseña actual.' : 'Mínimo 8 caracteres; compártela por un medio seguro.' }}</small>
    </label>
    <label>
        Rol
        <select name="id_rol" required>
            <option value="">Selecciona un rol</option>
            @foreach($roles as $role)
                <option value="{{ $role->id_rol }}" @selected((string) old('id_rol', $selectedRoleId) === (string) $role->id_rol)>
                    {{ \App\Support\EmploymentRoles::displayName($role->nombre) }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="full">
        Sede
        <input value="{{ $currentSite->nombre }}" disabled>
        <small>La sede se determina con tu contexto actual y no puede cambiarse desde este formulario.</small>
    </label>

    <div class="actions full">
        <a class="button" href="{{ route('manager.employees.index') }}">Cancelar</a>
        <button class="button primary" type="submit">{{ $isEditing ? 'Guardar cambios' : 'Registrar empleado' }}</button>
    </div>
</form>
<script>
document.querySelectorAll('[data-toggle-password]').forEach((button) => button.addEventListener('click', (event) => {
    const toggle = event.currentTarget;
    const input = toggle.previousElementSibling;
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    toggle.textContent = visible ? '○' : '◉';
    toggle.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    toggle.setAttribute('aria-pressed', String(visible));
}));
</script>
@endsection
