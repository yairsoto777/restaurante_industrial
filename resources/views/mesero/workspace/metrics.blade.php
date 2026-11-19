<section class="ww-metrics" style="--ww-columns:{{ count($metrics)===4?4:3 }}" aria-label="Indicadores">
@foreach($metrics as $metric)<article class="ww-metric ww-{{ $metric[0] }}"><span class="ww-metric-icon"><x-icon :name="$metric[1]"/></span><div><strong>{{ $metric[2] }}</strong><span>{{ $metric[3] }}</span><small>{{ $metric[4]??'Jornada seleccionada' }}</small></div></article>@endforeach
</section>
