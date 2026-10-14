@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<section class="welcome"><div><p class="eyebrow">{{ now()->translatedFormat('l, d \d\e F') }}</p><h2>Hola, {{ auth()->user()->name }}</h2><p>{{ auth()->user()->isAdministrator() ? 'Gestiona usuarios, roles y sedes desde un solo lugar.' : 'Supervisa las operaciones de tus sedes asignadas.' }}</p></div></section>
<div class="stats">@foreach($stats as $label => $value)<article><strong>{{ $value }}</strong><span>{{ $label }}</span></article>@endforeach</div>
<section class="panel"><div class="panel-head"><div><p class="eyebrow">Accesos</p><h2>Funciones disponibles</h2></div></div>
<div class="quick-grid">
@if(auth()->user()->isAdministrator())
    <a href="{{ route('admin.users.create') }}"><strong>Crear usuario</strong><span>Alta y asignación de rol</span></a><a href="{{ route('admin.users.index') }}"><strong>Administrar usuarios</strong><span>Editar roles y sedes</span></a><a href="{{ route('admin.sites.index') }}"><strong>Administrar sedes</strong><span>Alta y configuración</span></a>
@else
    @foreach(['recepcion'=>'Recepción','inventario'=>'Inventario','servicios'=>'Servicios','menu'=>'Menú','incidencias'=>'Incidencias','reportes'=>'Reportes'] as $key=>$label)<a href="{{ route('manager.module', $key) }}"><strong>{{ $label }}</strong><span>Operación de sedes asignadas</span></a>@endforeach
@endif
</div></section>
@endsection
