@extends('layouts.app')

@section('title', 'Mesas y zonas')

@section('content')
<div class="chef-page">
    <div class="chef-hero"><div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Operación</span><h1>Mesas y zonas</h1><p>Consulta las mesas y las asignaciones registradas por el capitán de meseros.</p></div></div>
    <section class="chef-stat-grid chef-stat-grid-three">
        <article class="chef-stat"><strong>{{ $zones->count() }}</strong><span>Zonas de la sede</span></article>
        <article class="chef-stat"><strong>{{ $tables->count() }}</strong><span>Mesas registradas</span></article>
        <article class="chef-stat"><strong>{{ $tables->sum('capacidad') }}</strong><span>Capacidad total</span></article>
    </section>
    @forelse($zones as $zone)
    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Zona</span><h2>{{ $zone->nombre }}</h2>
            <p>Meseros asignados: {{ $assignments->where('id_zona', $zone->id_zona)->map(fn ($assignment) => trim($assignment->nombre.' '.$assignment->apellido_p))->unique()->implode(', ') ?: 'Sin asignación activa' }}</p>
            @if($assignments->where('id_zona', $zone->id_zona)->contains('id_usuario', auth()->user()->id_usuario))<span class="status">Tu zona asignada</span>@endif
        </div></div>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Mesa</th><th>Capacidad</th><th>Estado físico</th></tr></thead><tbody>
            @forelse($tables->where('id_zona', $zone->id_zona) as $table)
            <tr><td><strong>Mesa {{ $table->numero }}</strong></td><td>{{ $table->capacidad }} comensales</td><td>{{ $table->estado_fisico }}</td></tr>
            @empty<tr><td colspan="3">Esta zona todavía no tiene mesas registradas.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @empty
    <section class="chef-panel"><h2>No hay zonas registradas</h2><p>Las zonas y mesas aparecerán aquí cuando el capitán las registre en esta sede.</p></section>
    @endforelse
</div>
@endsection
