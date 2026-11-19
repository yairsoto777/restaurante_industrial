<h1 class="gm-title" data-gm-title>{{ $document['settings']['titulo'] }}</h1>
<p class="gm-subtitle" data-gm-subtitle @if(empty($document['settings']['subtitulo'])) hidden @endif>{{ $document['settings']['subtitulo'] }}</p>
<p class="gm-period">{{ $document['period'] }}</p>
