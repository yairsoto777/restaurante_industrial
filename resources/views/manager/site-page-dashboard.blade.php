@extends('layouts.app')
@section('title', 'Mi página web')
@section('content')
    <div class="page-heading">
        <div>
            <span class="section-kicker">Plantilla corporativa</span>
            <h1>Mi página web</h1>
            <p>Personaliza la versión de NUTRILIADO de {{ $site->nombre }} sin alterar la página original.</p>
        </div>
        <a class="button" href="{{ route('sites.public', $page->slug) }}" target="_blank" rel="noopener">Ver página pública</a>
    </div>
    <div class="kpi-grid">
        <article class="kpi-card"><div class="kpi-copy"><span>Estado</span><strong>{{ $page->status === 'published' ? 'Publicada' : 'Borrador' }}</strong></div></article>
        <article class="kpi-card"><div class="kpi-copy"><span>Plantilla</span><strong>NUTRILIADO</strong></div></article>
        <article class="kpi-card"><div class="kpi-copy"><span>Secciones fijas</span><strong>6</strong></div></article>
        <article class="kpi-card"><div class="kpi-copy"><span>Última publicación</span><strong>{{ $page->published_at?->format('d/m/Y') ?: 'Pendiente' }}</strong></div></article>
    </div>
    <section class="panel site-builder-dashboard">
        <div>
            <span class="section-kicker">Tu espacio de trabajo</span>
            <h2>El diseño ya está listo: personaliza el contenido</h2>
            <p>Personaliza títulos, párrafos, colores corporativos, fotografías, GIF y video. Las secciones y la estructura visual permanecen fijas.</p>
        </div>
        <div class="actions">
            <a class="button primary" href="{{ route('manager.site-page.editor') }}">Editar contenido</a>
            <a class="button" href="{{ route('manager.site-page.media') }}" target="_blank" rel="noopener">Biblioteca multimedia</a>
            <form method="POST" action="{{ route('manager.site-page.publish') }}">@csrf<button type="submit" class="button success">Publicar borrador guardado</button></form>
        </div>
    </section>
@endsection
