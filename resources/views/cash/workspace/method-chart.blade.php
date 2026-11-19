@php
 $chartTotal=(float)$chartPayments->sum('importe');
 $cashPart=$chartTotal>0?$chartPayments->where('metodo','efectivo')->sum('importe')/$chartTotal*100:0;
 $cardEnd=$cashPart+($chartTotal>0?$chartPayments->where('metodo','tarjeta')->sum('importe')/$chartTotal*100:0);
@endphp
<div class="cr-method-chart"><div class="cr-donut {{ $chartTotal<=0?'cr-donut-empty':'' }}" style="--cr-cash:{{ $cashPart }}%;--cr-card:{{ $cardEnd }}%" role="img" aria-label="Distribución de cobros por método: efectivo {{ $money($chartPayments->where('metodo','efectivo')->sum('importe')) }}, tarjeta {{ $money($chartPayments->where('metodo','tarjeta')->sum('importe')) }}, transferencia {{ $money($chartPayments->where('metodo','transferencia')->sum('importe')) }}"><div><small>Total cobrado</small><strong>{{ $money($chartTotal) }}</strong><span>MXN</span></div></div><div class="cr-chart-legend">@foreach($methods as $key=>$method)<div><span class="cr-legend-dot cr-{{ $method[1] }}"></span><span>{{ $method[0] }}<small>{{ $chartPayments->where('metodo',$key)->count() }} cobros</small></span><strong>{{ $money($chartPayments->where('metodo',$key)->sum('importe')) }}</strong></div>@endforeach</div></div>
