@php($s=$document['settings'])
<article class="gm-document cw-template-{{ $template }} gm-template-{{ $template }} gm-period-{{ $s['periodo'] }} gm-orientation-{{ $s['orientacion'] }} gm-paper-{{ $s['papel'] }} {{ !$s['fotos'] ? 'gm-no-photos' : '' }} {{ !$s['precios'] ? 'gm-no-prices' : '' }} {{ !$s['descripciones'] ? 'gm-no-descriptions' : '' }} {{ !$s['horarios'] ? 'gm-no-hours' : '' }} {{ !$s['destacar_especial'] ? 'gm-no-special' : '' }}" style="--gm-primary:{{ $s['color'] }};--gm-secondary:{{ $s['color_secundario'] }};--gm-title-size:{{ $s['tamano_titulo'] }}px" data-gm-document data-template="{{ $template }}">
    <img class="gm-background" @if($document['background']) src="{{ $document['background'] }}" @else hidden @endif alt="" data-gm-background>
    @include('chef.generator.templates.'.$template)
    <footer class="gm-document-footer"><span data-gm-comedor>{{ $document['siteName'] }}</span><span>NUTRILIADO · Menú de alimentación</span></footer>
</article>
