@extends('layouts.app')
@section('title', 'Usuarios y permisos')
@section('content')
<div class="page-heading"><div><span class="section-kicker">Administración</span><h1>Usuarios y permisos</h1><p>Gestiona las cuentas, roles y sedes asignadas.</p></div><a class="button primary" href="{{ route('admin.users.create') }}">Crear usuario</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Usuario</th><th>Rol</th><th>Sedes</th><th></th></tr></thead><tbody>
@forelse($users as $user)<tr><td><strong>{{ $user->name }}</strong><small>{{ $user->correo }}</small></td><td><span class="badge">{{ $user->roles->pluck('nombre')->unique()->join(', ') ?: 'Sin rol' }}</span></td><td>{{ $user->sites->pluck('nombre')->join(', ') ?: 'Sin sede' }}</td><td><a href="{{ route('admin.users.edit', $user) }}">Editar</a></td></tr>@empty<tr><td colspan="4">No hay usuarios registrados.</td></tr>@endforelse
</tbody></table></div>{{ $users->links() }}</section>
@endsection
