@extends('layouts.app')
@section('title', $title)
@section('content')
<div class="page-heading"><div><span class="section-kicker">Operación</span><h1>{{ $title }}</h1><p>{{ $description }}</p></div></div>
<section class="empty-panel module-placeholder"><div class="empty-icon"><x-icon name="layers" /></div><h2>Módulo en desarrollo</h2><p>La navegación y el contexto de sede están listos. La funcionalidad operativa se conectará en una siguiente etapa.</p></section>
@endsection
