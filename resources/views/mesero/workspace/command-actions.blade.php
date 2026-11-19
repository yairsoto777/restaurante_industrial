@if($a->canOrder)
    <button class="ww-button ww-primary" type="button" data-ww-open="offered-{{ $a->id_atencion }}">
        <x-icon name="utensils"/>Registrar servicio ofrecido
    </button>
@endif
@if($a->canFinalize)
    <button class="ww-button ww-success" type="button" data-ww-open="finalize-{{ $a->id_atencion }}">
        <x-icon name="check"/>Finalizar atención
    </button>
@endif
