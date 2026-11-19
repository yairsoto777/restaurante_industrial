@if(!$active)
    <section class="kp-card kp-empty"><x-icon name="calendar"/><h2>Sin jornada activa</h2><p>Cuando una jornada aprobada esté en curso, aparecerán aquí sus datos y pedidos.</p></section>
@else
    <section class="kp-card kp-service-strip">
        <div class="kp-card-heading"><span class="kp-symbol"><x-icon name="utensils"/></span><div><h2>{{ $active->nombre }}</h2>
            <p><span class="kp-badge kp-preparando"><i></i>{{ $active->estado }}</span></p></div></div>
        <dl class="kp-inline"><div><dt>Fecha</dt><dd>{{ $fecha($active->fecha, 'd/m/Y') }}</dd></div>
            <div><dt>Horario</dt><dd>{{ substr($active->inicio_hora ?? '', 0, 5) }} – {{ substr($active->fin_hora ?? '', 0, 5) }}</dd></div>
            <div><dt>Comandas</dt><dd>{{ $current->count() }}</dd></div><div><dt>Comensales</dt><dd>{{ $guests }}</dd></div></dl>
    </section>
    <div class="kp-grid kp-dashboard-grid">
        @include('kitchen.production.activity')
        <section class="kp-card">
            <div class="kp-card-heading"><span class="kp-symbol"><x-icon name="activity"/></span><div><h2>Carga de preparación</h2><p>Porciones por estado en esta jornada</p></div></div>
            @php($serviceItems = $current->flatMap(fn ($order) => $order->items))
            <ul class="kp-legend">
                @foreach(['pendiente','preparando','listo','entregado'] as $key)
                    <li>@include('kitchen.production.badge', ['state' => $key]) <strong>{{ $serviceItems->where('state', $key)->sum('cantidad') }}</strong></li>
                @endforeach
            </ul>
        </section>
    </div>
    <section class="kp-card">
        <div class="kp-card-heading"><span class="kp-symbol"><x-icon name="dish"/></span><div><h2>Platillos solicitados</h2><p>Totales de la jornada por platillo</p></div></div>
        @if($requested->isEmpty()) <p class="kp-empty">Aún no se han solicitado platillos.</p>
        @else
            <div class="kp-dish-grid">@foreach($requested as $dish)
                <div class="kp-dish">
                    @include('kitchen.production.photo', ['image' => $dish['image']])
                    <strong>{{ $dish['name'] }}</strong><span>{{ $dish['quantity'] }} porciones</span>
                </div>
            @endforeach</div>
        @endif
    </section>
@endif
