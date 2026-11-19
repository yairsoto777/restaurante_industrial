@extends('layouts.app')
@section('title', 'Gestión de servicios')
@section('content')
<div class="mgr-page manager-services">
<header class="mgr-heading"><x-icon name="dish"/><div><span class="section-kicker">Operación · {{ $site->nombre }}</span><h1>Gestión de servicios</h1><p>Consulta la programación y revisa las propuestas de tu sede.</p></div></header>
<div class="mgr-kpis">@foreach([['calendar',$summary['total'],'Servicios registrados'],['clock',$summary['pending'],'Pendientes de aprobación'],['dish',$summary['approved'],'Servicios aprobados'],['alert',$summary['rejected'],'Servicios rechazados']] as [$icon,$value,$label])<article class="mgr-card mgr-kpi"><span class="mgr-kpi-icon"><x-icon :name="$icon"/></span><div><strong>{{ $value }}</strong><span>{{ $label }}</span></div></article>@endforeach</div>
<form class="mgr-card manager-filters" method="GET"><label>Buscar servicio<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="150" placeholder="Nombre del servicio"></label><label>Fecha<input type="date" name="fecha" value="{{ $filters['fecha'] ?? '' }}"></label><label>Estado operativo<select name="estado"><option value="">Todos</option>@foreach(['Programado','En curso','Finalizado','Cancelado'] as $state)<option @selected(($filters['estado'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></label><label>Aprobación<select name="aprobacion"><option value="">Todas</option>@foreach(['Pendiente','Aprobado','Rechazado'] as $state)<option @selected(($filters['aprobacion'] ?? '') === $state)>{{ $state }}</option>@endforeach</select></label><button class="mgr-button">Filtrar</button><a href="{{ route('manager.services.index') }}">Limpiar</a></form>
<div class="manager-service-grid">
@include('manager.services.schedules')
@forelse($services as $service)
    @php
        $name = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($service->nombre));
        $image = str_contains($name, 'desayuno') ? 'breakfast' : (str_contains($name, 'cena') ? 'dinner' : 'lunch');
        $tone = match ($service->estado_aprobacion) { 'Aprobado' => 'ok', 'Rechazado' => 'danger', default => 'waiting' };
        $own = (int) $service->id_usuario_registro === (int) auth()->id();
    @endphp
    <article class="mgr-card manager-service-card"><div class="manager-service-cover" style="background-image:linear-gradient(90deg,#102e50e6,#102e5033),url('{{ asset('img/ui/service-'.$image.'.jpg') }}')"><span class="manager-status {{ $tone }}">{{ $service->estado_aprobacion ?: 'Sin revisión' }}</span><h2>{{ $service->nombre }}</h2></div>
        <div class="manager-service-body"><dl><div><dt><x-icon name="calendar"/>Fecha</dt><dd>{{ $service->fecha?->format('d/m/Y') ?? 'Sin fecha' }}</dd></div><div><dt><x-icon name="clock"/>Horario</dt><dd>{{ substr($service->inicio_hora ?? '',0,5) ?: '--:--' }} – {{ substr($service->fin_hora ?? '',0,5) ?: '--:--' }}</dd></div><div><dt><x-icon name="users"/>Capacidad</dt><dd>{{ $service->capacidad ?? 'Sin dato' }} comensales</dd></div><div><dt><x-icon name="activity"/>Estado operativo</dt><dd>{{ $service->estado ?: 'Sin estado' }}</dd></div></dl><p class="manager-responsible"><x-icon name="users"/><span><small>Responsable</small>{{ $service->creator?->name ?? 'Registro histórico' }}</span></p>
        <details class="manager-service-details"><summary>Revisar detalles</summary><p>Servicio #{{ $service->id_servicio }} · {{ $site->nombre }}</p><p>{{ $service->observaciones_revision ?: 'Sin observaciones de revisión.' }}</p></details>
        @if($service->estado_aprobacion === 'Pendiente' && !$own)
            <div class="manager-service-actions"><form method="POST" action="{{ route('manager.services.approve',$service) }}" onsubmit="return confirm('¿Aprobar este servicio?')">@csrf @method('PATCH')<button class="mgr-button">✓ Aprobar</button></form><details><summary class="manager-reject">Rechazar propuesta</summary><form method="POST" action="{{ route('manager.services.reject',$service) }}">@csrf @method('PATCH')<label>Motivo del rechazo<textarea name="observaciones_revision" required maxlength="1000" rows="3" placeholder="Explica el motivo de la decisión"></textarea></label><button class="button danger" type="submit">Confirmar rechazo</button></form></details></div>
        @elseif($own && $service->estado_aprobacion === 'Pendiente')<p class="manager-note">La propuesta debe ser revisada por otro responsable autorizado.</p>@else<p class="manager-note">Revisión {{ $service->estado_aprobacion === 'Aprobado' ? 'aprobada' : ($service->estado_aprobacion === 'Rechazado' ? 'rechazada' : 'registrada') }}.</p>@endif
        </div>
    </article>
@empty<div class="mgr-card manager-empty"><x-icon name="calendar"/><h2>No hay servicios con estos filtros.</h2><p>Las propuestas registradas por cocina aparecerán aquí para su revisión.</p></div>@endforelse
</div><div class="mgr-pagination">{{ $services->links() }}</div>
</div>
@endsection
