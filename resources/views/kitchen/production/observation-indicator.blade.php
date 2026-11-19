@if($order->pendingObservations->isNotEmpty())
    <small class="kp-notes-indicator">
        <x-icon name="alert"/>
        @if($order->allergenHold) Alérgenos por revisar
        @elseif($order->pendingObservations->contains('prioridad', 'urgente')) Aviso urgente por revisar
        @else {{ $order->pendingObservations->count() }} sin revisar
        @endif
    </small>
@elseif($order->notes->isNotEmpty() || $order->observations->isNotEmpty())
    <small class="kp-notes-indicator"><x-icon name="alert"/>Con observaciones</small>
@endif
