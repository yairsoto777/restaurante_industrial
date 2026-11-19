@extends('layouts.app')
@section('content')
<div class="cw-page">
<header class="cw-heading"><div><span class="cw-eyebrow">NUTRILIADO / DIRECCIÓN DE COCINA</span><h1>@yield('title')</h1><p>{{ $site->nombre }} · Hola, {{ auth()->user()->nombre }}</p></div><span class="cw-date"><x-icon name="calendar"/>{{ now('America/Mexico_City')->format('d/m/Y') }}</span></header>
<section class="cw-banner cw-banner-@yield('banner','kitchen') @yield('dashboard-banner-class')"><div><span>PLANIFICA · PREPARA · COMPARTE</span><h2>@yield('banner-title','Una cocina bien organizada se disfruta')</h2><p>@yield('banner-copy','Cada servicio, cada platillo y cada detalle en su lugar.')</p></div><x-icon name="utensils"/></section>
@if(session('status'))<div class="cw-message ok" role="status"><x-icon name="check"/><p>{{ session('status') }}</p></div>@endif
@if($errors->any())<div class="cw-message danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@isset($metrics)<div class="cw-kpis" style="--cw-columns:3">@foreach($metrics as [$label,$value,$icon,$tone])<article class="cw-kpi {{ $tone }}"><span class="cw-kpi-icon"><x-icon :name="$icon"/></span><div><strong>{{ $value }}</strong><h2>{{ $label }}</h2><p>Datos de {{ $site->nombre }}</p></div></article>@endforeach</div>@endisset
@yield('workspace')
@stack('cw-dialogs')
</div>
@endsection
