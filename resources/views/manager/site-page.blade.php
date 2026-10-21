@extends('layouts.app')
@section('title', 'Mi página web')
@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">Presencia digital por sede</span>
            <h1>Mi página web</h1>
            <p>{{ $site->nombre }} usa una versión personalizada de la plantilla original NUTRILIADO.</p>
        </div>
        <div class="actions">
            <a class="button primary" href="{{ route('manager.site-page.editor') }}">Personalizar plantilla</a>
            <a class="button" href="{{ route('sites.public', $page->slug) }}" target="_blank" rel="noopener">Ver página pública</a>
        </div>
    </div>
    <section class="panel">
        <div class="panel-head"><div><span class="section-kicker">Plantilla fija</span><h2>Tu página mantiene el diseño original</h2></div></div>
        <p>Desde el editor puedes cambiar títulos, textos, colores corporativos, imágenes, GIF y video de portada. No puedes alterar la estructura, distribución ni código de la plantilla.</p>
        <p><strong>Estado:</strong> {{ $page->status === 'published' ? 'Publicada' : 'Borrador' }}</p>
        <div class="actions"><a href="{{ route('manager.site-page.editor') }}" class="button primary">Abrir editor</a></div>
    </section>
@endsection
