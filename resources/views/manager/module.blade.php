@extends('layouts.app')
@section('title', $title)
@section('content')<section class="panel empty-state"><span class="module-icon">{{ strtoupper(substr($title, 0, 2)) }}</span><h2>{{ $title }}</h2><p>{{ $description }}</p><div class="alert success">Tu acceso está limitado a las sedes que tienes asignadas.</div><a class="button" href="{{ route('manager.sites') }}">Consultar mis sedes</a></section>@endsection
