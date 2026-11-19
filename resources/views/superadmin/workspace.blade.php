@extends('layouts.app')
@php
    $titles=['panel'=>'Panel principal','solicitudes'=>'Solicitudes de empresas','clientes'=>'Gestión de clientes','sedes'=>'Gestión de sedes','usuarios'=>'Usuarios del sistema','configuracion'=>'Configuración general'];
    $icons=['panel'=>'chart','solicitudes'=>'building','clientes'=>'group','sedes'=>'map','usuarios'=>'users','configuracion'=>'layers'];
    $descriptions=['panel'=>'Una visión integral de tu crecimiento y administración comercial.','solicitudes'=>'Conecta nuevas empresas con una operación alimentaria de excelencia.','clientes'=>'Identidad, relaciones comerciales y seguimiento en un solo lugar.','sedes'=>'Organiza la presencia de cada empresa y sus responsables.','usuarios'=>'Visibilidad de cuentas, asignaciones y accesos de toda la plataforma.','configuracion'=>'Consulta las políticas y herramientas disponibles para tu administración.'];
    $W=\App\Support\SuperadminWorkspace::class;
    $pendingUsers=$users->filter(fn($u)=>$u->mustChangePassword());
    $linkedSites=$sites->filter(fn($s)=>$W::siteState($s)==='Vinculada');
@endphp
@section('title', $titles[$module])
@section('content')
<div class="sa-workspace" data-module="{{ $module }}" data-reopen="{{ $errors->any() ? old('_sa_drawer','') : '' }}">
    <header class="sa-page-heading"><span class="sa-heading-icon"><x-icon :name="$icons[$module]"/></span><div><span class="sa-eyebrow">NUTRILIADO / ADMINISTRACIÓN GENERAL</span><h1>{{ $titles[$module] }}</h1><p>{{ $descriptions[$module] }}</p></div><span class="sa-heading-date"><x-icon name="calendar"/>{{ now()->locale('es')->translatedFormat('d M Y') }}</span></header>
    @if(session('status'))<div class="sa-alert sa-success" role="status"><x-icon name="check"/>{{ session('status') }}</div>@endif
    @if($errors->any())<div class="sa-alert sa-error" role="alert"><x-icon name="alert"/><div><strong>Revisa los datos antes de continuar.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
    @include('superadmin.sections.'.$module)
</div>
@endsection
