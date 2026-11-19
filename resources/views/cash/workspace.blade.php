@extends('layouts.app')
@php
 $titles=['panel'=>['chart','Panel principal','Controla tus ingresos, cobros y el estado de tu caja.'],'turno'=>['cash','Turno de caja','Prepara tu operación y consulta el estado del turno actual.'],'pendientes'=>['clock','Cuentas pendientes','Consulta los servicios finalizados y registra cada cobro con claridad.'],'historial'=>['activity','Historial de cobros','Consulta y filtra los pagos registrados en tu sede.'],'tickets'=>['receipt','Tickets','Consulta los comprobantes de tus operaciones y vuelve a imprimirlos.'],'cierre'=>['check','Cierre de caja','Revisa los movimientos y concilia el efectivo de tu turno.']];
 $title=$titles[$module];$todayTotal=$todayPayments->sum('importe');
 if($module==='turno')$title=['register','Turno de caja','Apertura, control y seguimiento del turno actual.'];
 if($module==='pendientes')$title=['users','Cuentas pendientes','Consulta y cobra las atenciones listas para pago.'];
 if($module==='tickets')$title=['receipt','Tickets','Emisión, búsqueda y reimpresión de comprobantes.'];
 $headerOpen=$module==='turno'?$turno?->estado==='abierto':(bool)$shift;
 $leading=$todayPayments->groupBy('metodo')->sortByDesc(fn($group)=>$group->count())->keys()->first();
@endphp
@section('title',$title[1])
@section('content')
<main class="cr-workspace" data-cr-workspace data-cr-module="{{ $module }}">
 <header class="cr-hero {{ $module === 'panel' ? 'dashboard-banner dashboard-banner--cashier' : '' }}"><span class="cr-hero-icon">@if($module==='turno')<x-cashier.turno-icon name="register"/>@else<x-cashier.icon :name="$title[0]"/>@endif</span><div><span class="cr-eyebrow">NUTRILIADO · {{ $site->nombre }} · Cajero</span><h1>{{ $title[1] }}</h1><p>{{ $title[2] }}</p></div>@if($module==='tickets')@include('cash.workspace.ticket-shift-info')@else<div class="cr-hero-status"><span class="cr-badge cr-{{ $headerOpen?'green':'gray' }}">{{ $headerOpen?'Turno abierto':($module==='turno' && $turno?'Turno cerrado':'Sin turno abierto') }}</span><small>{{ now()->locale('es')->translatedFormat('D, d M Y') }}</small></div>@endif</header>
 @if(session('status'))<div class="cr-notice cr-green" role="status"><x-icon name="check"/><span>{{ session('status') }}</span>@if(session('cash_payment_id'))<a class="cr-button cr-primary" href="{{ route('cash.ticket',session('cash_payment_id')) }}">Ver ticket</a>@endif</div>@endif
 @if($errors->any())<div class="cr-notice cr-red" role="alert">{{ $errors->first() }}</div>@endif
 @if($module!=='turno')<div class="cr-context"><span><x-icon name="building"/>{{ $site->nombre }}</span><span><x-icon name="users"/>{{ $responsible?trim($responsible->nombre.' '.$responsible->apellido_p):auth()->user()->nombre }}</span><span><x-icon name="clock"/>{{ $shift?'Turno #'.$shift->id_turno_caja.' · '.$date($shift->abierta_at):'Abre un turno para comenzar a cobrar' }}</span><a href="{{ $link($shift?'cierre':'turno') }}">{{ $shift?'Revisar cierre':'Abrir turno' }}<x-icon name="chevron"/></a></div>@endif
 @include('cash.workspace.'.$module)
</main>
@endsection
