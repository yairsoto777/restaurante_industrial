@if(isset($preparation[$item->state]))
    @php([$target, $label] = $preparation[$item->state])
    <form action="{{ route('kitchen.order-status.update', $item->id_comanda_detalle) }}" method="post" data-kp-action
        @if($target === 'entregado') data-kp-confirm="¿Confirmas el registro de entrega de esta partida?" @endif>
        @csrf @method('PATCH')
        <input type="hidden" name="estado" value="{{ $target }}">
        <input type="hidden" name="volver" value="produccion">
        <button class="kp-button {{ $target === 'preparando' ? 'kp-primary' : 'kp-complete' }}">{{ $label }}</button>
    </form>
@endif
