@extends('layouts.app')
@section('title', 'Reportes')
@section('content')
<div class="chef-page">
    <section class="chef-hero" aria-labelledby="reports-title">
        <div class="chef-hero-icon"><x-icon name="chart" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">{{ $site->nombre }} · Consulta gerencial</span><h1 id="reports-title">Reportes</h1><p>Consulta indicadores operativos de la sede seleccionada.</p></div>
    </section>
    <section class="chef-stat-grid" aria-label="Resumen de reportes">
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-blue"><x-icon name="box" /></span><div><strong>{{ $orders->count() }}</strong><span>Pedidos registrados</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-indigo"><x-icon name="activity" /></span><div><strong>{{ $ordersByState->get('Programado', 0) }}</strong><span>Pedidos programados</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-amber"><x-icon name="alert" /></span><div><strong>{{ $openIncidents }}</strong><span>Incidencias abiertas</span></div></article>
        <article class="chef-stat"><span class="chef-stat-icon chef-stat-green"><x-icon name="check" /></span><div><strong>{{ $incidents->count() - $openIncidents }}</strong><span>Incidencias cerradas</span></div></article>
    </section>
    <section class="chef-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Indicadores por estado</span><h2>Pedidos de la sede</h2></div></div>
        <div class="chef-table-wrap"><table class="chef-table"><thead><tr><th>Estado</th><th>Total</th></tr></thead><tbody>@forelse($ordersByState as $state => $total)<tr><td>{{ $state }}</td><td><strong>{{ $total }}</strong></td></tr>@empty<tr><td colspan="2">No hay pedidos registrados.</td></tr>@endforelse</tbody></table></div>
    </section>
</div>
@endsection
