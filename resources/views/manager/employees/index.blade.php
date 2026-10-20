@extends('layouts.app')
@section('title', 'Empleados')
@section('content')
<div class="page-heading"><div><span class="section-kicker">Gestión de personal</span><h1>Gestión de personal</h1><p>Administra y organiza los empleados de {{ $currentSite->nombre }}.</p></div><a class="button primary" href="{{ route('manager.employees.create') }}">Registrar empleado</a></div>
<section class="kpi-grid" aria-label="Resumen de personal">
    <article class="kpi-card"><div class="kpi-icon blue"><x-icon name="users"/></div><div class="kpi-copy"><span>Total de empleados</span><strong>{{ $totalEmployees }}</strong><small>En esta sede</small></div></article>
    <article class="kpi-card"><div class="kpi-icon cyan"><x-icon name="utensils"/></div><div class="kpi-copy"><span>Personal de cocina</span><strong>{{ $roles->firstWhere('id', 2)['count'] ?? 0 }}</strong><small>Rol operativo</small></div></article>
    <article class="kpi-card"><div class="kpi-icon green"><x-icon name="layers"/></div><div class="kpi-copy"><span>Roles ocupados</span><strong>{{ $roles->where('count', '>', 0)->count() }}</strong><small>Con personal asignado</small></div></article>
    <article class="kpi-card"><div class="kpi-icon orange"><x-icon name="group"/></div><div class="kpi-copy"><span>Otros trabajadores</span><strong>{{ max(0, $totalEmployees - ($roles->firstWhere('id', 2)['count'] ?? 0)) }}</strong><small>Roles operativos</small></div></article>
</section>
<div class="dashboard-grid">
<section class="dashboard-card"><div class="card-heading"><div><span class="section-kicker">Distribución</span><h2>Empleados por rol</h2></div></div>
@if($totalEmployees)<div style="display:grid;gap:12px">@foreach($roles as $role)<div><div style="display:flex;justify-content:space-between"><span>{{ $role['name'] }}</span><strong>{{ $role['count'] }}</strong></div><div style="height:8px;background:#e8eef5;border-radius:99px;overflow:hidden"><i style="display:block;width:{{ round(($role['count'] / $totalEmployees) * 100) }}%;height:100%;background:#1769aa"></i></div></div>@endforeach</div>@else<div class="inline-empty"><x-icon name="users"/><p>Aún no hay empleados registrados.</p></div>@endif
</section>
</div>
<section class="panel"><form method="GET" action="{{ route('manager.employees.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end"><label style="flex:1;min-width:220px">Buscar empleado<input name="buscar" value="{{ $search }}" placeholder="Nombre o correo"></label><label style="min-width:220px">Filtrar por rol<select name="rol"><option value="">Todos los empleados</option>@foreach($roles as $role)<option value="{{ $role['id'] }}" @selected($roleFilter === $role['id'])>{{ $role['name'] }} ({{ $role['count'] }})</option>@endforeach</select></label><button class="button primary" type="submit">Filtrar</button><a class="button" href="{{ route('manager.employees.index') }}">Limpiar</a></form></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Empleado</th><th>Correo</th><th>Rol</th><th>Sede</th><th>Acciones</th></tr></thead><tbody>
@forelse($employees as $employee) @php($assignedRole = $employee->user?->roles->first())<tr><td><strong>{{ $employee->user?->name ?? $employee->nombre }}</strong></td><td>{{ $employee->user?->correo ?? 'Sin correo' }}</td><td><span class="badge">{{ $assignedRole ? \App\Support\EmploymentRoles::displayName($assignedRole->nombre) : 'Sin rol' }}</span></td><td>{{ $currentSite->nombre }}</td><td><a href="{{ route('manager.employees.edit', $employee) }}">Editar</a></td></tr>
@empty<tr><td colspan="5"><div class="table-empty">No se encontraron empleados con los filtros seleccionados.</div></td></tr>@endforelse
</tbody></table></div>{{ $employees->links() }}</section>
@endsection
