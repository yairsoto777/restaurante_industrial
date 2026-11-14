@php
    $isEditing = $employee->exists;
    $mainRole = (string) old('primary_role_id', $employeeProfile['primary_role_id'] ?? ($selectedRoleIds[0] ?? ''));
    $checkedRoles = array_map('strval', old('role_ids', $selectedRoleIds ?? []));
@endphp
<div class="mgr-person mgr-profile-header">
    @include('manager.employees.avatar', ['avatarName' => $user->name ?: 'Nuevo empleado', 'avatarProfile' => $employeeProfile, 'avatarSize' => 'large'])
    <div><span class="mgr-section-label">Perfil del empleado</span><h3>{{ $isEditing ? $user->name : 'Nuevo empleado' }}</h3><p><x-icon name="building"/>{{ $currentSite->nombre }}</p><span class="mgr-state {{ $isEditing ? 'approved' : 'pending' }}">{{ $isEditing ? 'Cuenta existente' : 'Alta pendiente' }}</span></div>
</div>
<div class="mgr-fields">
    <label class="mgr-photo-picker"><x-icon name="users"/><span><strong>{{ $employeeProfile['has_photo'] ? 'Actualizar foto' : 'Agregar foto del empleado' }}</strong><small>JPG, PNG o WebP · Máximo 2 MB</small></span><input type="file" name="foto" accept="image/jpeg,image/png,image/webp" data-employee-photo aria-label="Seleccionar foto del empleado"></label>
    <p class="mgr-photo-feedback" data-photo-feedback role="status" hidden></p>
    <section class="mgr-form-section"><h3><span>01</span>Datos de la cuenta</h3>
        @if($isEditing)<details class="mgr-account-details" @if($errors->hasAny(['nombre', 'apellido_p', 'apellido_m', 'password'])) open @endif><summary>Editar nombre y contraseña</summary>@endif
        <div class="mgr-personal-grid">
            <label>Nombre<input name="nombre" value="{{ old('nombre', $user->nombre) }}" maxlength="100" required autocomplete="given-name"></label>
            <label>Apellido paterno<input name="apellido_p" value="{{ old('apellido_p', $user->apellido_p) }}" maxlength="100" required autocomplete="family-name"></label>
            <label>Apellido materno<input name="apellido_m" value="{{ old('apellido_m', $user->apellido_m) }}" maxlength="100" autocomplete="additional-name" {{ $isEditing ? '' : 'required' }}></label>
            <label>{{ $isEditing ? 'Nueva contraseña' : 'Contraseña temporal' }}<input type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" {{ $isEditing ? '' : 'required' }}><small>{{ $isEditing ? 'Déjala vacía para conservar la contraseña actual.' : 'Mínimo 8 caracteres.' }}</small></label>
        </div>
        @if($isEditing)</details>@endif
        <label>Correo de la cuenta<input type="email" name="correo" value="{{ old('correo', $user->correo) }}" maxlength="150" required autocomplete="email"></label>
        <p class="mgr-info"><x-icon name="users"/>Una sola cuenta y contraseña para todos sus roles en la sede.</p>
    </section>
    <section class="mgr-form-section"><h3><span>02</span>Roles en esta sede</h3>
        <label class="mgr-primary-label">Rol principal <span>Principal</span><select name="primary_role_id" required data-primary-role><option value="">Selecciona el rol principal</option>@foreach($roles as $role)
            @php($roleId = is_array($role) ? $role['id'] : $role->id_rol)
            @php($roleName = is_array($role) ? $role['name'] : \App\Support\EmploymentRoles::displayName($role->nombre))
            <option value="{{ $roleId }}" @selected($mainRole === (string) $roleId)>{{ $roleName }}</option>
        @endforeach</select><small>Responsabilidad principal de esta persona. Puede cambiar de rol activo sin cerrar sesión.</small></label>
    </section>
    <fieldset class="mgr-role-options"><legend><x-icon name="layers"/>Roles adicionales</legend><p>Selecciona las responsabilidades secundarias que también desempeñará en esta sede.</p>
        @foreach($roles as $role)
            @php($roleId = is_array($role) ? $role['id'] : $role->id_rol)
            @php($roleName = is_array($role) ? $role['name'] : \App\Support\EmploymentRoles::displayName($role->nombre))
            @php($roleVisual = \App\Support\EmploymentRoles::visual($roleName))
            <label class="mgr-role-option role-color-{{ $roleVisual['tone'] }}" data-role-option="{{ $roleId }}"><input type="checkbox" name="role_ids[]" value="{{ $roleId }}" @checked(in_array((string) $roleId, $checkedRoles, true) && $mainRole !== (string) $roleId) @disabled($mainRole === (string) $roleId)><x-icon :name="$roleVisual['icon']"/><span>{{ $roleName }}</span><small data-role-kind>{{ $mainRole === (string) $roleId ? 'Principal' : 'Adicional' }}</small></label>
        @endforeach
    </fieldset>
    <p class="mgr-role-summary" data-role-summary aria-live="polite">{{ $mainRole ? '1 rol principal' : 'Selecciona un rol principal' }} · Roles adicionales opcionales</p>
    <small>La sede se determina con tu contexto actual y no puede cambiarse desde este formulario.</small>
</div>
<footer class="mgr-form-actions"><a class="mgr-button secondary" href="{{ route('manager.employees.index') }}">Cancelar</a><button class="mgr-button" type="submit">{{ $isEditing ? 'Guardar cambios' : 'Registrar empleado' }}</button></footer>
@once
    @push('scripts')<script src="{{ asset('js/manager-users.js') }}?v=20261113" defer></script>@endpush
@endonce
