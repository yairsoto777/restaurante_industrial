<div class="ops-section-title"><h2>Seguimiento de mis atenciones</h2><small>Vista rápida · hasta 4 registros</small></div>
<div class="ops-cards waiter-attention-cards">
    @forelse($attentions->take(4) as $attentionPreview)
        <article class="ops-record" data-waiter-state="{{ $attentionPreview->estado_ofrecido ?: $attentionPreview->estado }}">
            <div class="ops-record-head"><h3>Atención #{{ $attentionPreview->id_atencion }}</h3><x-ops.status :value="$attentionPreview->estado_ofrecido ?: $attentionPreview->estado" /></div>
            <p><strong>Mesa {{ $attentionPreview->mesa }} · {{ $attentionPreview->zona }}</strong><br>{{ $attentionPreview->servicio_programado }} · {{ $attentionPreview->fecha }}</p>
            <p>{{ $attentionPreview->comensales }} comensales</p>
            @if($attentionPreview->id_servicio_ofrecido)<p>{{ $attentionPreview->servicio_ofrecido }}<br><strong>${{ number_format((float)$attentionPreview->total_ofrecido,2) }}</strong></p>@else<p>Sin servicio ofrecido registrado.</p>@endif
            <a class="button" href="#servicios-ofrecidos">Ver registro y acciones</a>
        </article>
    @empty<x-ops.empty title="Sin atenciones registradas" message="Selecciona una mesa asignada e inicia la atención con el formulario existente." icon="plates" />@endforelse
</div>
