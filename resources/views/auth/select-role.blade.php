@extends('layouts.app')
@section('title', 'Seleccionar rol activo')
@section('content')
<div class="role-workspace">
    <header class="role-welcome"><span class="role-welcome-icon"><x-icon name="users"/></span><div><span class="role-eyebrow">NUTRILIADO · Tu espacio de trabajo</span><h1>¡Hola, {{ auth()->user()->nombre }}!</h1><h2>Elige cómo trabajar</h2><p>Selecciona uno de tus roles autorizados. Tu cuenta y contraseña son las mismas en todas tus sedes.</p></div></header>
    <div class="role-card-grid">
    @foreach($assignments as $assignment)
        @php
            $visual = \App\Support\EmploymentRoles::visual($assignment->nombre);
            $active = (int) $assignment->id_rol === (int) auth()->user()->activeRoleId && (int) $assignment->pivot->id_sede === (int) auth()->user()->activeSiteId;
        @endphp
        <article class="role-choice {{ $active ? 'is-active' : '' }}">
            <div class="role-choice-top"><span class="role-choice-icon role-color-{{ $visual['tone'] }}"><x-icon :name="$visual['icon']"/></span>@if($active)<span class="role-active-badge">● Rol activo</span>@endif</div>
            <h3>{{ \App\Support\EmploymentRoles::displayName($assignment->nombre) }}</h3><p>{{ $visual['description'] }}</p>
            <div class="role-choice-site"><x-icon name="building"/><span>{{ $assignment->assignment_site_name }}</span></div>
            <form method="POST" action="{{ route('roles.update') }}">@csrf<input type="hidden" name="id_rol" value="{{ $assignment->id_rol }}"><input type="hidden" name="id_sede" value="{{ $assignment->pivot->id_sede }}"><button type="submit">Entrar con este rol <span>→</span></button></form>
        </article>
    @endforeach
    </div>
    <p class="role-security-note"><x-icon name="users"/>Solo se muestran roles y sedes asignados a tu cuenta.</p>
</div>
@endsection
