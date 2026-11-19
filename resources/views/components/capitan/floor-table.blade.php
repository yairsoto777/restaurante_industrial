@props(['number'=>null,'capacity'=>4])
<svg class="cw-table-symbol" viewBox="0 0 140 140" role="img" aria-label="Mesa {{ $number }}, {{ $capacity }} lugares">
@for($chair=0;$chair<min(100,(int)$capacity);$chair++)
<g transform="rotate({{ $chair*360/max(1,(int)$capacity) }} 70 70)"><rect x="60" y="9" width="20" height="20" rx="6" fill="currentColor" opacity=".65"/><path d="M62 12h16" stroke="white" stroke-opacity=".5"/></g>
@endfor
<rect x="34" y="34" width="72" height="72" rx="{{ $capacity<=4 ? 24 : 14 }}" fill="currentColor" opacity=".16"/>
<rect x="38" y="38" width="64" height="64" rx="{{ $capacity<=4 ? 20 : 10 }}" fill="currentColor"/>
<path d="M46 48h48M46 52h48" stroke="white" stroke-opacity=".12"/>
<text x="70" y="77" text-anchor="middle" fill="white" font-size="23" font-weight="700">{{ $number }}</text>
</svg>
