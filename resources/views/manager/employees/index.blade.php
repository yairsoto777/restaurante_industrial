@extends('layouts.app')
@section('title', 'Gestión de usuarios y roles')
@section('content')
<div class="mgr-page mgr-users">
<header class="mgr-heading"><x-icon name="users"/><div><h1>Gestión de usuarios y roles</h1><p>Administra las cuentas de tus empleados y asígnales uno o más roles en esta sede.</p></div></header>
<div class="mgr-user-layout"><div>
<div class="mgr-kpis">
@foreach([['users', $totalEmployees, 'Empleados registrados', 'cyan'], ['layers', $assignmentCount, 'Roles asignados', 'purple'], ['group', $multiRoleCount, 'Cuentas multirrol', 'green'], ['building', $roles->where('count', '>', 0)->count(), 'Roles con personal', 'orange']] as [$icon, $value, $label, $tone])
<article class="mgr-card mgr-kpi {{ $tone }}"><span class="mgr-kpi-watermark" aria-hidden="true"><x-icon :name="$icon"/></span><span class="mgr-kpi-icon"><x-icon :name="$icon"/></span><div><strong>{{ $value }}</strong><span>{{ $label }}</span></div></article>
@endforeach
</div>
<section class="mgr-card mgr-users-table"><div class="mgr-card-heading"><h2><x-icon name="users"/>Usuarios de la sede</h2><a class="mgr-button" href="{{ route('manager.employees.index', ['nuevo' => 1]) }}">+ Nuevo empleado</a></div>
<form class="mgr-filters" method="GET" action="{{ route('manager.employees.index') }}"><label><span class="sr-only">Buscar por nombre o correo</span><input type="search" name="buscar" value="{{ $search }}" placeholder="Buscar por nombre o correo…" maxlength="150"></label><label><span class="sr-only">Filtrar por rol</span><select name="rol"><option value="">Todos los roles</option>@foreach($roles as $role)<option value="{{ $role['id'] }}" @selected($roleFilter === $role['id'])>{{ $role['name'] }}</option>@endforeach</select></label><button class="mgr-button" type="submit">Filtrar</button><a href="{{ route('manager.employees.index') }}">Limpiar</a></form>
<div class="table-wrap"><table class="mgr-table"><thead><tr><th>Nombre</th><th>Correo</th><th>Roles asignados</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
@forelse($employees as $employee)
@php
    $canEdit = $employee->user && !$employee->user->isManager() && !$employee->user->canAdminister() && $employee->user->id_usuario !== auth()->id();
    $profile = $employeeProfiles[$employee->getKey()];
    $assignedRoles = ($employee->user?->roles ?? collect())->sortBy(fn ($role) => (int) $role->id_rol === $profile['primary_role_id'] ? 0 : 1);
    $stateTone = ! $employee->user || $employee->user->getAttribute('activo') === 0 ? 'alert' : ($assignedRoles->isEmpty() ? 'pending' : 'approved');
    $stateLabel = $stateTone === 'alert' ? 'Revisar cuenta' : ($assignedRoles->isEmpty() ? 'Sin acceso' : 'Asignado');
@endphp
<tr class="{{ (int) $editingEmployee?->id_empleado === (int) $employee->id_empleado ? 'selected' : '' }}">
    <td><div class="mgr-name">@include('manager.employees.avatar', ['avatarName' => $employee->user?->name ?? $employee->nombre, 'avatarProfile' => $profile, 'avatarSize' => 'small'])<div><strong>{{ $employee->user?->name ?? $employee->nombre }}</strong><small>Empleado de {{ $currentSite->nombre }}</small></div></div></td>
    <td><span class="mgr-employee-email">{{ $employee->user?->correo ?? 'Sin correo' }}</span></td>
    <td><div class="mgr-role-list">@forelse($assignedRoles as $role)
        @php($roleVisual = \App\Support\EmploymentRoles::visual($role->nombre))
        <span class="mgr-role role-color-{{ $roleVisual['tone'] }} {{ (int) $role->id_rol === $profile['primary_role_id'] ? 'is-primary' : 'is-secondary' }}"><x-icon :name="$roleVisual['icon']"/>{{ \App\Support\EmploymentRoles::displayName($role->nombre) }}<small>{{ (int) $role->id_rol === $profile['primary_role_id'] ? 'Principal' : 'Secundario' }}</small></span>@empty<span class="mgr-note pending">Pendiente de asignación</span>@endforelse</div></td>
    <td><span class="mgr-state {{ $stateTone }}"><i></i>{{ $stateLabel }}</span></td><td>@if($canEdit)<a class="mgr-edit" href="{{ route('manager.employees.index', ['editar' => $employee->id_empleado]) }}" aria-label="Editar {{ $employee->nombre }}">✎</a>@else<span>Protegido</span>@endif</td>
</tr>
@empty<tr><td colspan="5" class="mgr-empty">No se encontraron empleados con los filtros seleccionados.</td></tr>@endforelse
</tbody></table></div><div class="mgr-table-footer"><p><span class="mgr-legend-dot approved"></span>Asignado <span class="mgr-legend-dot pending"></span>Pendiente <span class="mgr-legend-dot alert"></span>Requiere atención</p><span>{{ $employees->total() }} empleados en el listado</span></div><div class="mgr-pagination">{{ $employees->links() }}</div>
</section></div>
@if($editingEmployee || request()->boolean('nuevo'))
<aside class="mgr-card mgr-editor" aria-labelledby="editor-title"><div class="mgr-editor-heading"><h2 id="editor-title">✎ {{ $editingEmployee ? 'Editar usuario' : 'Nuevo empleado' }}</h2><a href="{{ route('manager.employees.index') }}" aria-label="Cerrar panel">×</a></div>
<form method="POST" enctype="multipart/form-data" action="{{ $editingEmployee ? route('manager.employees.update', $editingEmployee) : route('manager.employees.store') }}">@csrf @if($editingEmployee) @method('PUT') @endif
@include('manager.employees.fields', ['employee' => $editingEmployee ?? new \App\Models\Employee, 'user' => $editingUser])
</form>
@if($editingEmployee)
<details class="mgr-revoke"><summary>Administrar retirada de accesos</summary><p>Retirar acceso conserva la cuenta, los datos y los registros históricos.</p><form method="POST" action="{{ route('manager.employees.destroy', $editingEmployee) }}" onsubmit="return confirm('¿Retirar los accesos seleccionados de esta sede? La cuenta y el historial se conservarán.')">@csrf @method('DELETE')<label>Rol a retirar<select name="id_rol"><option value="">Todos los roles de esta sede</option>@foreach($editingUser->roles()->where('usuario_sede.id_sede', $currentSite->id_sede)->get() as $role)<option value="{{ $role->id_rol }}">{{ \App\Support\EmploymentRoles::displayName($role->nombre) }}</option>@endforeach</select></label><button class="button danger" type="submit">Retirar acceso</button></form></details>
@endif
</aside>
@endif
</div></div>
@endsection
