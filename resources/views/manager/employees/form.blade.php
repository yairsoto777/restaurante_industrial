@extends('layouts.app')
@section('title', $employee->exists ? 'Editar empleado' : 'Registrar empleado')
@section('content')
<div class="mgr-page"><header class="mgr-heading"><div><h1>{{ $employee->exists ? 'Editar empleado' : 'Registrar empleado' }}</h1><p>La cuenta quedará asignada automáticamente a {{ $currentSite->nombre }}.</p></div></header>
<section class="mgr-card mgr-editor mgr-standalone"><form method="POST" enctype="multipart/form-data" action="{{ $employee->exists ? route('manager.employees.update', $employee) : route('manager.employees.store') }}">@csrf @if($employee->exists) @method('PUT') @endif
@include('manager.employees.fields')
</form></section></div>
@endsection
