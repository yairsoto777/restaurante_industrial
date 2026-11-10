@extends('layouts.app')
@section('title', 'Operación del mesero')
@section('content')
<div class="chef-page">
    <div class="chef-hero"><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Servicio</span><h1>Mi operación</h1><p>Atiende únicamente las zonas asignadas por tu capitán.</p></div></div>
    @if(session('status'))<div class="chef-notice chef-notice-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="chef-notice">{{ $errors->first() }}</div>@endif
    <section class="chef-stat-grid chef-stat-grid-three"><article class="chef-stat"><strong>{{ $zones->count() }}</strong><span>Zonas asignadas</span></article><article class="chef-stat"><strong>{{ $tables->count() }}</strong><span>Mesas asignadas</span></article><article class="chef-stat"><strong>{{ $attentions->whereNotIn('estado', ['Cerrada', 'Cancelada'])->count() }}</strong><span>Atenciones activas</span></article></section>
    <section class="chef-panel" style="display:none"><div class="chef-panel-heading"><div><span class="chef-kicker">Nueva atención</span><h2>Abrir mesa</h2></div></div>
        <form method="POST" action="{{ route('waiter.attention.store') }}" class="chef-filters">@csrf
            <select name="id_mesa" required><option value="">Mesa disponible</option>@foreach($tables as $table)<option value="{{ $table->id_mesa }}" @disabled($table->active_attention_id)>Mesa {{ $table->numero }} · {{ $table->zona }} @if($table->active_attention_id)(ocupada)@endif</option>@endforeach</select>
            <select name="id_servicio" required><option value="">Servicio</option>@foreach($services as $service)<option value="{{ $service->id_servicio }}">{{ $service->nombre }} · {{ $service->fecha }}</option>@endforeach</select>
            <input type="number" name="comensales" min="1" placeholder="Comensales" required><input name="observaciones" placeholder="Observaciones (opcional)"><button class="chef-button chef-button-primary">Abrir atención</button>
        </form>
    </section>
    <section class="chef-panel"><div class="chef-panel-heading"><div><span class="chef-kicker">Administración operativa</span><h2>Crear mesa</h2><p>Solo puedes crear mesas dentro de tus zonas asignadas.</p></div></div>
        <form method="POST" action="{{ route('waiter.table.store') }}" class="chef-filters">@csrf
            <select name="id_zona" required><option value="">Zona asignada</option>@foreach($zones as $zone)<option value="{{ $zone->id_zona }}">{{ $zone->nombre }}</option>@endforeach</select>
            <input type="number" name="numero" min="1" max="9999" placeholder="Número de mesa" required><input type="number" name="capacidad" min="1" max="100" placeholder="Capacidad" required>
            <select name="estado_fisico" required><option value="Disponible">Disponible</option><option value="Mantenimiento">Mantenimiento</option><option value="Fuera de servicio">Fuera de servicio</option></select><input name="observaciones" maxlength="500" placeholder="Observaciones (opcional)">
            <button class="chef-button chef-button-primary" type="submit">Crear mesa</button>
        </form>
    </section>
    <section class="chef-panel"><div class="chef-panel-heading"><div><span class="chef-kicker">Mesas asignadas</span><h2>Tomar orden</h2><p>Crea la mesa arriba y después inicia aquí la atención.</p></div></div><div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Mesa</th><th>Zona</th><th>Capacidad</th><th>Acción</th></tr></thead><tbody>
    @forelse($tables as $table)<tr><td>Mesa {{ $table->numero }}</td><td>{{ $table->zona }}</td><td>{{ $table->capacidad }}</td><td>@if($table->active_attention_id)<span>Ocupada</span>@else<form method="POST" action="{{ route('waiter.attention.store') }}" class="chef-filters">@csrf<select name="id_servicio" required><option value="">Servicio</option>@foreach($services as $service)<option value="{{ $service->id_servicio }}">{{ $service->nombre }}</option>@endforeach</select><input type="hidden" name="id_mesa" value="{{ $table->id_mesa }}"><input type="number" name="comensales" min="1" max="{{ $table->capacidad }}" placeholder="Comensales" required><button class="chef-button chef-button-primary" type="submit">Tomar orden</button></form>@endif</td></tr>@empty<tr><td colspan="4">No tienes mesas asignadas.</td></tr>@endforelse
    </tbody></table></div></section>
    <section class="chef-panel"><div class="chef-panel-heading"><div><span class="chef-kicker">Seguimiento</span><h2>Mis atenciones</h2></div></div><div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Servicio</th><th>Mesa</th><th>Comensales</th><th>Estado</th><th>Comanda</th></tr></thead><tbody>
    @forelse($attentions as $attention)<tr><td>{{ $attention->servicio }}<small>{{ $attention->fecha }}</small></td><td>Mesa {{ $attention->mesa }}<small>{{ $attention->zona }}</small></td><td>{{ $attention->comensales }}</td><td>{{ $attention->estado }}</td><td>
        @if(!$attention->id_comanda)<form method="POST" action="{{ route('waiter.comanda.store', $attention->id_atencion) }}">@csrf<button class="chef-button chef-button-primary">Abrir comanda</button></form>@else
        <form method="POST" action="{{ route('waiter.comanda.item.store', $attention->id_atencion) }}">@csrf<select name="id_menu" required><option value="">Platillo</option>@foreach($menus->where('id_servicio', $attention->id_servicio) as $item)<option value="{{ $item->id_menu }}">{{ $item->platillo }} · ${{ number_format($item->precio_venta, 2) }}</option>@endforeach</select><input type="number" name="numero_comensal" min="1" max="{{ $attention->comensales }}" placeholder="# comensal" required><input type="number" name="cantidad" value="1" min="1" max="100" required><input name="observaciones" placeholder="Indicaciones"><button class="chef-button chef-button-primary">Agregar</button></form>@endif
        @if($attention->id_comanda && !in_array($attention->comanda_estado, ['Pendiente de pago', 'Pagada']))<form method="POST" action="{{ route('waiter.comanda.close', $attention->id_atencion) }}" onsubmit="return confirm('¿Cerrar comanda y enviar cuenta a caja?')">@csrf<button class="chef-button chef-button-primary">Cerrar y enviar a caja</button></form>@endif
        </td></tr>@empty<tr><td colspan="5">No tienes atenciones activas.</td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection
