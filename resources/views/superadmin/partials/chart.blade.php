@if(array_sum($values)>0)
@php
    $maximum=max(3,ceil(max($values)/3)*3); $points=[];
    foreach($values as $i=>$value) $points[]=(55+$i*100).','. (155-($value/$maximum*120));
@endphp
<figure class="sa-chart"><svg viewBox="0 0 610 200" role="img" aria-label="{{ $chartTitle }}"><title>{{ $chartTitle }}</title>
@foreach([0,1,2,3] as $tick)@php($y=155-$tick*40)<line x1="38" y1="{{ $y }}" x2="580" y2="{{ $y }}" class="sa-chart-grid"/><text x="28" y="{{ $y+4 }}" text-anchor="end">{{ round($maximum*$tick/3,1) }}</text>@endforeach
@if($kind==='line')<polygon points="55,155 {{ implode(' ',$points) }} 555,155" class="sa-chart-area"/><polyline points="{{ implode(' ',$points) }}" class="sa-chart-line"/>@endif
@foreach($values as $i=>$value)@php($x=55+$i*100)@php($y=155-$value/$maximum*120)
@if($kind==='bar')<rect x="{{ $x-17 }}" y="{{ $y }}" width="34" height="{{ 155-$y }}" rx="5" class="sa-chart-bar"><title>{{ $labels[$i] }}: {{ $value }}</title></rect>@else<circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="sa-chart-point"><title>{{ $labels[$i] }}: {{ $value }}</title></circle>@endif
<text x="{{ $x }}" y="{{ $y-10 }}" text-anchor="middle" class="sa-chart-value">{{ $value }}</text><text x="{{ $x }}" y="185" text-anchor="middle">{{ $labels[$i] }}</text>
@endforeach</svg><figcaption>{{ $chartTitle }}. @foreach($labels as $i=>$label){{ $label }}: {{ $values[$i] }}{{ $loop->last ? '.' : '; ' }}@endforeach</figcaption></figure>
@else<x-superadmin.empty title="Aún no hay datos en este periodo" text="Los registros reales de los últimos seis meses se mostrarán aquí." icon="chart"/>@endif
