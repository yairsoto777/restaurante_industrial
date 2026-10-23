@extends('layouts.app')
@section('title', 'Incidencias')
@section('content')
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="incidents-title">
        <div class="chef-hero-icon"><x-icon name="alert" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Cocina</span><h1 id="incidents-title">Incidencias</h1><p>Reporta faltantes o problemas de operación y consulta su estado y respuesta.</p></div>
    </section>

    <section class="chef-stat-grid" aria-label="Resumen de incidencias">
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="alert" /></span><div><strong>{{ $totalCount }}</strong><span>Incidencias registradas</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-indigo"><x-icon name="box" /></span><div><strong>{{ $receiptLinkedCount }}</strong><span>Relacionadas con recepción</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="activity" /></span><div><strong>{{ $responseCount }}</strong><span>Con respuesta registrada</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-green"><x-icon name="check" /></span><div><strong>{{ $closedCount }}</strong><span>Con fecha de cierre</span></div></article>
    </section>

    @if(! $available)
        <section class="chef-panel"><div class="chef-empty"><strong>Incidencias no disponible</strong><p>La tabla de incidencias o su relación con usuarios no está completa en la base conectada.</p></div></section>
    @else
        <section class="chef-panel">
            <div class="chef-panel-heading"><div><span class="chef-kicker">Registro de sede</span><h2>Incidencias registradas</h2></div><span class="chef-count">{{ $incidents->total() }} resultados</span></div>
            <form class="chef-filters" method="GET" action="{{ route('incidents.index') }}">
                <label class="chef-search"><span class="chef-sr-only">Buscar en la descripción</span><x-icon name="search" /><input type="search" name="q" value="{{ $search }}" placeholder="Buscar descripción"></label>
                @if($stateOptions->isNotEmpty())<label><span class="chef-sr-only">Filtrar por estado guardado</span><select name="estado"><option value="">Todos los estados</option>@foreach($stateOptions as $state)<option value="{{ $state }}" @selected($filterState === $state)>{{ $state }}</option>@endforeach</select></label>@endif
                <button class="chef-button chef-button-secondary" type="submit">Filtrar</button>
                @if($search !== '' || $filterState !== '')<a class="chef-link-muted" href="{{ route('incidents.index') }}">Limpiar</a>@endif
            </form>

            <details class="chef-create-panel" id="registrar-incidencia" @if($errors->any()) open @endif>
                <summary><span class="chef-plus">+</span><span><strong>Registrar una incidencia</strong><small>Agrega una descripción y, si aplica, relaciónala con una recepción de esta sede.</small></span></summary>
                <form class="chef-form chef-create-form" method="POST" action="{{ route('incidents.store') }}">
                    @csrf
                    <label class="chef-field chef-field-wide"><span>Descripción</span><textarea name="descripcion" maxlength="5000" rows="4" required placeholder="Describe el problema operativo">{{ old('descripcion') }}</textarea>@error('descripcion')<small class="chef-field-error">{{ $message }}</small>@enderror</label>
                    @if($receivingDetails->isNotEmpty())
                        <label class="chef-field chef-field-wide"><span>Recepción relacionada <small>(opcional)</small></span><select name="id_recepcion_detalle"><option value="">Sin recepción relacionada</option>@foreach($receivingDetails as $detail)<option value="{{ $detail->id_recepcion_detalle }}" @selected((string) old('id_recepcion_detalle') === (string) $detail->id_recepcion_detalle)>{{ $detail->insumo }} · aceptada {{ $detail->cantidad_aceptada ?? 'sin cantidad aceptada' }} · {{ $detail->fecha_recepcion ? \Carbon\Carbon::parse($detail->fecha_recepcion)->format('d/m/Y') : 'sin fecha' }}@if($detail->caducidad) · caduca {{ \Carbon\Carbon::parse($detail->caducidad)->format('d/m/Y') }}@endif</option>@endforeach</select>@error('id_recepcion_detalle')<small class="chef-field-error">{{ $message }}</small>@enderror</label>
                    @endif
                    <div class="chef-form-actions chef-field-wide"><button class="chef-button chef-button-primary" type="submit">Guardar incidencia</button></div>
                </form>
            </details>

            <div class="chef-table-wrap">
                <table class="chef-table">
                    <thead><tr><th>Fecha</th><th>Descripción y recepción</th><th>Registró</th><th>Estado registrado</th><th>Respuesta</th><th>Cierre</th></tr></thead>
                    <tbody>
                    @forelse($incidents as $incident)
                        <tr>
                            <td>{{ $incident->fecha ? \Carbon\Carbon::parse($incident->fecha)->format('d/m/Y H:i') : '—' }}</td>
                            <td><strong>{{ $incident->descripcion }}</strong>@if($incident->insumo_nombre)<small class="chef-cell-note">Recepción · {{ $incident->insumo_nombre }}</small>@endif</td>
                            <td>{{ trim(($incident->usuario_nombre ?? '').' '.($incident->usuario_apellido ?? '')) ?: 'Sin nombre' }}</td>
                            <td>@if($incident->estado === 'Pendiente')<span class="chef-pill chef-pill-amber">Pendiente</span>@elseif($incident->estado)<span class="chef-pill chef-pill-slate">{{ $incident->estado }}</span>@else<span class="chef-pill chef-pill-amber">Sin estado definido</span>@endif</td>
                            <td>{{ $incident->respuesta ?: '—' }}</td>
                            <td>{{ $incident->fecha_cierre ? \Carbon\Carbon::parse($incident->fecha_cierre)->format('d/m/Y H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="alert" /></span><strong>{{ $search || $filterState ? 'No hay incidencias con esos filtros' : 'No hay incidencias registradas' }}</strong><p>{{ $search || $filterState ? 'Prueba con otra búsqueda o estado.' : 'Registra la primera incidencia de la sede con el formulario.' }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($incidents->hasPages())<div class="chef-pagination">{{ $incidents->links() }}</div>@endif
        </section>
    @endif
</div>
<script>if(window.location.hash==='#registrar-incidencia'){const form=document.getElementById('registrar-incidencia');if(form)form.open=true}</script>
@endsection
