@extends('chef.workspace.layout')
@section('title','Generador de menús')
@section('banner','generator')
@section('banner-title','Tu cocina merece una gran presentación')
@section('banner-copy','Seis composiciones gastronómicas para convertir tus servicios y platillos reales en una carta, un cartel o una edición para compartir.')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/chef-menu-document.css') }}?v={{ filemtime(public_path('css/chef-menu-document.css')) }}">
<link rel="stylesheet" href="{{ asset('css/chef-menu-generator.css') }}?v={{ filemtime(public_path('css/chef-menu-generator.css')) }}">
@endpush
@push('scripts')<script src="{{ asset('js/chef-menu-generator.js') }}?v={{ filemtime(public_path('js/chef-menu-generator.js')) }}" defer></script>@endpush
@section('workspace')
@php($canSaveDesign=\App\Support\ChefWorkspace::tables(['kitchen_menu_designs']))
<div class="gm-workspace" data-gm-generator>
<div class="gm-studio-heading"><div><span>ESTUDIO DE PRESENTACIÓN</span><h2>Del menú de cocina a la mesa</h2><p>Personaliza el documento sin cambiar tus platillos ni sus reglas de publicación.</p></div><div class="gm-export-actions"><button type="button" class="cw-button secondary" data-gm-print>Imprimir</button><button type="button" class="cw-button" data-gm-pdf @disabled(!\App\Support\ChefMenuPdf::browser())>Descargar PDF</button></div></div>
<p class="gm-feedback" role="status" aria-live="polite" data-gm-feedback hidden></p>
<div class="gm-studio"><aside class="gm-editor"><form method="POST" enctype="multipart/form-data" action="{{ route('chef.generator.save') }}" data-gm-form data-preview="{{ route('chef.generator.preview') }}" data-print="{{ route('chef.generator.print') }}" data-pdf="{{ route('chef.generator.pdf') }}">@csrf
@include('chef.generator.controls')
<footer class="gm-editor-footer"><button class="cw-button" @disabled(!$canSaveDesign)>Guardar diseño e imágenes</button><span>La vista previa cambia al instante. Guarda para conservar tu configuración.</span>@unless($canSaveDesign)<p>La persistencia del diseño requiere habilitar su esquema.</p>@endunless</footer>
</form></aside><div class="gm-preview-studio"><header class="gm-preview-toolbar"><div><span class="gm-live-dot"></span><strong>Vista previa</strong><small data-gm-count>{{ $document['dishCount'] }} platillos</small></div><button type="button" data-gm-fit>Ajustar a pantalla</button></header><div class="gm-preview-stage" data-gm-stage><div class="gm-preview-canvas" data-gm-preview>{!! \App\Support\ChefMenuDocument::render($document) !!}</div></div><div class="gm-preview-caption"><span data-gm-paper-label>{{ $settings['papel'] }} · {{ $settings['orientacion'] }}</span><span data-gm-final-count>{{ $finalDocument['dishCount'] }} platillos en la selección final</span></div><details class="gm-diagnostics" @if($document['pending']) open @endif><summary>Fechas sin menú en la selección <span data-gm-pending-count>{{ count($document['pending']) }}</span></summary><p data-gm-pending>{{ $document['pending'] ? implode(' · ',$document['pending']) : 'Todas las fechas incluidas tienen un menú configurado.' }}</p><small>Información administrativa. Estos espacios no aparecen en el documento final.</small></details></div></div>
<div class="gm-print-only" data-gm-final>{!! \App\Support\ChefMenuDocument::render($finalDocument) !!}</div>
<div data-gm-cache>@foreach(\App\Support\ChefMenuDocument::TEMPLATES as $key=>$label)<template data-template="{{ $key }}" data-kind="preview">{!! \App\Support\ChefMenuDocument::render($document,$key) !!}</template><template data-template="{{ $key }}" data-kind="final">{!! \App\Support\ChefMenuDocument::render($finalDocument,$key) !!}</template>@endforeach</div>
</div>
@endsection
