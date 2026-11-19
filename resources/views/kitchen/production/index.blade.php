@extends('layouts.app')

@php
    $titles = [
        'panel' => ['Panel principal', 'Consulta y gestiona la preparación de los alimentos solicitados.', 'utensils'],
        'servicio' => ['Servicio actual', 'Consulta los pedidos y la carga de trabajo de la jornada.', 'calendar'],
        'comandas' => ['Comandas', 'Consulta los pedidos recibidos y actualiza cada partida.', 'layers'],
        'preparacion' => ['En preparación', 'Consulta las partidas que ya se están preparando.', 'clock'],
        'listos' => ['Platillos listos', 'Revisa las porciones terminadas y confirma su entrega.', 'dish'],
        'historial' => ['Historial de comandas', 'Consulta los pedidos y sus registros de producción.', 'clock'],
    ];
    [$title, $description, $icon] = $titles[$module];
    $heading = $module === 'panel' ? '¡Hola, '.auth()->user()->nombre.'!' : $title;
    $cover = $module === 'servicio' && !empty($active?->cover_url)
        ? \App\Support\ChefWorkspace::image($active->cover_url) : asset('img/ui/captain-dining.webp');
    $cleanQuery = collect(request()->query())->except('comanda')->all();
    $link = fn ($destination, $args = []) => route('kitchen.index', ['modulo' => $destination] + $args);
    $detail = fn ($order) => route('kitchen.index', ['comanda' => $order->id_comanda] + $cleanQuery);
    $back = route('kitchen.index', $cleanQuery);
    $fecha = fn ($value, $format = 'd/m/Y H:i') => filled($value) ? \Illuminate\Support\Carbon::parse($value)->format($format) : 'Sin registro';
    $elapsed = fn ($value) => \Illuminate\Support\Carbon::parse($value)->locale('es')->diffForHumans();
    $preparation = ['pendiente' => ['preparando', 'Iniciar preparación'], 'preparando' => ['listo', 'Marcar como listo'], 'listo' => ['entregado', 'Registrar entrega']];
    $selectedActionable = !in_array($module, ['historial', 'servicio'], true)
        && $selected?->atencion_estado === 'En atención' && $selected->estado_aprobacion === 'Aprobado'
        && !in_array($selected->servicio_estado, ['Cancelado', 'Finalizado'], true);
@endphp

@section('title', $title)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/kitchen-production.css') }}?v={{ filemtime(public_path('css/kitchen-production.css')) }}">
@endpush
@push('scripts')
    <script src="{{ asset('js/kitchen-production.js') }}?v={{ filemtime(public_path('js/kitchen-production.js')) }}" defer></script>
@endpush

@section('content')
<main class="kp-workspace">
    <header class="kp-hero {{ $module === 'panel' ? 'dashboard-banner dashboard-banner--kitchen' : '' }}">
        @if($module !== 'panel')
            <img src="{{ $cover }}" alt="" fetchpriority="high">
        @endif
        <div class="kp-hero-content">
            <span class="kp-hero-icon"><x-icon :name="$icon"/></span>
            <div><p>{{ $site->nombre }} · Personal de cocina</p><h1>{{ $heading }}</h1><div>{{ $description }}</div></div>
        </div>
        @if($module === 'servicio' && $active)
            <a class="kp-button kp-primary" href="{{ $link('comandas', ['servicio' => $active->id_servicio]) }}"><x-icon name="layers"/>Ver comandas</a>
        @endif
    </header>
    @if(!$selected && session('status'))<div class="kp-feedback" role="status">{{ session('status') }}</div>@endif
    @if(!$selected && $errors->any())<div class="kp-feedback kp-danger" role="alert">{{ $errors->first() }}</div>@endif

    @switch($module)
        @case('panel') @include('kitchen.production.panel') @break
        @case('servicio') @include('kitchen.production.service') @break
        @default @include('kitchen.production.orders')
    @endswitch

    @if($selected) @include('kitchen.production.detail') @endif
</main>
@endsection
