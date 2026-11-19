@php
    $palette = $palette ?? ['#19b76b','#f3bd36','#e35760','#3988cc','#ed8d36','#8f73ca'];
    $total = $distribution->sum(); $position = 0; $stops = [];
    foreach ($distribution as $label=>$value) { $next = $position + ($total ? $value / $total * 100 : 0); $stops[] = $palette[count($stops) % count($palette)].' '.$position.'% '.$next.'%'; $position=$next; }
@endphp
@if($total)
<div class="wh-distribution"><div class="wh-donut" role="img" aria-label="{{ $distribution->map(fn ($v,$k) => $k.': '.$v)->join(', ') }}" style="background:conic-gradient({{ implode(',',$stops) }})"><div><strong>{{ $total }}</strong><small>{{ $chartUnit ?? 'registros' }}</small></div></div><ul class="wh-legend">@foreach($distribution as $label=>$value)<li><i style="background:{{ $palette[$loop->index % count($palette)] }}"></i><span>{{ $label }}</span><strong>{{ $value }}</strong></li>@endforeach</ul></div>
@else<p class="wh-empty">No hay datos registrados para esta distribución.</p>@endif
