@props(['label','value','icon'=>'chart','color'=>'blue','note'=>''])
<article class="sa-kpi sa-{{ $color }}"><span class="sa-kpi-icon"><x-icon :name="$icon"/></span><div><span>{{ $label }}</span><strong>{{ $value }}</strong><small>{{ $note }}</small></div></article>
