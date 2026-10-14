@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<section class="panel"><div class="panel-head"><div><p class="eyebrow">Control de acceso</p><h2>Usuarios registrados</h2></div><a class="button primary" href="{{ route('admin.users.create') }}">Crear usuario</a></div>
<div class="table-wrap"><table><thead><tr><th>Usuario</th><th>Rol</th><th>Sedes</th><th>Estado</th><th></th></tr></thead><tbody>
@forelse($users as $user)<tr><td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td><td><span class="badge">{{ $user->role->label() }}</span></td><td>{{ $user->sites->pluck('name')->join(', ') ?: '—' }}</td><td><span class="status {{ $user->active ? 'on' : 'off' }}">{{ $user->active ? 'Activo' : 'Inactivo' }}</span></td><td><a href="{{ route('admin.users.edit', $user) }}">Editar</a></td></tr>@empty<tr><td colspan="5">No hay usuarios registrados.</td></tr>@endforelse
</tbody></table></div>{{ $users->links() }}</section>
@endsection
