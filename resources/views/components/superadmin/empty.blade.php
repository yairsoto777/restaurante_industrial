@props(['title'=>'Todavía no hay registros','text'=>'La información aparecerá aquí cuando esté disponible.','icon'=>'building'])
<div class="sa-empty"><span><x-icon :name="$icon"/></span><strong>{{ $title }}</strong><p>{{ $text }}</p></div>
