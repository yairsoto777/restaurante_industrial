<dialog class="kp-detail" open data-kp-detail aria-labelledby="kp-detail-title">
    <header>
        <div><h2 id="kp-detail-title">Comanda #{{ $selected->id_comanda }}</h2>
            <p>Mesa {{ $selected->mesa_numero }} · {{ $selected->zona_nombre }} · {{ $selected->servicio_nombre }}</p></div>
        @include('kitchen.production.badge', ['state' => $selected->state])
        <a class="kp-close" href="{{ $back }}" data-kp-close aria-label="Cerrar detalle">×</a>
    </header>
    <div class="kp-detail-body">
        @if(session('status'))<div class="kp-feedback" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="kp-feedback kp-danger" role="alert">{{ $errors->first() }}</div>@endif
        @if(isset($preparation[$selected->state]) || $selected->state === 'entregado')
            <ol class="kp-progress" aria-label="Progreso de la comanda">
                @php($progress = ['pendiente','preparando','listo','entregado'])
                @foreach($progress as $step)
                    <li class="{{ array_search($step, $progress, true) <= array_search($selected->state, $progress, true) ? 'reached' : '' }}"
                        @if($step === $selected->state) aria-current="step" @endif><i></i>{{ $states[$step] }}</li>
                @endforeach
            </ol>
        @endif
        <div class="kp-detail-grid">
            <section>
                <h3>Información general</h3>
                <dl class="kp-detail-facts">
                    <div><dt>Atención</dt><dd>#{{ $selected->id_atencion }}</dd></div>
                    <div><dt>Comensales</dt><dd>{{ $selected->comensales ?? 'Sin registro' }}</dd></div>
                    <div><dt>Mesero</dt><dd>{{ $selected->waiter ?: 'Sin registro' }}</dd></div>
                    <div><dt>Recepción</dt><dd>{{ $fecha($selected->fecha_apertura) }}</dd></div>
                </dl>
                @include('kitchen.production.observation-history')
            </section>
            <section>
                <h3>Platillos solicitados <span>{{ $selected->quantity }} porciones</span></h3>
                <ul class="kp-detail-lines">
                    @foreach($selected->items as $item)
                        <li>
                            @include('kitchen.production.photo', ['image' => $item->platillo_imagen])
                            <div>
                                <strong>{{ $item->cantidad }} × {{ $item->platillo_nombre }}</strong>
                                @include('kitchen.production.badge', ['state' => $item->state])
                                @if(filled($item->detalle_observaciones))
                                    <details class="kp-observation-expand kp-item-note">
                                        <summary>Ver nota del platillo<x-icon name="chevron"/></summary>
                                        <p class="kp-observation-message">{{ $item->detalle_observaciones }}</p>
                                    </details>
                                @endif
                                @if($history || !empty($item->preparacion_iniciada_en) || !empty($item->lista_en) || $item->delivery)
                                    <dl class="kp-item-trace">
                                        <div><dt>Inicio</dt><dd>{{ $fecha($item->preparacion_iniciada_en ?? null) }}
                                            @if($item->preparador_nombre ?? null) · {{ trim($item->preparador_nombre.' '.$item->preparador_paterno.' '.$item->preparador_materno) }} @endif</dd></div>
                                        <div><dt>Listo</dt><dd>{{ $fecha($item->lista_en ?? null) }}
                                            @if($item->finalizador_nombre ?? null) · {{ trim($item->finalizador_nombre.' '.$item->finalizador_paterno.' '.$item->finalizador_materno) }} @endif</dd></div>
                                        <div><dt>Entrega</dt><dd>{{ $fecha($item->delivery?->fecha_entrega) }}
                                            @if($item->delivery?->nombre) · {{ trim($item->delivery->nombre.' '.$item->delivery->apellido_p.' '.$item->delivery->apellido_m) }} @endif</dd></div>
                                    </dl>
                                @endif
                                @if($selectedActionable && !$selected->allergenHold) @include('kitchen.production.action') @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    </div>
    <footer><a class="kp-button" href="{{ $back }}" data-kp-close>Cerrar</a></footer>
</dialog>
