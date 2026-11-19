@extends('layouts.app')
@php
 $titles=['panel'=>['home','Panel principal','Gestiona tus mesas y atiende a los comensales del servicio activo.'],'servicios'=>['utensils','Mis servicios','Tus jornadas, zonas y mesas de atención en un solo lugar.'],'mesas'=>['layers','Mis mesas','Atiende y supervisa las mesas asignadas durante tu jornada.'],'atenciones'=>['activity','Atenciones activas','Da seguimiento a cada comensal y a los servicios que ofreces.'],'menu'=>['dish','Menú del servicio','Consulta los platillos publicados para la jornada seleccionada.'],'historial'=>['calendar','Historial de atenciones','Consulta tus atenciones y los servicios registrados.'],'incidencias'=>['alert','Reportar incidencias','Comunica los problemas operativos y consulta su seguimiento.']];
 $title=$titles[$module]; $name=trim(auth()->user()->nombre.' '.auth()->user()->apellido_p.' '.auth()->user()->apellido_m);
 $initials=collect(explode(' ',$name))->filter()->take(2)->map(fn($n)=>mb_substr($n,0,1))->implode('');
 $date=fn($value)=>\Carbon\Carbon::parse($value)->locale('es')->translatedFormat('D, d \d\e M \d\e Y');
 $tone=fn($state)=>\App\Support\WaiterWorkspace::tone($state);
 $image=fn($path)=>\App\Support\ChefWorkspace::image($path,'img/ui/captain-dining.webp');
 $occupied=$tables->where('operativo','Ocupada')->count(); $available=$tables->where('operativo','Disponible')->count();
 $finalized=$attentions->filter(fn($a)=>in_array($a->estado,['Cerrada','Pendiente de pago']))->count();
 $pending=$active->filter(fn($a)=>$a->canOrder ?? false)->count();
 $ownIncidents=$incidents->filter(fn($i)=>!isset($i->id_servicio)||$i->id_servicio===$service?->id_servicio);
@endphp
@section('title',$title[1])
@section('content')
<main class="ww-workspace" data-ww-workspace>
 <header class="ww-hero {{ $module==='menu'?'ww-hero-food':'' }} {{ $module === 'panel' ? 'dashboard-banner dashboard-banner--waiter' : '' }}"><div class="ww-hero-icon"><x-icon :name="$title[0]"/></div><div><span class="ww-eyebrow">NUTRILIADO · {{ $site->nombre }} · Mesero</span><h1>{{ $title[1] }}</h1><p>{{ $title[2] }}</p>@if($module==='panel')<span class="ww-welcome">Hola, {{ auth()->user()->nombre }}</span>@endif</div><span class="ww-live-dot">Mi operación</span></header>
 @if(session('status'))<div class="ww-notice ww-green" role="status"><x-icon name="check"/>{{ session('status') }}</div>@endif
 @if($errors->any())<div class="ww-notice ww-red" role="alert">{{ $errors->first() }}</div>@endif
 <form class="ww-operation" method="GET" action="{{ route('waiter.index') }}">
  <input type="hidden" name="modulo" value="{{ $module }}">
  <label><x-icon name="utensils"/><span>Servicio<select data-ww-service-name aria-label="Servicio">@forelse($services->unique('nombre') as $s)<option value="{{ $s->nombre }}" @selected($s->nombre===$service?->nombre)>{{ $s->nombre }}</option>@empty<option>Sin servicios asignados</option>@endforelse</select></span></label>
  <label><x-icon name="calendar"/><span>Fecha / jornada<select name="id_servicio" data-ww-jornada aria-label="Jornada" onchange="this.form.requestSubmit()">@forelse($services as $s)<option value="{{ $s->id_servicio }}" data-service="{{ $s->nombre }}" @selected($s->id_servicio===$service?->id_servicio)>{{ $date($s->fecha) }} · {{ substr($s->inicio_hora,0,5) }}</option>@empty<option value="">Sin jornadas</option>@endforelse</select></span></label>
  <div><x-icon name="activity"/><span>Estado operativo<strong class="ww-badge ww-{{ $tone($service?->estado) }}">{{ $service?->estado ?? 'Sin servicio' }}</strong></span></div>
  <div><x-icon name="clock"/><span>Horario<strong>{{ $service? substr($service->inicio_hora,0,5).' – '.substr($service->fin_hora,0,5).' h':'—' }}</strong></span></div>
 </form>
 @include('mesero.workspace.'.$module)
 @include('mesero.workspace.forms')
</main>
@endsection
