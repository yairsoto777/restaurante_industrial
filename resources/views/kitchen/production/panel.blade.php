<div class="kp-kpis">
    @foreach([
        ['Comandas pendientes', $metrics['pending'], 'clock', 'pendiente'],
        ['Comandas en preparación', $metrics['preparing'], 'utensils', 'preparando'],
        ['Platillos listos', $metrics['ready'], 'dish', 'listo'],
        ['Comandas entregadas hoy', $metrics['delivered'], 'check', 'entregado'],
    ] as [$label, $value, $glyph, $tone])
        <article class="kp-card kp-kpi"><span class="kp-symbol kp-{{ $tone }}"><x-icon :name="$glyph"/></span><div><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div></article>
    @endforeach
</div>

<section class="kp-card kp-service-strip">
    <div class="kp-card-heading"><span class="kp-symbol"><x-icon name="calendar"/></span><div><h2>Servicio actual</h2>
        @if($active)<p>{{ $active->nombre }}</p>@else<p>No hay una jornada activa.</p>@endif
    </div></div>
    @if($active)
        <dl class="kp-inline"><div><dt>Jornada</dt><dd>{{ $fecha($active->fecha, 'd/m/Y') }}</dd></div>
            <div><dt>Horario</dt><dd>{{ substr($active->inicio_hora ?? '', 0, 5) }} – {{ substr($active->fin_hora ?? '', 0, 5) }}</dd></div>
            <div><dt>Estado</dt><dd><span class="kp-badge kp-preparando"><i></i>{{ $active->estado }}</span></dd></div>
            <div><dt>Comandas</dt><dd>{{ $current->count() }}</dd></div></dl>
        <a class="kp-button" href="{{ $link('servicio') }}">Ver servicio <x-icon name="chevron"/></a>
    @endif
</section>

<div class="kp-grid kp-dashboard-grid">
    @include('kitchen.production.activity')
    <section class="kp-card">
        <div class="kp-card-heading"><span class="kp-symbol"><x-icon name="layers"/></span><div><h2>Distribución por estado</h2><p>Comandas recibidas hoy</p></div></div>
        @if($distribution->isNotEmpty())
            @php($total = $distribution->sum())
            <div class="kp-distribution" aria-hidden="true">
                @foreach($distribution as $key => $value)
                    <span class="kp-{{ $key }}" style="flex-basis:{{ 100 * $value / $total }}%"></span>
                @endforeach
            </div>
            <ul class="kp-legend">@foreach($states as $key => $label)
                <li>@include('kitchen.production.badge', ['state' => $key]) <strong>{{ $distribution->get($key, 0) }}</strong></li>
            @endforeach</ul>
        @else <p class="kp-empty">No hay comandas recibidas hoy.</p> @endif
    </section>
</div>

<div class="kp-grid kp-dashboard-grid">
    <section class="kp-card">
        <div class="kp-card-heading kp-split"><div><h2>Comandas recientes</h2><p>Últimos pedidos recibidos hoy</p></div><a class="kp-text-link" href="{{ $link('comandas') }}">Ver comandas</a></div>
        @if($recent->isEmpty()) <p class="kp-empty">Aún no hay comandas recientes.</p>
        @else
            <div class="kp-table-wrap"><table class="kp-table"><thead><tr><th>Comanda</th><th>Mesa</th><th>Recepción</th><th>Platillos</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
            @foreach($recent as $order)
                <tr><th scope="row">#{{ $order->id_comanda }}</th><td>{{ $order->mesa_numero }}</td><td>{{ $fecha($order->fecha_apertura, 'H:i') }}</td>
                    <td>{{ $order->quantity }}</td><td>@include('kitchen.production.badge', ['state' => $order->state]) @include('kitchen.production.observation-indicator')</td>
                    <td><a class="kp-text-link" href="{{ $detail($order) }}">Ver detalle</a></td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </section>
    <section class="kp-card">
        <div class="kp-card-heading"><span class="kp-symbol kp-cancelado"><x-icon name="alert"/></span><div><h2>Alertas</h2><p>Espera y observaciones comunicadas</p></div></div>
        @forelse($alerts as $order)
            <a class="kp-alert kp-danger" href="{{ $detail($order) }}"><x-icon name="alert"/><div><strong>#{{ $order->id_comanda }} · Mesa {{ $order->mesa_numero }}</strong><span>{{ \Illuminate\Support\Str::limit($order->pendingObservations->last()?->observacion ?? $order->notes->implode(' · '), 150) }}</span></div></a>
        @empty <p class="kp-muted">Sin observaciones en las comandas activas.</p> @endforelse
        @if($waiting->isNotEmpty())
            <h3 class="kp-subheading">Mayor espera desde la recepción</h3>
            @foreach($waiting as $order)
                <a class="kp-alert kp-wait" href="{{ $detail($order) }}"><x-icon name="clock"/><div><strong>#{{ $order->id_comanda }} · Mesa {{ $order->mesa_numero }}</strong><span>Recibida {{ $elapsed($order->fecha_apertura) }}</span></div></a>
            @endforeach
        @endif
    </section>
</div>
