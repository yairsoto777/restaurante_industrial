<dialog class="ww-dialog ww-detail-dialog" id="command-note-{{ $a->command->id_comanda }}" aria-labelledby="command-note-title-{{ $a->command->id_comanda }}">
    <header>
        <x-icon name="bell"/>
        <div><small>COMANDA #{{ $a->command->id_comanda }} · MESA {{ $a->numero }}</small>
            <h2 id="command-note-title-{{ $a->command->id_comanda }}">Agregar observación</h2></div>
        <button type="button" data-ww-open="detail-{{ $a->id_atencion }}" aria-label="Cerrar">×</button>
    </header>
    <form method="post" action="{{ route('waiter.command.observations.store', $a->command->id_comanda) }}" data-ww-form>
        @csrf
        <input type="hidden" name="solicitud" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="ww-form-body">
            <div class="ww-form-errors" role="alert" hidden></div>
            <div class="ww-summary-row"><span>Servicio</span><strong>{{ $a->servicio }}</strong></div>
            <div class="ww-summary-row"><span>Estado de las partidas</span>
                <strong>{{ $a->commandLines->where('id_comanda', $a->command->id_comanda)->pluck('estado')->unique()->map(fn ($state) => ucfirst($state ?? 'Sin registro'))->implode(' · ') }}</strong>
            </div>
            @foreach(collect([$a->observaciones ?? null, $a->command->observaciones ?? null])
                ->merge($a->commandLines->where('id_comanda', $a->command->id_comanda)->pluck('observaciones'))->filter()->unique() as $original)
                <div class="ww-observations"><strong>Observación existente</strong><p>{{ $original }}</p></div>
            @endforeach
            @include('mesero.workspace.observation-history')
            @unless($commandNotesSupported)
                <div class="ww-notice ww-amber" role="status">El registro de nuevas observaciones se habilitará al aplicar la migración pendiente.</div>
            @endunless
            <label>Prioridad
                <select name="prioridad" @disabled(!$commandNotesSupported)>
                    @foreach(\App\Support\CommandObservations::PRIORITIES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Nueva observación
                <textarea name="observacion" rows="4" maxlength="1000" required @disabled(!$commandNotesSupported)
                    placeholder="Escribe la indicación o aclara una observación anterior"></textarea>
            </label>
        </div>
        <footer>
            <button class="ww-button" type="button" data-ww-open="detail-{{ $a->id_atencion }}">Volver</button>
            <button class="ww-button ww-primary" type="submit" @disabled(!$commandNotesSupported)>Guardar y notificar a cocina</button>
        </footer>
    </form>
</dialog>
