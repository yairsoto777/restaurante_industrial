@extends('layouts.app')
@section('title', 'Editar página web')
@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">Plantilla fija NUTRILIADO</span><h1>Mi página web</h1><p>Personaliza textos, colores y archivos multimedia de {{ $site->nombre }} sin cambiar el diseño.</p></div>
        <div class="actions">
            <a class="button" target="_blank" rel="noopener" href="{{ route('sites.public', $page->slug) }}">Ver página publicada</a>
            <a class="button" href="{{ route('manager.site-page.edit') }}">Salir</a>
            <button class="button primary" id="save-page" type="button">Guardar borrador</button>
            <form method="POST" action="{{ route('manager.site-page.publish') }}" id="publish-form">@csrf<button class="button success" type="submit">Publicar</button></form>
        </div>
    </div>
    <div id="web-editor" class="nu-editor" data-save-url="{{ route('manager.site-page.editor.save') }}" data-upload-url="{{ route('manager.site-page.media.upload') }}" data-csrf="{{ csrf_token() }}">
        <aside class="nu-editor-panel"><h3>Secciones fijas</h3><nav id="section-list" aria-label="Secciones editables"></nav></aside>
        <main class="nu-editor-center">
            <div class="nu-editor-toolbar"><strong>Vista previa real</strong><span id="save-state" role="status">Borrador cargado</span><div class="nu-devices"><button type="button" data-device="desktop" class="active">Escritorio</button><button type="button" data-device="tablet">Tablet</button><button type="button" data-device="mobile">Móvil</button></div></div>
            <div class="nu-frame-outer"><iframe title="Vista previa de la página NUTRILIADO" id="site-preview" src="{{ route('manager.site-page.preview', $page->slug) }}" loading="eager"></iframe></div>
            <p class="nu-editor-note">Esta vista muestra la estructura real de la página. Los cambios son locales hasta guardar y publicar.</p>
        </main>
        <aside class="nu-editor-panel nu-editor-fields"><h3 id="properties-title">Contenido editable</h3><div id="property-panel"></div></aside>
    </div>
    <script type="application/json" id="page-data">@json($page->draft ?: [])</script>
    <style>
        .nu-editor{display:grid;grid-template-columns:190px minmax(0,1fr) 310px;gap:14px;align-items:stretch}.nu-editor-panel,.nu-editor-center{background:#fff;border:1px solid #dbe3ed;border-radius:12px;padding:16px;min-width:0}.nu-editor-panel h3{margin:0 0 14px}.nu-editor-panel nav button{display:block;width:100%;text-align:left;padding:11px 12px;border:0;background:transparent;border-radius:8px;cursor:pointer;color:#263c57}.nu-editor-panel nav button:hover{background:#edf4ff}.nu-editor-panel nav button.active{background:#137cf3;color:white;font-weight:bold}.nu-editor-toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.nu-devices{display:flex;gap:4px}.nu-devices button{padding:6px 9px;border:1px solid #dbe3ed;background:white;border-radius:6px;cursor:pointer}.nu-devices button.active{color:#fff;background:#137cf3}.nu-frame-outer{display:flex;justify-content:center;background:#eaf0f7;padding:12px;border-radius:10px;min-height:680px;overflow:auto}.nu-frame-outer iframe{background:white;width:100%;height:740px;border:1px solid #ced7e4;border-radius:6px;transition:width .2s;flex-shrink:0}.nu-editor-fields label{display:block;font-size:12px;font-weight:700;margin:12px 0}.nu-editor-fields input,.nu-editor-fields textarea{display:block;width:100%;padding:8px;margin-top:6px;border:1px solid #ccd6e2;border-radius:7px;box-sizing:border-box}.nu-editor-fields input[type=color]{height:44px}.nu-editor-fields input[type=file]{font-size:12px}.nu-editor-fields textarea{min-height:80px;resize:vertical}.nu-editor-fields small{font-size:11px;font-weight:400;color:#64748b}.nu-editor-fields .group-label{padding-top:12px;margin-top:16px;border-top:1px solid #dce4ec;font-size:13px;font-weight:700}.nu-editor-note{font-size:12px;color:#64748b}.nu-editor-fields img{max-width:100%;max-height:110px;object-fit:contain;border-radius:5px;margin-top:8px}@media(max-width:1150px){.nu-editor{grid-template-columns:170px minmax(0,1fr)}.nu-editor-fields{grid-column:1/-1}}@media(max-width:650px){.nu-editor{display:block}.nu-editor>*{margin-bottom:12px}.nu-editor-toolbar{display:block}.nu-devices{margin-top:8px}}
    </style>
    <script src="{{ asset('js/site-page-editor.js') }}?v={{ filemtime(public_path('js/site-page-editor.js')) }}" defer></script>
@endsection
