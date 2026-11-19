@extends('almacen.workspace.layout')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/warehouse-reception-detail.css') }}">
@endpush
@section('title','Recepción de insumos')
@section('banner','receptions')
@section('banner-title','Revisa la mercancía que ya llegó')
@section('banner-copy','Verifica las capturas de Recepción, atiende las diferencias y confirma únicamente las cantidades aceptadas.')
@section('workspace')
<form method="GET" action="{{ route('receptions.index') }}" class="wh-card wh-filters"><label class="wh-search">Buscar recepción<input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Folio de recepción, pedido o proveedor"></label><label>Estado<select name="estado"><option value="">Todos los estados</option>@foreach($states as $state)<option @selected(($filters['estado'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></label><button class="wh-button secondary">Filtrar</button><a class="wh-clear" href="{{ route('receptions.index') }}">Limpiar</a></form>
<div class="wh-reception-grid"><section class="wh-card wh-reception-list"><header class="wh-card-heading"><h2>Recepciones registradas</h2><span>{{ $receipts->total() }}</span></header>@forelse($receipts as $r)<a class="wh-receipt-choice {{ $selected?->id_recepcion === $r->id_recepcion ? 'selected' : '' }}" href="{{ route('receptions.review.show',array_merge(['receipt'=>$r->id_recepcion],request()->query())) }}"><div><strong>Recepción #{{ $r->id_recepcion }}</strong><span class="wh-badge {{ \App\Support\WarehouseWorkspace::badgeTone($r->estado) }}">{{ $r->estado }}</span></div><h3>{{ $r->proveedor_nombre }}</h3><p>Pedido #{{ $r->id_pedido }} · {{ $r->fecha_recepcion }}</p><small>{{ $r->responsable ?: 'Responsable sin dato' }}</small></a>@empty<p class="wh-empty">No hay recepciones que coincidan con los filtros.</p>@endforelse<div class="wh-pagination">{{ $receipts->links() }}</div></section>
<section class="wh-card wh-reception-detail">@if($selected)
<header class="wh-card-heading"><div><span class="wh-eyebrow">REVISIÓN DE MERCANCÍA</span><h2>Recepción #{{ $selected->id_recepcion }}</h2></div><span class="wh-badge {{ \App\Support\WarehouseWorkspace::badgeTone($selected->estado) }}">{{ $selected->estado }}</span></header>
<dl class="wh-detail-grid"><div><dt>Pedido relacionado</dt><dd><a href="{{ route('orders.show',$selected->id_pedido) }}">#{{ $selected->id_pedido }}</a></dd></div><div><dt>Proveedor</dt><dd>{{ $selected->proveedor_nombre }}</dd></div><div><dt>Fecha de recepción</dt><dd>{{ $selected->fecha_recepcion }}</dd></div><div><dt>Empleado responsable</dt><dd>{{ $selected->responsable }}</dd></div></dl>
@if($selected->observaciones)<p class="wh-note"><strong>Observaciones generales:</strong> {{ $selected->observaciones }}</p>@endif
<h3>Insumos inspeccionados</h3>
@foreach($lines as $line)
@php
    $meta = json_decode($line->observaciones ?? '',true); $meta = is_array($meta) ? $meta : [];
    $capture = $meta['captura'] ?? [];
    $missing = array_key_exists('faltante_entrega',$meta) ? $meta['faltante_entrega'] : null;
    $delivered = max(0, (float) $line->cantidad_entregada);
    $rejected = max(0, round($delivered - (float) $line->cantidad_aceptada, 3));
    $acceptedPercent = $delivered > 0 ? max(0, min(100, (float) $line->cantidad_aceptada / $delivered * 100)) : 0;
    $rejectedPercent = $delivered > 0 ? 100 - $acceptedPercent : 0;
    $unit = $line->unidad ?: $line->unidad_nombre;
    $photo = \App\Support\WarehouseWorkspace::image($line->imagen_url);
