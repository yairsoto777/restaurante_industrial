<section class="kp-card">
    <div class="kp-card-heading kp-split">
        <div><h2>{{ match($module) { 'preparacion' => 'Tablero de producción', 'listos' => 'Por entregar', 'historial' => 'Registro de comandas', default => 'Pedidos recibidos' } }}</h2>
            <p>{{ $page->total() }} {{ $page->total() === 1 ? 'comanda encontrada' : 'comandas encontradas' }}</p></div>
        <a class="kp-button" href="{{ $back }}"><x-icon name="activity"/>Actualizar</a>
    </div>
    <form class="kp-filters" method="get" action="{{ route('kitchen.index') }}">
        <input type="hidden" name="modulo" value="{{ $module }}">
        <label>Buscar<input name="q" type="search" placeholder="Comanda, mesa o platillo" value="{{ $filter['q'] ?? '' }}" maxlength="120"></label>
        <label>Servicio o jornada<select name="servicio"><option value="">Todas las jornadas</option>
            @foreach($serviceOptions as $option)
                <option value="{{ $option->id_servicio }}" @selected(($filter['servicio'] ?? null) == $option->id_servicio)>{{ $option->nombre }} · {{ $fecha($option->fecha, 'd/m/Y') }}</option>
            @endforeach
        </select></label>
        @if(in_array($module, ['comandas', 'historial'], true))
            <label>Estado de partida<select name="estado"><option value="">Todos los estados</option>
                @foreach($states as $key => $label)
                    <option value="{{ $key }}" @selected(($filter['estado'] ?? null) === $key)>{{ $label }}</option>
                @endforeach
            </select></label>
        @endif
        @if($history)
            <label>Recibida desde<input type="date" name="desde" value="{{ $filter['desde'] ?? '' }}"></label>
            <label>Hasta<input type="date" name="hasta" value="{{ $filter['hasta'] ?? '' }}"></label>
        @endif
        <div class="kp-filter-actions">
            <button class="kp-button kp-primary"><x-icon name="search"/>Filtrar</button>
            @if(collect($filter)->except(['modulo','comanda'])->filter()->isNotEmpty())
                <a class="kp-text-link" href="{{ $link($module) }}">Limpiar</a>
            @endif
        </div>
    </form>
</section>

@if($page->isEmpty())
    <section class="kp-card kp-empty">
        <x-icon name="dish"/>
        <h2>{{ match($module) {'preparacion' => 'Sin partidas en preparación', 'listos' => 'Sin platillos listos', default => 'Sin comandas para mostrar'} }}</h2>
        <p>{{ collect($filter)->except(['modulo','comanda'])->filter()->isNotEmpty() ? 'Prueba con otros filtros.' : 'Los pedidos aparecerán aquí conforme avance la producción.' }}</p>
    </section>
@elseif(in_array($module, ['preparacion', 'listos'], true))
    @include('kitchen.production.board')
@else
    <section class="kp-card">
        @if($history)<div class="kp-card-heading"><div><h2>Historial de producción</h2><p>Consulta de solo lectura. Las fechas aparecen cuando existe un registro.</p></div></div>@endif
        <div class="kp-table-wrap">
            <table class="kp-table kp-order-table">
                <thead><tr><th>Comanda</th><th>Mesa y zona</th><th>Servicio</th><th>Mesero</th><th>Recepción</th><th>Platillos</th>
                    @if($history)<th>Trazabilidad por partida</th>@endif
                    <th>Estado</th><th>Detalle</th></tr></thead>
                <tbody>
                @foreach($page as $order)
                    <tr>
                        <th scope="row">#{{ $order->id_comanda }}<small>Atención #{{ $order->id_atencion }}</small></th>
                        <td>Mesa {{ $order->mesa_numero }}<small>{{ $order->zona_nombre }}</small></td>
                        <td>{{ $order->servicio_nombre }}<small>{{ $fecha($order->servicio_fecha, 'd/m/Y') }}</small></td>
                        <td>{{ $order->waiter ?: 'Sin mesero registrado' }}</td><td>{{ $fecha($order->fecha_apertura) }}</td>
                        <td><ul class="kp-line-list">@foreach($order->items as $item)<li>{{ $item->cantidad }} × {{ $item->platillo_nombre }}</li>@endforeach</ul></td>
                        @if($history)
                            <td>
                                @foreach($order->items as $item)
                                    <div class="kp-trace">
                                        <strong>{{ $item->platillo_nombre }}</strong>
                                        <small>Inicio {{ $fecha($item->preparacion_iniciada_en ?? null) }}</small>
                                        <small>Listo {{ $fecha($item->lista_en ?? null) }}</small>
                                        <small>Entrega {{ $fecha($item->delivery?->fecha_entrega) }}</small>
                                    </div>
                                @endforeach
                            </td>
                        @endif
                        <td>@include('kitchen.production.badge', ['state' => $order->state])
                            @include('kitchen.production.observation-indicator')</td>
                        <td><a class="kp-text-link" href="{{ $detail($order) }}">Ver detalle</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@if($page->hasPages())
    <nav class="kp-pagination" aria-label="Páginas de comandas">
        @if($page->previousPageUrl())<a class="kp-button" href="{{ $page->previousPageUrl() }}">Anterior</a>@endif
        <span>Página {{ $page->currentPage() }} de {{ $page->lastPage() }}</span>
        @if($page->nextPageUrl())<a class="kp-button" href="{{ $page->nextPageUrl() }}">Siguiente</a>@endif
    </nav>
@endif
