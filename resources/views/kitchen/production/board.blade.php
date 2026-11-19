<div class="kp-board">
    @foreach($page as $order)
        @php($items = $order->items->where('state', $stage))
        <article class="kp-card kp-board-card kp-board-{{ $stage }}">
            <header>
                <div><h2>Comanda #{{ $order->id_comanda }}</h2><p>Mesa {{ $order->mesa_numero }} · {{ $order->zona_nombre }}</p></div>
                @include('kitchen.production.badge', ['state' => $stage])
            </header>
            @include('kitchen.production.observation-indicator')
            <dl class="kp-inline">
                <div><dt>Servicio</dt><dd>{{ $order->servicio_nombre }}</dd></div>
                <div><dt>Porciones</dt><dd>{{ $items->sum('cantidad') }}</dd></div>
                <div><dt>Recepción</dt><dd>{{ $fecha($order->fecha_apertura) }}</dd></div>
            </dl>
            @if($stage === 'preparando' && $loop->first && $page->currentPage() === 1 && $page->total() > 1)
                <div class="kp-priority"><x-icon name="clock"/>{{ $tracksPreparation ? 'Preparación o recepción más antigua' : 'Recepción más antigua en este listado' }}</div>
            @endif
            @if($order->notes->isNotEmpty())
                <div class="kp-alert kp-danger"><x-icon name="alert"/><div><strong>{{ $stage === 'listo' ? 'Observaciones e indicaciones de entrega' : 'Observaciones para cocina' }}</strong>
                    @foreach($order->notes as $note)<span>{{ $note }}</span>@endforeach
                </div></div>
            @endif
            <ul class="kp-board-lines">
                @foreach($items as $item)
                    <li>
                        @include('kitchen.production.photo', ['image' => $item->platillo_imagen])
                        <div><strong>{{ $item->cantidad }} × {{ $item->platillo_nombre }}</strong>
                            @php($mark = $stage === 'preparando' ? ($item->preparacion_iniciada_en ?? null) : ($item->lista_en ?? null))
                            <small>{{ $stage === 'preparando' ? 'Inicio' : 'Listo desde' }} · {{ $fecha($mark) }}
                                @if($mark) · {{ $elapsed($mark) }} @endif
                            </small>
                            @if($stage === 'preparando' && ($item->preparador_nombre ?? null))
                                <small>Preparó {{ trim($item->preparador_nombre.' '.$item->preparador_paterno.' '.$item->preparador_materno) }}</small>
                            @endif
                        </div>
                        @unless($order->allergenHold) @include('kitchen.production.action') @endunless
                    </li>
                @endforeach
            </ul>
            <footer><a class="kp-text-link" href="{{ $detail($order) }}"><x-icon name="chevron"/>Ver detalle</a></footer>
        </article>
    @endforeach
</div>
