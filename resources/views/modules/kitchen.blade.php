@extends('layouts.app')
@section('title', 'Operación de cocina')
@section('content')
@php($canAdvance = auth()->user()->hasRole('Personal de cocina'))
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="kitchen-title">
        <div class="chef-hero-icon"><x-icon name="utensils" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Cocina</span><h1 id="kitchen-title">Operación de cocina</h1><p>Consulta comandas de servicios aprobados y actualiza el avance de cada platillo.</p></div>
        <div class="chef-hero-actions"><a class="chef-button chef-button-secondary" href="{{ route('services.live') }}"><x-icon name="activity" />Servicio en vivo</a><a class="chef-button chef-button-secondary" href="{{ route('groups.index') }}"><x-icon name="users" />Grupos y entregas</a></div>
    </section>

    <section class="chef-stat-grid chef-stat-grid-three" aria-label="Resumen de comandas">
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="plates" /></span><div><strong>{{ $totalCount }}</strong><span>Renglones de comanda</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="clock" /></span><div><strong>{{ $pendingCount }}</strong><span>Con estado pendiente</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-green"><x-icon name="dish" /></span><div><strong>{{ number_format((int) $totalQuantity) }}</strong><span>Platillos solicitados</span></div></article>
    </section>

    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Servicios aprobados y operativos</span><h2>Comandas de cocina</h2></div><span class="chef-count">@if($available){{ $orders->total() }}@else 0 @endif resultados</span></div>
        @if(! $available)
            <div class="chef-empty"><strong>El tablero no está disponible</strong><p>Se requieren comandas, detalles, atenciones, servicios, menús, platillos, mesas y zonas en el esquema.</p></div>
        @else
            <form class="chef-filters" method="GET" action="{{ route('kitchen.index') }}">
                <label class="chef-search"><span class="chef-sr-only">Buscar platillo o servicio</span><x-icon name="search" /><input type="search" name="q" value="{{ $search }}" placeholder="Buscar platillo o servicio"></label>
                <label><span class="chef-sr-only">Filtrar por estado</span><select name="estado"><option value="">Todos los estados</option>@foreach($stateOptions as $state)<option value="{{ $state }}" @selected($filterState === $state)>{{ ucfirst($state) }}</option>@endforeach</select></label>
                <button class="chef-button chef-button-secondary" type="submit">Filtrar</button>
                @if($search !== '' || $filterState !== '')<a class="chef-link-muted" href="{{ route('kitchen.index') }}">Limpiar</a>@endif
            </form>
            @if($canAdvance)<div class="chef-notice"><x-icon name="activity" /><span>Avanza cada platillo en orden: pendiente, preparando, listo y entregado. Al marcarlo entregado se registra con su atención, menú, cantidad, fecha y usuario.</span></div>@endif
            <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Platillo</th><th>Servicio</th><th>Mesa</th><th>Fecha de apertura</th><th>Cantidad</th><th>Estado</th>@if($canAdvance)<th>Acción</th>@endif</tr></thead><tbody>
                @forelse($orders as $order)
                    @php($state = strtolower((string) $order->detalle_estado))
                    @php($nextState = ['pendiente' => 'preparando', 'preparando' => 'listo', 'listo' => 'entregado'][$state] ?? null)
                    <tr>
                        <td><strong>{{ $order->platillo_nombre }}</strong>@if($order->detalle_observaciones)<small class="chef-cell-note">{{ $order->detalle_observaciones }}</small>@endif</td>
                        <td>{{ $order->servicio_nombre }}<small class="chef-cell-note">{{ $order->servicio_fecha ? \Carbon\Carbon::parse($order->servicio_fecha)->format('d/m/Y') : '' }}</small></td>
                        <td>{{ $order->zona_nombre ? $order->zona_nombre.' · ' : '' }}Mesa {{ $order->mesa_numero }}</td>
                        <td>{{ $order->fecha_apertura ? \Carbon\Carbon::parse($order->fecha_apertura)->format('d/m/Y H:i') : '—' }}</td>
                        <td>{{ number_format((int) $order->cantidad) }}</td>
                        <td>
                            @if($state === 'pendiente')<span class="chef-pill chef-pill-amber">Pendiente</span>
                            @elseif($state === 'preparando')<span class="chef-pill chef-pill-blue">Preparando</span>
                            @elseif($state === 'listo')<span class="chef-pill chef-pill-green">Listo</span>
                            @elseif($state === 'entregado')<span class="chef-pill chef-pill-slate">Entregado</span>
                            @else<span class="chef-pill chef-pill-slate">{{ $order->detalle_estado ?: 'Sin estado' }}</span>@endif
                        </td>
                        @if($canAdvance)<td>
                            @if($nextState)
                                <form method="POST" action="{{ route('kitchen.order-status.update', $order->id_comanda_detalle) }}" @if($nextState === 'entregado')data-confirm="¿Confirmas la entrega de {{ $order->platillo_nombre }}? Se guardará en el historial."@endif>
                                    @csrf @method('PATCH')<input type="hidden" name="estado" value="{{ $nextState }}">
                                    <button class="chef-button chef-button-small {{ $nextState === 'entregado' ? 'chef-button-primary' : 'chef-button-secondary' }}" type="submit">{{ ['preparando' => 'Iniciar preparación', 'listo' => 'Marcar listo', 'entregado' => 'Registrar entrega'][$nextState] }}</button>
                                </form>
                            @else<span class="chef-muted">Sin acciones</span>@endif
                        </td>@endif
                    </tr>
                @empty<tr><td colspan="{{ $canAdvance ? 7 : 6 }}"><div class="chef-empty"><span class="chef-empty-icon"><x-icon name="utensils" /></span><strong>{{ $search || $filterState ? 'No hay comandas con esos filtros' : 'No hay comandas para consultar' }}</strong><p>{{ $search || $filterState ? 'Prueba con otra búsqueda o estado.' : 'Las comandas relacionadas con servicios aprobados y operativos de esta sede aparecerán aquí.' }}</p></div></td></tr>@endforelse
            </tbody></table></div>
            @if($orders->hasPages())<div class="chef-pagination">{{ $orders->links() }}</div>@endif
        @endif
    </section>
</div>
<script>document.querySelectorAll('form[data-confirm]').forEach((form)=>form.addEventListener('submit',(event)=>{if(!window.confirm(form.dataset.confirm))event.preventDefault()}));</script>
@endsection
