@extends('layouts.app')

@section('title', 'Empleados')

@section('content')
<div class="page-heading">
    <div>
        <span class="section-kicker">Gestión de personal</span>
        <h1>Empleados de {{ $currentSite->nombre }}</h1>
        <p>Administra las cuentas, puestos y roles internos de tu sede.</p>
    </div>
    <a class="button primary" href="{{ route('manager.employees.create') }}">Registrar empleado</a>
</div>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Puesto</th>
                    <th>Sede</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($employees as $employee)
                @php($assignedRole = $employee->user?->roles->first())
                <tr>
                    <td><strong>{{ $employee->user?->name ?? $employee->nombre }}</strong></td>
                    <td>{{ $employee->user?->correo ?? 'Sin correo' }}</td>
                    <td><span class="badge">{{ $assignedRole ? \App\Support\EmploymentRoles::displayName($assignedRole->nombre) : 'Sin rol' }}</span></td>
                    <td>{{ $employee->position?->nombre ?? 'Sin puesto' }}</td>
                    <td>{{ $currentSite->nombre }}</td>
                    <td><a href="{{ route('manager.employees.edit', $employee) }}">Editar</a></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6"><div class="table-empty">Todavía no hay empleados registrados en esta sede.</div></td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $employees->links() }}
</section>
@endsection
