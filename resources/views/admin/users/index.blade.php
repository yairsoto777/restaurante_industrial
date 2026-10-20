@extends('layouts.app')
@section('title', 'Clientes / Gerentes')
@section('content')
<div class="page-heading"><div><span class="section-kicker">Administración global</span><h1>Clientes / Gerentes</h1><p>Administra gerentes y cuentas globales. Los empleados corresponden a cada gerente.</p></div><a class="button primary" href="{{ route('admin.users.create') }}">Crear gerente</a></div>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Sede</th><th>Acciones</th></tr></thead><tbody>
@forelse($users as $user)
@php($roleNames = $user->roles->pluck('nombre')->unique())
<tr><td><strong>{{ $user->name }}</strong></td><td>{{ $user->correo }}</td><td><span class="badge">{{ $roleNames->join(', ') ?: 'Sin rol' }}</span></td><td>{{ $user->sites->pluck('nombre')->join(', ') ?: 'Sin sede' }}</td><td><div class="actions">
@if(!$user->isSuperAdministrator() || auth()->user()->isSuperAdministrator())<a href="{{ route('admin.users.edit', $user) }}">Editar</a>@endif
@if($roleNames->contains('Gerente'))<form method="POST" action="{{ route('admin.users.promote', $user) }}">@csrf @method('PATCH')<button type="submit">Hacer Administrador</button></form>@endif
@if($roleNames->contains('Administrador') && !auth()->user()->is($user))<form method="POST" action="{{ route('admin.users.demote', $user) }}">@csrf @method('PATCH')<button type="submit">Cambiar a Gerente</button></form>@endif
@if((!$user->isSuperAdministrator() || auth()->user()->isSuperAdministrator()) && !auth()->user()->is($user))<form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('¿Eliminar este usuario?')">@csrf @method('DELETE')<button type="submit">Eliminar</button></form>@endif
</div></td></tr>@empty<tr><td colspan="5">No hay usuarios registrados.</td></tr>@endforelse
</tbody></table></div>{{ $users->links() }}</section>
@endsection
