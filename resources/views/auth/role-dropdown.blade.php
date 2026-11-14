@if($roleAssignments->count() > 1)
<details class="active-role-menu"><summary class="button role-switch"><x-icon name="users"/><span>Rol activo: {{ $authenticatedUser->roleLabel() }}</span><x-icon name="chevron"/></summary><div class="active-role-dropdown"><div class="active-role-caption">Cambiar espacio de trabajo</div>
@foreach($roleAssignments as $assignment)
    @php
        $visual = \App\Support\EmploymentRoles::visual($assignment->nombre);
        $active = (int) $assignment->id_rol === (int) $authenticatedUser->activeRoleId && (int) $assignment->pivot->id_sede === (int) $authenticatedUser->activeSiteId;
    @endphp
    <form method="POST" action="{{ route('roles.update') }}">@csrf<input type="hidden" name="id_rol" value="{{ $assignment->id_rol }}"><input type="hidden" name="id_sede" value="{{ $assignment->pivot->id_sede }}"><button type="submit" class="{{ $active ? 'is-active' : '' }}"><span class="role-mini-icon role-color-{{ $visual['tone'] }}"><x-icon :name="$visual['icon']"/></span><span><strong>{{ \App\Support\EmploymentRoles::displayName($assignment->nombre) }}</strong><small>{{ $assignment->assignment_site_name }}</small></span>@if($active)<b aria-label="Rol activo">✓</b>@endif</button></form>
@endforeach
<a href="{{ route('roles.select') }}">Ver todos mis roles →</a></div></details>
@else<span class="button role-switch"><x-icon name="users"/>Rol activo: {{ $authenticatedUser->roleLabel() }}</span>@endif
