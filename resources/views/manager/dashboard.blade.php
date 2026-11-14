@extends('layouts.app')
@section('title', 'Gerente de sede')
@section('content')
<div class="mgr-page">
    <header class="mgr-heading"><div><h1>👋 ¡Hola, {{ auth()->user()->nombre }}!</h1><p>Bienvenido(a) a NUTRILIADO. Aquí puedes administrar tu sede, personal, servicios y más.</p></div></header>
    <section class="mgr-banner"><div><h2>Gerente de Sede</h2><p>Supervisa la operación de tu comedor, gestiona al personal, autoriza servicios y mantén tu información actualizada.</p></div><span class="mgr-banner-brand"><x-icon name="dish"/><strong>NUTRILIADO</strong><small>Comedor Industrial</small></span></section>
    <div class="mgr-kpis">
        @foreach([['users', $employeeCount, 'Empleados registrados', 'purple'], ['dish', $activeServices, 'Servicios activos', 'orange'], ['box', $receiptCount, 'Recepciones del mes', 'green'], ['calendar', $pendingServices->count(), 'Servicios por aprobar', 'violet']] as [$icon, $value, $label, $tone])
        <article class="mgr-card mgr-kpi {{ $tone }}"><span class="mgr-kpi-icon"><x-icon :name="$icon"/></span><div><strong>{{ $value }}</strong><span>{{ $label }}</span></div></article>
        @endforeach
    </div>
    <div class="mgr-charts">
        <section class="mgr-card"><div class="mgr-card-heading"><h2><x-icon name="dish"/>Servicios programados</h2><a href="{{ route('manager.services.index') }}">Ver todos →</a></div><p class="mgr-caption">Capacidad de los próximos servicios aprobados</p>
            <div class="mgr-bars" role="img" aria-label="Capacidad por servicio programado">
            @forelse($scheduledServices as $service)
                <div class="mgr-bar-column"><strong>{{ $service->capacidad }}</strong><div class="mgr-bar-track"><i style="height:{{ max(2, round(($service->capacidad / max(1, $scheduledServices->max('capacidad'))) * 100)) }}%"></i></div><span>{{ $service->nombre }}</span></div>
            @empty<p class="mgr-empty">No hay servicios aprobados programados.</p>@endforelse
            </div>
        </section>
        <section class="mgr-card"><div class="mgr-card-heading"><h2><x-icon name="activity"/>Estado de operación</h2></div>
            @php
                $total = $operationStates->sum('value');
                $cursor = 0; $stops = [];
                foreach ($operationStates as $state) { $end = $cursor + ($total ? $state['value'] / $total * 100 : 0); $stops[] = $state['color'].' '.$cursor.'% '.$end.'%'; $cursor = $end; }
                $percent = $total ? round($activeServices / $total * 100) : 0;
            @endphp
            <div class="mgr-operation"><div class="mgr-donut" style="background:{{ $total ? 'conic-gradient('.implode(', ', $stops).')' : '#e5e0ef' }}" role="img" aria-label="{{ $percent }}% de servicios activos"><div><strong>{{ $percent }}%</strong><span>Activos</span></div></div><ul class="mgr-legend">@foreach($operationStates as $state)<li><i style="background:{{ $state['color'] }}"></i><span>{{ $state['label'] }}</span><strong>{{ $state['value'] }}</strong></li>@endforeach</ul></div>
        </section>
        <section class="mgr-card"><div class="mgr-card-heading"><h2><x-icon name="layers"/>Acciones rápidas</h2></div><div class="mgr-quick">
            <a class="purple" href="{{ route('manager.employees.index', ['nuevo' => 1]) }}"><x-icon name="group"/>Registrar empleado</a>
            <a class="green" href="{{ route('manager.services.create') }}"><x-icon name="dish"/>Crear servicio</a>
            <a class="violet" href="{{ route('manager.site-page.edit') }}"><x-icon name="calendar"/>Editar mi página web</a>
            <a class="orange" href="{{ route('receptions.index') }}"><x-icon name="box"/>Consultar recepciones</a>
        </div></section>
    </div>
    <div class="mgr-bottom">
        <section class="mgr-card"><div class="mgr-card-heading"><h2><x-icon name="calendar"/>Servicios pendientes de aprobación</h2><a href="{{ route('manager.services.index') }}">Ver todos →</a></div><div class="table-wrap"><table class="mgr-table"><thead><tr><th>Servicio</th><th>Fecha</th><th>Horario</th><th>Acción</th></tr></thead><tbody>
        @forelse($pendingServices->take(5) as $service)<tr><td><strong>{{ $service->nombre }}</strong></td><td>{{ $service->fecha }}</td><td>{{ substr($service->inicio_hora, 0, 5) }}–{{ substr($service->fin_hora, 0, 5) }}</td><td>
            @if((int) ($service->id_usuario_registro ?? 0) !== (int) auth()->id())
            <form method="POST" action="{{ route('manager.services.approve', $service->id_servicio) }}">@csrf @method('PATCH')<button class="mgr-approve" title="Aprobar servicio" aria-label="Aprobar {{ $service->nombre }}">✓</button></form>
            @endif
            <a href="{{ route('manager.services.index') }}" title="Revisar o rechazar con observaciones">Revisar</a>
        </td></tr>@empty<tr><td colspan="4" class="mgr-empty">No hay servicios pendientes de aprobación.</td></tr>@endforelse
        </tbody></table></div></section>
        <section class="mgr-card"><div class="mgr-card-heading"><h2><x-icon name="users"/>Gestión de usuarios (Multirrol)</h2><a class="mgr-button" href="{{ route('manager.employees.index', ['nuevo' => 1]) }}">+ Nuevo usuario</a></div><div class="table-wrap"><table class="mgr-table"><thead><tr><th>Usuario</th><th>Correo</th><th>Roles asignados</th><th>Acciones</th></tr></thead><tbody>
        @forelse($employees as $employee)<tr><td><strong>{{ $employee->user?->name ?? $employee->nombre }}</strong></td><td>{{ $employee->user?->correo ?? 'Sin cuenta' }}</td><td><div class="mgr-role-list">@foreach($employee->user?->roles ?? [] as $role)<span class="mgr-role tone-{{ $role->id_rol % 7 }}">{{ \App\Support\EmploymentRoles::displayName($role->nombre) }}</span>@endforeach</div></td><td>@if($employee->user && !$employee->user->isManager() && !$employee->user->canAdminister())<a class="mgr-edit" href="{{ route('manager.employees.index', ['editar' => $employee->id_empleado]) }}" aria-label="Editar {{ $employee->nombre }}">✎</a>@endif</td></tr>
        @empty<tr><td colspan="4" class="mgr-empty">Aún no hay empleados registrados en esta sede.</td></tr>@endforelse
        </tbody></table></div></section>
    </div>
</div>
@endsection
