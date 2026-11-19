@extends('layouts.app')
@section('content')
<div class="wh-page">
    <header class="wh-heading"><div><span class="wh-eyebrow">NUTRILIADO / ALMACÉN</span><h1>@yield('title')</h1><p>{{ $site->nombre }} · Hola, {{ auth()->user()->nombre }}</p></div><span class="wh-date"><x-icon name="calendar"/>{{ now('America/Mexico_City')->translatedFormat('d \d\e F, Y') }}</span></header>
    <section class="wh-banner wh-banner-@yield('banner', 'warehouse') @yield('dashboard-banner-class')"><div><span>CONTROL Y ABASTECIMIENTO</span><h2>@yield('banner-title')</h2><p>@yield('banner-copy')</p></div><x-icon name="warehouse"/></section>
    @if(session('status'))<div class="wh-message ok" role="status"><x-icon name="check"/><p>{{ session('status') }}</p></div>@endif
    @if($errors->any())<div class="wh-message danger" role="alert"><div><strong>Revisa los datos antes de continuar</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
    @isset($metrics)<div class="wh-kpis" style="--wh-columns:{{ min( count($metrics), 6) }}">@foreach($metrics as [$label,$value,$description,$icon,$tone])<article class="wh-kpi {{ $tone }}"><span class="wh-kpi-icon"><x-icon :name="$icon"/></span><div><strong>{{ $value }}</strong><h2>{{ $label }}</h2><p>{{ $description }}</p></div></article>@endforeach</div>@endisset
    @yield('workspace')
</div>
@endsection
