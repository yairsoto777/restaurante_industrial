@extends('layouts.app')
@section('title', 'Operación del mesero')
@section('content')
<div class="chef-page waiter-operation-page">
    <div class="chef-hero"><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Servicio</span><h1>Mi operación</h1><p>Atiende únicamente las zonas asignadas por tu capitán.</p></div></div>
    @if(session('status'))<div class="chef-notice chef-notice-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="chef-notice">{{ $errors->first() }}</div>@endif
    <section class="chef-stat-grid chef-stat-grid-three"><article class="chef-stat"><span class="waiter-stat-icon"><x-icon name="map" /></span><div><strong>{{ $zones->count() }}</strong><span>Zonas asignadas</span></div></article><article class="chef-stat"><span class="waiter-stat-icon"><x-icon name="plates" /></span><div><strong>{{ $tables->count() }}</strong><span>Mesas asignadas</span></div></article><article class="chef-stat"><span class="waiter-stat-icon waiter-stat-warning"><x-icon name="activity" /></span><div><strong>{{ $attentions->whereNotIn('estado', ['Cerrada', 'Cancelada'])->count() }}</strong><span>Atenciones activas</span></div></article></section>

    <nav class="ops-quick-actions" aria-label="Acciones del mesero"><strong>Tu jornada de servicio</strong><a class="button primary" href="#mesas-asignadas">Atender mis mesas</a><a class="button" href="#servicios-ofrecidos">Servicios ofrecidos</a><a class="button" href="{{ route('services.index') }}">Consultar servicios</a></nav>
    @include('mesero.partials.assigned-tables')

    <details class="ops-disclosure" @if($errors->hasAny(['id_zona','numero','capacidad','estado_fisico','observaciones'])) open @endif><summary>Crear mesa en una zona asignada</summary>
    <section class="chef-panel"><div class="chef-panel-heading"><div><span class="chef-kicker">Administración operativa</span><h2>Crear mesa</h2><p>Solo puedes crear mesas dentro de tus zonas asignadas.</p></div></div>
        <form method="POST" action="{{ route('waiter.table.store') }}" class="chef-filters">@csrf
            <select name="id_zona" required><option value="">Zona asignada</option>@foreach($zones as $zone)<option value="{{ $zone->id_zona }}">{{ $zone->nombre }}</option>@endforeach</select>
            <input type="number" name="numero" min="1" max="9999" placeholder="Número de mesa" required><input type="number" name="capacidad" min="1" max="100" placeholder="Capacidad" required>
            <select name="estado_fisico" required><option value="Disponible">Disponible</option><option value="Mantenimiento">Mantenimiento</option><option value="Fuera de servicio">Fuera de servicio</option></select><input name="observaciones" maxlength="500" placeholder="Observaciones (opcional)">
            <button class="chef-button chef-button-primary" type="submit">Crear mesa</button>
        </form>
    </section></details>

    @include('mesero.partials.attention-summary')
    <section class="chef-panel" id="servicios-ofrecidos"><div class="chef-panel-heading"><div><span class="chef-kicker">Registro y cobro</span><h2>Servicios ofrecidos</h2><p>Registra lo que recibió el comensal; el total se calcula por persona y se envía a Caja al finalizar.</p></div></div><x-ops.filter target="ops-offered-table" label="Buscar atención, mesa o servicio" /><div class="chef-table-wrap"><table class="chef-table" id="ops-offered-table"><thead><tr><th>Servicio programado / mesa</th><th>Atención</th><th>Servicio ofrecido</th><th>Estado y acciones</th></tr></thead><tbody>
    @forelse($attentions as $attention)
        <tr>
            <td><strong>{{ $attention->servicio_programado }}</strong><small>{{ $attention->fecha }} · Mesa {{ $attention->mesa }} ({{ $attention->zona }})</small></td>
            <td><strong>Atención #{{ $attention->id_atencion }}</strong><small>{{ $attention->comensales }} comensales</small><x-ops.status :value="$attention->estado" /></td>
            <td>
                @if($attention->id_servicio_ofrecido)
                    <strong>{{ $attention->servicio_ofrecido }}</strong><small>{{ $attention->comensales_ofrecidos }} × ${{ number_format((float) $attention->precio_unitario, 2) }} = ${{ number_format((float) $attention->total_ofrecido, 2) }}</small>
                @else
                    <span>Sin registrar</span>
                @endif
            </td>
            <td>
                @if(!$attention->id_servicio_ofrecido && $attention->estado === 'En atención')
                    <form method="POST" action="{{ route('waiter.offered-service.store', $attention->id_atencion) }}" class="chef-filters">@csrf
                        <input name="descripcion" maxlength="160" placeholder="Descripción (ej. Desayuno)" value="{{ old('descripcion', $attention->servicio_programado) }}" required>
                        <input type="number" name="comensales" min="1" max="1000" value="{{ old('comensales', $attention->comensales) }}" required aria-label="Comensales atendidos">
                        <input type="number" name="precio_unitario" min="0.01" step="0.01" placeholder="Precio por persona" value="{{ old('precio_unitario') }}" required aria-label="Precio unitario">
                        <button class="chef-button chef-button-primary" type="submit">Registrar servicio</button>
                    </form>
                @elseif($attention->estado_ofrecido === 'Registrado')
                    <form method="POST" action="{{ route('waiter.offered-service.update', $attention->id_atencion) }}" class="chef-filters">@csrf @method('PUT')
                        <input name="descripcion" maxlength="160" value="{{ $attention->servicio_ofrecido }}" required>
                        <input type="number" name="comensales" min="1" max="1000" value="{{ $attention->comensales_ofrecidos }}" required aria-label="Comensales atendidos">
                        <input type="number" name="precio_unitario" min="0.01" step="0.01" value="{{ $attention->precio_unitario }}" required aria-label="Precio unitario">
                        <button class="chef-button" type="submit">Actualizar</button>
                    </form>
                    <form method="POST" action="{{ route('waiter.offered-service.finalize', $attention->id_atencion) }}" onsubmit="return confirm('¿Finalizar el servicio ofrecido y enviarlo a Caja?')">@csrf @method('PATCH')<button class="chef-button chef-button-primary" type="submit">Finalizar y enviar a Caja</button></form>
                @elseif($attention->estado_ofrecido === 'Pendiente de cobro')
                    <x-ops.status value="Pendiente de cobro" /><small>En Caja · pendiente de pago</small>
                @elseif($attention->estado_ofrecido === 'Pagado')
                    <x-ops.status value="Pagado" />
                @else
                    <span>{{ $attention->estado_ofrecido ?? 'Atención cerrada' }}</span>
                @endif
            </td>
        </tr>
    @empty<tr><td colspan="4">Todavía no tienes atenciones registradas.</td></tr>@endforelse
    </tbody></table></div></section>
</div>
@endsection
