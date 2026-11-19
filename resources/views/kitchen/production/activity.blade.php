<section class="kp-card">
    <div class="kp-card-heading"><span class="kp-symbol"><x-icon name="chart"/></span><div><h2>Actividad de comandas por hora</h2><p>{{ $module === 'panel' ? 'Recepciones de hoy' : 'Recepciones de la jornada' }}</p></div></div>
    @if($hourly->isNotEmpty())
        @php($peak = max(1, $hourly->max()))
        <div class="kp-chart-scroll">
            <div class="kp-bar-chart" role="list" aria-label="Comandas recibidas por hora">
                @foreach($hourly as $hour => $count)
                    <div class="kp-bar-column" role="listitem" aria-label="{{ sprintf('%02d:00', $hour) }}, {{ $count }} comandas">
                        <div><span style="--bar:{{ 100 * $count / $peak }}%"><b>{{ $count }}</b></span></div>
                        <small>{{ sprintf('%02d:00', $hour) }}</small>
                    </div>
                @endforeach
            </div>
        </div>
    @else <p class="kp-empty">Todavía no se han recibido comandas.</p> @endif
</section>
