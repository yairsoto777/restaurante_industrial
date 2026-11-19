@php
    $priorityOrder = ['alergenos' => 0, 'urgente' => 1, 'normal' => 2];
    $observations = $selected->observations->sort(fn ($a, $b) =>
        ($priorityOrder[$a->prioridad] <=> $priorityOrder[$b->prioridad])
        ?: strcmp($b->creada_en, $a->creada_en)
        ?: ($b->id_observacion <=> $a->id_observacion));
    $preview = fn ($message) => \Illuminate\Support\Str::limit(\Illuminate\Support\Str::squish($message), 110);
@endphp

<h3 id="kp-observations-title">Observaciones y alertas</h3>
@if($selectedActionable && $selected->allergenHold)
    <p class="kp-observation-hold" role="alert"><x-icon name="alert"/>Revisa las alertas de alérgenos para habilitar los cambios de estado.</p>
@endif

@if($observations->isEmpty() && $selected->notes->isEmpty())
    <p class="kp-muted">Sin observaciones registradas.</p>
@else
    <div class="kp-observation-list" role="region" aria-labelledby="kp-observations-title" tabindex="0">
        @foreach($observations as $note)
            <article class="kp-observation-card kp-observation-{{ $note->prioridad }}">
                <div class="kp-observation-labels">
                    <span class="kp-observation-type">
                        <x-icon :name="$note->prioridad === 'normal' ? 'layers' : 'alert'"/>
                        {{ \App\Support\CommandObservations::PRIORITIES[$note->prioridad] }}
                    </span>
                    <span class="kp-badge {{ $note->revisada_en ? 'kp-listo' : 'kp-pendiente' }}">
                        <i aria-hidden="true"></i>{{ $note->revisada_en ? 'Revisada' : 'Pendiente de revisión' }}
                    </span>
                </div>
                <p class="kp-observation-meta">
                    {{ trim($note->author_name.' '.$note->author_first.' '.$note->author_last) ?: 'Usuario #'.$note->id_autor }}
                    · {{ $fecha($note->creada_en) }}
                </p>
                <p class="kp-observation-preview">{{ $preview($note->observacion) }}</p>
                <details class="kp-observation-expand">
                    <summary><span>Ver detalle</span><span>Ocultar detalle</span><x-icon name="chevron"/></summary>
                    <p class="kp-observation-message">{{ $note->observacion }}</p>
                    @if($note->revisada_en)
                        <p class="kp-observation-meta">Revisada por {{ trim($note->reviewer_name.' '.$note->reviewer_first.' '.$note->reviewer_last) ?: 'usuario #'.$note->id_revisor }}
                            · {{ $fecha($note->revisada_en) }}</p>
                    @elseif(!$history)
                        <form method="post" action="{{ route('kitchen.observations.acknowledge', [$selected->id_comanda, $note->id_observacion]) }}" data-kp-action>
                            @csrf @method('PATCH')
                            @if($note->prioridad === 'alergenos')
                                <label class="kp-review-confirmation">
                                    <input type="checkbox" name="confirmada" value="1" required>
                                    Confirmo que revisé la alerta y las indicaciones antes de continuar.
                                </label>
                            @endif
                            <button class="kp-button {{ $note->prioridad === 'alergenos' ? 'kp-observation-review' : '' }}" type="submit">
                                {{ $note->prioridad === 'alergenos' ? 'Confirmar revisión de alérgenos' : 'Confirmar revisión' }}
                            </button>
                        </form>
                    @endif
                </details>
            </article>
        @endforeach

        @foreach($selected->notes as $note)
            <article class="kp-observation-card">
                <div class="kp-observation-labels"><span class="kp-observation-type"><x-icon name="layers"/>Observación original</span></div>
                <p class="kp-observation-preview">{{ $preview($note) }}</p>
                <details class="kp-observation-expand">
                    <summary><span>Ver detalle</span><span>Ocultar detalle</span><x-icon name="chevron"/></summary>
                    <p class="kp-observation-message">{{ $note }}</p>
                </details>
            </article>
        @endforeach
    </div>
@endif
