@props(['number','capacity'])
@if($capacity<=12)
<x-capitan.floor-table :number="$number" :capacity="$capacity"/>
@else
@php($seatWidth=max(2,min(15,260/max(1,$capacity))))
<svg class="cw-table-symbol" viewBox="0 0 140 140" role="img" aria-label="Mesa {{ $number }}, {{ $capacity }} lugares">
@for($chair=0;$chair<min(100,(int)$capacity);$chair++)<g transform="rotate({{ $chair*360/max(1,(int)$capacity) }} 70 70)"><rect x="{{ 70-$seatWidth/2 }}" y="9" width="{{ $seatWidth }}" height="14" rx="1" fill="currentColor" opacity=".65"/></g>@endfor
<circle cx="70" cy="70" r="40" fill="currentColor" opacity=".16"/><circle cx="70" cy="70" r="35" fill="currentColor"/><text x="70" y="77" text-anchor="middle" fill="white" font-size="23" font-weight="700">{{ $number }}</text></svg>
@endif