@endphp
<details class="wh-reception-line wh-inspected-product" open><summary><span class="wh-inspected-photo"><x-icon name="box"/>@if($photo)<img src="{{ $photo }}" alt="Fotografía de catálogo de {{ $line->nombre }}" loading="lazy" onerror="this.hidden=true">@endif</span><span class="wh-inspected-title"><strong>{{ mb_strtoupper($line->nombre ?? 'Insumo no disponible') }}</strong><small>{{ $line->categoria ?: 'Sin categoría' }} · {{ $line->unidad_nombre ?: $unit ?: 'Unidad sin dato' }}</small></span><span class="wh-badge {{ \App\Support\WarehouseWorkspace::badgeTone($line->resultado_revision) }}">{{ $line->resultado_revision ?: 'Sin resultado' }}</span><span class="wh-inspected-toggle"><x-icon name="chevron"/><small>Ver / ocultar</small></span></summary><div class="wh-reception-line-body"><div class="wh-line-quantities">
@foreach([['Solicitada',$line->solicitada,'info'],['Entregada',$line->cantidad_entregada,'info'],['Aceptada',$line->cantidad_aceptada,'ok'],['Rechazada',$rejected,'danger'],['Faltante',$missing,'waiting']] as [$label,$value,$tone])<div class="{{ $tone }}"><span>{{ $label }}</span><strong>{{ \App\Support\WarehouseWorkspace::quantity($value) }} @if($value !== null)<small>{{ $unit }}</small>@endif</strong></div>@endforeach</div>
<div class="wh-inspection-distribution" role="img" aria-label="{{ $delivered > 0 ? 'Aceptado '.round($acceptedPercent,1).'%, rechazado '.round($rejectedPercent,1).'% de la cantidad entregada' : 'Sin cantidad entregada para calcular porcentajes' }}"><div class="wh-inspection-track"><span class="accepted" style="width:{{ $acceptedPercent }}%"></span><span class="rejected" style="width:{{ $rejectedPercent }}%"></span></div><div class="wh-inspection-legend">@if($delivered > 0)<span>Aceptado <strong>{{ \App\Support\WarehouseWorkspace::quantity(round($acceptedPercent,1)) }}%</strong></span><span>Rechazado <strong>{{ \App\Support\WarehouseWorkspace::quantity(round($rejectedPercent,1)) }}%</strong></span><small>Sobre lo entregado</small>@else<span>Sin cantidad entregada para calcular porcentajes</span>@endif</div></div>
@if($missing === null)<p class="wh-note">Esta captura histórica no conserva el faltante de la entrega. No se calcula a partir del pedido completo.</p>@endif
<dl class="wh-inspected-meta"><div><dt><x-icon name="layers"/>Lote</dt><dd>{{ $line->codigo_lote ?: 'Sin dato' }}</dd></div><div><dt><x-icon name="calendar"/>Caducidad</dt><dd>{{ $line->caducidad ?: 'Sin dato' }}</dd></div></dl>
<div class="wh-inspected-notes"><strong>Observaciones</strong><p>{{ ($capture['observaciones'] ?? (!$meta ? $line->observaciones : null)) ?: 'Sin observaciones' }}</p></div>
@if(!empty($capture['incidencia']))<p class="wh-message danger"><strong>Incidencia:</strong> {{ $capture['incidencia'] }}</p>@endif
@foreach($incidents->where('id_recepcion_detalle',$line->id_recepcion_detalle) as $incident)<div class="wh-message danger"><div><strong>Incidencia #{{ $incident->id_incidencia }} · {{ $incident->estado }}</strong><p>{{ $incident->descripcion }}</p>@if(!empty($incident->respuesta))<p>Respuesta: {{ $incident->respuesta }}</p>@endif</div></div>@endforeach
</div></details>@endforeach
@if($selected->estado === config('receptions.pending'))
<section class="wh-review-actions"><h3>Decisión de almacén</h3>
@if($canConfirm && $locations->isNotEmpty())
<p class="wh-note">Confirma después de revisar todas las partidas. Se ingresarán únicamente las cantidades aceptadas, conservando la relación con esta recepción.</p>
<form method="POST" action="{{ route('receptions.review.confirm',$selected->id_recepcion) }}" class="wh-form" data-review-form data-confirm-message="¿Confirmar esta recepción e ingresar únicamente las cantidades aceptadas al inventario?">@csrf<label>Ubicación de ingreso<select name="id_ubicacion" required><option value="">Selecciona una ubicación de esta sede</option>@foreach($locations as $location)<option value="{{ $location->id_ubicacion_inv }}" @selected((string)old('id_ubicacion') === (string)$location->id_ubicacion_inv)>{{ $location->nombre }}</option>@endforeach</select></label><label>Observación de revisión<textarea name="observacion_revision" required minlength="5" maxlength="1000" rows="3" placeholder="Describe lo que verificaste y las diferencias atendidas">{{ old('observacion_revision') }}</textarea></label><button class="wh-button green" type="submit"><x-icon name="check"/>Confirmar recepción</button></form>
@elseif(!$canConfirm)
<div class="wh-message orange"><p>El esquema de confirmación de recepciones requiere revisión técnica.</p></div>
@else
<div class="wh-message orange"><p>Registra una ubicación activa de esta sede para poder confirmar. <a href="{{ route('warehouse.locations.index') }}">Ir a Ubicaciones de almacén</a>.</p></div>
@endif
@if($canConfirm && collect(config('receptions.released_states',[]))->intersect(['Devuelta para corrección','Cancelada','Rechazada','Rechazada por almacén'])->isNotEmpty())<details class="wh-return"><summary>Devolver para corrección</summary><form class="wh-form" method="POST" action="{{ route('receptions.review.return',$selected->id_recepcion) }}" data-review-form data-confirm-message="¿Devolver esta recepción conservando su historial y sin generar entradas?">@csrf<label>Motivo de devolución<textarea name="motivo" required minlength="10" maxlength="1000" rows="3">{{ old('motivo') }}</textarea><small>Explica qué debe corregir el recepcionista.</small></label><button class="wh-button secondary" type="submit">Devolver recepción</button></form></details>@endif
</section>
@elseif($selected->estado === 'Confirmada')<div class="wh-message ok"><p>Recepción confirmada el {{ $selected->fecha_confirmacion }}. Las entradas conservan su trazabilidad.</p></div><a class="wh-button secondary" href="{{ route('warehouse.movements') }}">Consultar movimientos</a>
@else<p class="wh-note">Esta recepción se conserva como historial con su estado registrado.</p>@endif
@else<p class="wh-empty">Selecciona una recepción para revisar su detalle.</p>@endif</section></div>
@endsection
