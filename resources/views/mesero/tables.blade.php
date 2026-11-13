@extends('layouts.app')

@section('title', 'Mesas y zonas')

@section('content')
<div class="chef-page waiter-zones-page">
    <div class="chef-hero"><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Operación</span><h1>Mesas y zonas</h1><p>Consulta las mesas y las asignaciones registradas por el capitán de meseros.</p></div></div>
    <section class="chef-stat-grid chef-stat-grid-three">
        <article class="chef-stat">@if(auth()->user()->hasRole('Mesero'))<span class="waiter-stat-icon"><x-icon name="map" /></span><div>@endif<strong>{{ $zones->count() }}</strong><span>Zonas de la sede</span>@if(auth()->user()->hasRole('Mesero'))</div>@endif</article>
        <article class="chef-stat">@if(auth()->user()->hasRole('Mesero'))<span class="waiter-stat-icon"><x-icon name="plates" /></span><div>@endif<strong>{{ $tables->count() }}</strong><span>Mesas registradas</span>@if(auth()->user()->hasRole('Mesero'))</div>@endif</article>
        <article class="chef-stat">@if(auth()->user()->hasRole('Mesero'))<span class="waiter-stat-icon"><x-icon name="users" /></span><div>@endif<strong>{{ $tables->sum('capacidad') }}</strong><span>Capacidad total</span>@if(auth()->user()->hasRole('Mesero'))</div>@endif</article>
    </section>
    @forelse($zones as $zone)
    <section class="chef-panel waiter-zone-panel {{ auth()->user()->hasRole('Mesero') && $assignments->where('id_zona', $zone->id_zona)->contains('id_usuario', auth()->user()->id_usuario) ? 'ops-assigned' : '' }}">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Zona</span><h2>{{ $zone->nombre }}</h2>
            <p>Meseros asignados: {{ $assignments->where('id_zona', $zone->id_zona)->map(fn ($assignment) => trim($assignment->nombre.' '.$assignment->apellido_p))->unique()->implode(', ') ?: 'Sin asignación activa' }}</p>
            @if($assignments->where('id_zona', $zone->id_zona)->contains('id_usuario', auth()->user()->id_usuario))<span class="status">Tu zona asignada</span>@endif
        </div>@if(auth()->user()->hasRole('Mesero') && !auth()->user()->hasRole('Capitan de meseros') && !auth()->user()->hasRole('Cajero'))<span class="waiter-zone-count">{{ $tables->where('id_zona',$zone->id_zona)->count() }} mesas · {{ $tables->where('id_zona',$zone->id_zona)->sum('capacidad') }} lugares</span>@endif</div>
        @if(auth()->user()->hasRole('Mesero'))
            <div class="ops-cards waiter-tables-grid">@foreach($tables->where('id_zona',$zone->id_zona) as $table)<article class="ops-record waiter-table" data-waiter-physical="{{ $table->estado_fisico }}"><div class="ops-record-head"><h3>Mesa {{ $table->numero }}</h3><x-icon name="plates" /></div><x-capitan.table-symbol :number="$table->numero" /><p>{{ $table->capacidad }} lugares · {{ $zone->nombre }}</p><div><small class="ops-note">Estado físico</small><br><x-ops.status :value="$table->estado_fisico" :physical="true" /></div><p class="ops-note">Consulta la ocupación de tus mesas en Mi operación.</p></article>@endforeach</div>
        @endif
        @if(auth()->user()->hasRole('Mesero') && !auth()->user()->hasRole('Capitan de meseros') && !auth()->user()->hasRole('Cajero'))<details class="waiter-zone-list"><summary>Consultar listado de mesas de la zona</summary>@endif
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Mesa</th><th>Capacidad</th><th>Estado físico</th></tr></thead><tbody>
            @forelse($tables->where('id_zona', $zone->id_zona) as $table)
            <tr><td><strong>Mesa {{ $table->numero }}</strong></td><td>{{ $table->capacidad }} comensales</td><td>{{ $table->estado_fisico }}</td></tr>
            @empty<tr><td colspan="3">Esta zona todavía no tiene mesas registradas.</td></tr>@endforelse
        </tbody></table></div>
        @if(auth()->user()->hasRole('Mesero') && !auth()->user()->hasRole('Capitan de meseros') && !auth()->user()->hasRole('Cajero'))</details>@endif
    </section>
    @empty
    <section class="chef-panel"><h2>No hay zonas registradas</h2><p>Las zonas y mesas aparecerán aquí cuando el capitán las registre en esta sede.</p></section>
    @endforelse
</div>
@endsection
