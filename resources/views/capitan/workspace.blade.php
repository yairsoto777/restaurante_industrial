@extends('layouts.app')
@section('title','Capitán de meseros · NUTRILIADO')
@section('content')
@php
    $CW = \App\Support\CaptainWorkspace::class;
    $titles=['panel'=>'Panel principal','servicios'=>'Servicios','zonas'=>'Gestión de zonas','mesas'=>'Gestión de mesas','asignaciones'=>'Asignación de meseros','comedor'=>'Comedor en vivo','incidencias'=>'Incidencias'];
    $modulo = array_key_exists($modulo,$titles) ? $modulo : 'comedor';
    $occupied=$mesasServicio->whereNotNull('id_atencion')->count();
    $total=$mesasServicio->count();
    $available=$mesasServicio->where('operativo','Disponible')->count();
    $percent=$total?round($occupied*100/$total):0;
    $assigned=$asignaciones->pluck('id_usuario_mesero')->unique();
    $covered=$mesasServicio->whereIn('id_zona',$asignaciones->pluck('id_zona'))->count();
    $coverage=$total?round($covered*100/$total):0;
    $segments=[]; $cursor=0;
    $palette=['green'=>'#188264','blue'=>'#2877b9','yellow'=>'#e1b600','purple'=>'#8650bc','orange'=>'#d5862b','red'=>'#cf4444','forest'=>'#246644','gray'=>'#748091'];
    foreach($mesasServicio->groupBy('operativo') as $state=>$group) {
        $next=$cursor+($total ? $group->count()*100/$total : 0);
        $segments[]=($palette[$CW::tone($state)] ?? '#748091').' '.$cursor.'% '.$next.'%';
        $cursor=$next;
    }
    $distribution=implode(',',$segments) ?: '#e4edf3 0% 100%';
    $link=fn($module,$service=null)=>route('capitan.index',array_filter(['modulo'=>$module,'fecha'=>$fechaOperacion,'id_servicio'=>$service ?? $jornada?->id_servicio]));
@endphp
<div class="cw-page" data-cw-module="{{ $modulo }}" style="--cw-distribution:{{ $distribution }}">
@if(!in_array($modulo,['panel','zonas','mesas','asignaciones','comedor'],true))
<header class="cw-heading"><div><span class="cw-eyebrow">NUTRILIADO / ATENCIÓN Y COMEDOR</span><h1>{{ $titles[$modulo] }}</h1><p>{{ $sede->nombre }} · Capitán de meseros</p></div><span class="cw-badge green">● Conexión activa</span></header>
@endif
@if(session('status') || session('success'))<p class="cw-notice cw-notice-success" role="status">{{ session('status') ?? session('success') }}</p>@endif
@if($errors->any())<div class="cw-notice red" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if(!in_array($modulo,['panel','zonas','mesas','asignaciones','comedor'],true))
<form class="cw-filters" method="GET"><input type="hidden" name="modulo" value="{{ $modulo }}"><label>Fecha de jornada<input type="date" name="fecha" value="{{ $fechaOperacion }}" required></label><label>Servicio / jornada<select name="id_servicio"><option value="">Selección automática</option>@foreach($servicios as $service)<option value="{{ $service->id_servicio }}" @selected($jornada?->id_servicio===$service->id_servicio)>{{ $service->nombre }} · {{ substr($service->inicio_hora,0,5) }} – {{ substr($service->fin_hora,0,5) }}</option>@endforeach</select></label><button class="cw-btn">Consultar</button>@if(in_array($modulo,['zonas','mesas','asignaciones','comedor'],true))<label>Zona<select data-cw-zone-filter><option value="">Todas las zonas</option>@foreach($zonas as $zone)<option value="{{ $zone->id_zona }}">{{ $zone->nombre }}</option>@endforeach</select></label>@endif<label class="cw-search">Buscar<input type="search" data-cw-search placeholder="Mesa, zona, mesero o servicio…"></label></form>
@endif

@if($modulo==='panel')
@include('capitan.partials.dashboard-workspace')
@elseif($modulo==='servicios')
<div class="cw-section"><div><h2>Jornadas autorizadas</h2><p>Organiza la atención de cada servicio aprobado.</p></div><label>Estado<select data-cw-state-filter><option value="">Todos</option>@foreach($servicios->pluck('estado')->unique() as $state)<option>{{ $state }}</option>@endforeach</select></label></div>
@include('capitan.partials.service-cards')
@elseif($modulo==='zonas')
@include('capitan.partials.zones-workspace')
@elseif($modulo==='mesas')
@include('capitan.partials.tables-workspace')
@elseif($modulo==='asignaciones')
@include('capitan.partials.assignments-workspace')
@elseif($modulo==='comedor')
@include('capitan.partials.live-workspace')
@elseif($modulo==='incidencias')
<div class="cw-section"><div><h2>Seguimiento del comedor</h2><p>Estados registrados y evidencias de la sede.</p></div><button class="cw-btn" data-cw-open="incident-create">+ Registrar incidencia</button></div><section class="cw-stats"><article class="cw-stat orange"><small>Incidencias abiertas</small><strong>{{ $metricas['incidencias'] }}</strong></article>@foreach($incidentCounts as $count)<article class="cw-stat {{ $CW::tone($count->estado) }}"><small>{{ ucfirst($count->estado) }}</small><strong>{{ $count->total }}</strong></article>@endforeach</section>
<section class="cw-card cw-table-wrap"><p>Últimas 100 incidencias de la sede. Los indicadores incluyen todos los registros.</p><table><thead><tr><th>Folio / fecha</th><th>Servicio / jornada</th><th>Zona / mesa</th><th>Descripción</th><th>Estado</th><th>Seguimiento / evidencia</th></tr></thead><tbody>@forelse($incidencias as $incident)<tr data-cw-record><td><strong>#{{ $incident->id_incidencia }}</strong><small>{{ $incident->fecha }}</small></td><td>{{ $incident->servicio_nombre ?? 'Sin servicio vinculado' }}<small>{{ $incident->jornada_fecha ?? '' }}</small></td><td>{{ $incident->zona_nombre ?? '—' }}<small>{{ isset($incident->mesa_numero) ? 'Mesa '.$incident->mesa_numero : '' }}</small></td><td>{{ $incident->descripcion }}<small>Registró: {{ $incident->responsable_nombre ?? '—' }}</small></td><td><span class="cw-badge {{ $CW::tone($incident->estado) }}">{{ ucfirst($incident->estado) }}</span></td><td>{{ $incident->respuesta ?? 'Sin respuesta registrada' }}@if($incident->evidencia_url ?? null)<a href="{{ \App\Support\ChefWorkspace::image($incident->evidencia_url) }}" target="_blank" rel="noopener"><img class="cw-evidence" src="{{ \App\Support\ChefWorkspace::image($incident->evidencia_url) }}" alt="Evidencia de incidencia #{{ $incident->id_incidencia }}"></a>@endif</td></tr>@empty<tr><td colspan="6" class="cw-empty">Sin incidencias registradas.</td></tr>@endforelse</tbody></table></section>
@include('capitan.partials.incident-form')
@endif
</div>
@endsection
