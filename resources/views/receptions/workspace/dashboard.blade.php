@extends('layouts.app')
@section('title', 'Recepción de insumos · Panel principal')
@section('content')
<div class="rx-page">
    <header class="rx-heading"><div><span class="rx-eyebrow">{{ $site->nombre }} · Recepción de insumos</span><h1>Hola, {{ auth()->user()->nombre }}</h1><p>Tu jornada de recepción, calidad y seguimiento en un solo lugar.</p></div><span class="rx-date"><x-icon name="calendar"/>{{ now('America/Mexico_City')->format('d/m/Y') }}</span></header>
    <section class="rx-banner dashboard-banner dashboard-banner--reception"><div><span>CONTROL DE MERCANCÍA</span><h2>Cada entrega, bien recibida.</h2><p>Verifica los insumos, registra las cantidades reales y documenta cualquier diferencia.</p></div><x-icon name="box"/></section>
    <div class="rx-kpis">
        @foreach([['calendar',$scheduledToday,'Entregas programadas para hoy','Pedidos de la sede','info'],['box',$registered,'Recepciones registradas','Tus registros en esta sede','ok'],['clock',$pending,'Pendientes de revisión','En espera de Almacén','waiting'],['alert',$withIncidents,'Recepciones con incidencias','Con al menos una incidencia','danger']] as [$icon,$value,$label,$caption,$tone])
        <article class="rx-card rx-kpi {{ $tone }}"><span class="rx-kpi-icon"><x-icon :name="$icon"/></span><div><strong>{{ $value ?? '—' }}</strong><h2>{{ $label }}</h2><small>{{ $value === null ? 'Información no disponible' : $caption }}</small></div></article>
        @endforeach
    </div>
    <div class="rx-dashboard-grid">
        <section class="rx-card"><div class="rx-card-heading"><h2><x-icon name="chart"/>Recepciones de la semana</h2><span>Lunes a domingo</span></div><p class="rx-muted">Tus recepciones registradas · {{ $week->first()['date'] }} al {{ $week->last()['date'] }}</p>
            @if($available)<div class="rx-bars" role="img" aria-label="Recepciones por día de la semana: {{ $week->map(fn ($d) => $d['label'].': '.$d['total'])->implode(', ') }}">
                @foreach($week as $day)<div><strong>{{ $day['total'] }}</strong><div class="rx-bar-track"><i style="height:{{ $day['total'] / max(1,$week->max('total')) * 100 }}%"></i></div><span>{{ $day['label'] }}</span><small>{{ $day['date'] }}</small></div>@endforeach
            </div>@else<p class="rx-empty">Las recepciones aún no están disponibles para consulta.</p>@endif
        </section>
        <section class="rx-card"><div class="rx-card-heading"><h2><x-icon name="activity"/>Estado de recepciones</h2></div><p class="rx-muted">Distribución de tus registros en la sede</p>
            @php
                $total = $distribution->sum('total'); $cursor = 0; $stops = [];
                foreach ($distribution as $entry) { $end = $cursor + ($total ? $entry->total / $total * 100 : 0); $stops[] = 'var(--rx-'.\App\Support\ReceptionWorkspace::tone($entry->estado).') '.$cursor.'% '.$end.'%'; $cursor = $end; }
            @endphp
            @if($total)<div class="rx-distribution"><div class="rx-donut" style="background:conic-gradient({{ implode(',',$stops) }})" role="img" aria-label="{{ $total }} recepciones; desglose en la leyenda"><div><strong>{{ $total }}</strong><span>recepciones</span></div></div><ul class="rx-legend">@foreach($distribution as $entry)<li><i class="{{ \App\Support\ReceptionWorkspace::tone($entry->estado) }}"></i><span>{{ $entry->estado ?: 'Sin estado' }}</span><strong>{{ $entry->total }}</strong></li>@endforeach</ul></div>
            @else<p class="rx-empty">{{ $available ? 'Tu primera recepción aparecerá aquí.' : 'Información no disponible.' }}</p>@endif
        </section>
        <section class="rx-card"><div class="rx-card-heading"><h2><x-icon name="calendar"/>Próximas entregas</h2><a href="{{ route('receptions.index') }}">Ver todas →</a></div>
            @forelse($upcoming as $order)<a class="rx-list-item" href="{{ route('receptions.orders.show',$order->id_pedido) }}"><span class="rx-list-icon info"><x-icon name="box"/></span><div><strong>Pedido #{{ $order->id_pedido }} · {{ $order->proveedor_nombre }}</strong><small>{{ $order->fecha_entrega ?: 'Fecha por definir' }}</small></div><x-icon name="chevron"/></a>@empty<p class="rx-empty">No hay próximas entregas programadas.</p>@endforelse
        </section>
        <section class="rx-card"><div class="rx-card-heading"><h2><x-icon name="layers"/>Últimas recepciones</h2><a href="{{ route('receptions.history') }}">Ver historial →</a></div><div class="rx-table-wrap"><table><thead><tr><th>Folio / proveedor</th><th>Fecha</th><th>Estado</th></tr></thead><tbody>
            @forelse($recent as $receipt)<tr><td><a href="{{ route('receptions.receipts.show',$receipt->id_recepcion) }}">#{{ $receipt->id_recepcion }}</a><small>{{ $receipt->proveedor_nombre }}</small></td><td>{{ $receipt->fecha_recepcion }}</td><td>@include('receptions.workspace.status',['state'=>$receipt->estado])</td></tr>@empty<tr><td colspan="3" class="rx-empty">Todavía no has registrado recepciones.</td></tr>@endforelse
        </tbody></table></div></section>
        <section class="rx-card"><div class="rx-card-heading"><h2><x-icon name="activity"/>Actividad reciente</h2></div>
            @forelse($activity as $event)<a class="rx-list-item" href="{{ $event['url'] }}"><span class="rx-list-icon {{ $event['tone'] }}"><x-icon name="activity"/></span><div><strong>{{ $event['title'] }}</strong><p>{{ $event['description'] }}</p><small>{{ $event['date'] }}</small></div></a>@empty<p class="rx-empty">La actividad de tus recepciones aparecerá aquí.</p>@endforelse
        </section>
        <section class="rx-card"><div class="rx-card-heading"><h2><x-icon name="layers"/>Acciones rápidas</h2></div><div class="rx-quick">
            @foreach([['calendar','receptions.index','Entregas programadas','info'],['plus','receptions.register','Registrar recepción','ok'],['layers','receptions.history','Consultar historial','waiting'],['alert','incidents.index','Consultar incidencias','danger']] as [$icon,$route,$label,$tone])<a href="{{ route($route) }}"><span class="rx-list-icon {{ $tone }}"><x-icon :name="$icon"/></span>{{ $label }}<x-icon name="chevron"/></a>@endforeach
        </div></section>
    </div>
</div>
@endsection
