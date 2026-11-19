<dialog class="ww-dialog ww-command" id="offered-{{ $a->id_atencion }}" aria-labelledby="command-title-{{ $a->id_atencion }}" data-ww-command>
    <header>
        <x-icon name="utensils"/>
        <div>
            <h2 id="command-title-{{ $a->id_atencion }}">Registrar comanda</h2>
            <small>Mesa {{ $a->numero }} · {{ $a->zona }} · {{ $a->servicio }} · Atención #{{ $a->id_atencion }}</small>
        </div>
        <button type="button" data-ww-close aria-label="Cerrar">×</button>
    </header>
    <form action="{{ route('waiter.command.save', $a->id_atencion) }}" method="POST">
        @csrf
        <div class="ww-command-grid ww-form-body">
            <section class="ww-command-catalog">
                <h3>Catálogo de platillos</h3>
                <div class="ww-command-filters">
                    <label>Buscar platillo<input type="search" data-command-search placeholder="Nombre o descripción"></label>
                    <label>Categoría<select data-command-category>
                        <option value="">Todas las categorías</option>
                        @foreach($commandMenu->filter(fn ($dish) => $dish->categoria)->unique('id_categoria')->sortBy('categoria') as $dish)
                            <option value="{{ $dish->id_categoria }}">{{ $dish->categoria }}</option>
                        @endforeach
                    </select></label>
                </div>
                <div class="ww-command-dishes">
                    @foreach($commandMenu as $dish)
                        <article class="ww-command-dish" data-command-dish="{{ $dish->id_menu }}"
                            data-name="{{ $dish->nombre }}" data-description="{{ $dish->descripcion }}"
                            data-category="{{ $dish->id_categoria }}" data-cents="{{ (int) round($dish->precio_venta * 100) }}">
                            <div class="ww-command-photo">
                                @if($dish->imagen_url && (!str_contains($dish->imagen_url, ':') || preg_match('~^https?://~i', $dish->imagen_url)))
                                    <img src="{{ \App\Support\ChefWorkspace::image($dish->imagen_url) }}" alt="" loading="lazy">
                                @else
                                    <x-icon name="dish"/>
                                @endif
                            </div>
                            <div class="ww-command-dish-body">
                                <span class="ww-badge ww-green">Disponible</span>
                                <h4>{{ $dish->nombre }}</h4>
                                @if($dish->descripcion)<p>{{ \Illuminate\Support\Str::limit($dish->descripcion, 105) }}</p>@endif
                                <div><strong>${{ number_format($dish->precio_venta, 2) }}</strong>
                                <button class="ww-button ww-command-add" data-command-add type="button" aria-label="Agregar {{ $dish->nombre }}">+ Agregar</button></div>
                            </div>
                        </article>
                    @endforeach
                </div>
                <p class="ww-empty" data-command-empty @if($commandMenu->isNotEmpty()) hidden @endif>
                    {{ $commandMenu->isEmpty() ? 'La jornada no tiene platillos publicados disponibles.' : 'No hay platillos que coincidan con la búsqueda.' }}
                </p>
            </section>
            <section class="ww-command-order">
                <h3>Tu comanda</h3>
                <p class="ww-command-context">Mesa {{ $a->numero }} · {{ $a->comensales }} comensales<br>{{ $a->servicio }}</p>
                <div data-command-lines></div>
                <p class="ww-empty" data-command-no-lines>Agrega platillos para comenzar.</p>
                <div class="ww-command-totals" aria-live="polite">
                    <div><span>Subtotal</span><span data-command-subtotal>$0.00</span></div>
                    <div><strong>Total</strong><strong data-command-total>$0.00 MXN</strong></div>
                </div>
                @if(!empty($a->observaciones))
                    <div class="ww-observations"><strong>Observaciones de la atención</strong><p>{{ $a->observaciones }}</p></div>
                @endif
                <label>Observaciones y notas para cocina
                    <textarea name="notes" rows="3" maxlength="1000" placeholder="Alergias informadas e indicaciones de preparación">{{ $a->commandDraft['notes'] ?? '' }}</textarea>
                </label>
            </section>
        </div>
        <div class="ww-form-errors" data-command-error role="alert" hidden></div>
        <footer>
            <strong class="ww-command-mobile-total" data-command-mobile-total>$0.00 MXN</strong>
            <span data-command-status role="status"></span>
            <button class="ww-button" type="submit" value="draft">Guardar borrador</button>
            <button class="ww-button ww-primary" type="submit" value="send" disabled><x-icon name="check"/>Enviar a cocina</button>
        </footer>
        <script type="application/json" data-command-draft>@json($a->commandDraft['items'] ?? [])</script>
    </form>
</dialog>
