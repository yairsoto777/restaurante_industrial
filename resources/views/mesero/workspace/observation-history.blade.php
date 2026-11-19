@if($a->commandNotes->isNotEmpty())
    <section aria-label="Historial de observaciones">
        <h3>Observaciones enviadas</h3>
        @foreach($a->commandNotes as $note)
            <div class="ww-observations ww-command-observation {{ $note->prioridad === 'normal' ? 'ww-blue' : 'ww-red' }}">
                <strong>{{ \App\Support\CommandObservations::PRIORITIES[$note->prioridad] }}</strong>
                <p>{{ $note->observacion }}</p>
                <small>{{ trim($note->author_name.' '.$note->author_first.' '.$note->author_last) ?: 'Usuario #'.$note->id_autor }} · {{ \Illuminate\Support\Carbon::parse($note->creada_en)->format('d/m/Y H:i') }}</small>
                <small>
                    @if($note->revisada_en)
                        Revisada por {{ trim($note->reviewer_name.' '.$note->reviewer_first.' '.$note->reviewer_last) ?: 'usuario #'.$note->id_revisor }}
                        · {{ \Illuminate\Support\Carbon::parse($note->revisada_en)->format('d/m/Y H:i') }}
                    @else Pendiente de revisión por Cocina @endif
                </small>
            </div>
        @endforeach
    </section>
@endif
