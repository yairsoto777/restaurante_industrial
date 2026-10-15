@extends('layouts.app')

@section('title', 'Mi panel')

@section('content')
<div class="dashboard-heading">
    <div>
        <span class="section-kicker">Portal del usuario</span>
        <h1>Hola, {{ auth()->user()->nombre ?: auth()->user()->name }}</h1>
        <p>Tu cuenta todavía no tiene un puesto operativo asignado. Desde aquí puedes consultar oportunidades y enviar una solicitud.</p>
    </div>
    <div class="current-date"><x-icon name="calendar"/><div><small>Fecha actual</small><strong>{{ now()->format('d/m/Y') }}</strong></div></div>
</div>

<section class="portal-hero">
    <div>
        <span class="section-kicker">Da el siguiente paso</span>
        <h2>Encuentra un puesto en la sede ideal para ti</h2>
        <p>Revisa los servicios y puestos disponibles. El gerente de la sede evaluará tu solicitud antes de asignarte un rol.</p>
    </div>
    <div class="portal-actions">
        <a class="button primary" href="{{ route('user.applications.create') }}">Solicitar puesto</a>
        <a class="button" href="{{ route('user.applications.index') }}">Ver mis solicitudes</a>
    </div>
</section>

<div class="portal-grid">
    <section class="dashboard-card portal-services">
        <div class="card-heading">
            <div><span class="section-kicker">Servicios</span><h2>Servicios disponibles</h2></div>
            <a href="{{ route('user.services.index') }}">Ver todos <x-icon name="chevron"/></a>
        </div>
        <div class="table-wrap">
            <table class="catalog-table">
                <thead><tr><th>Servicio</th><th>Sede</th><th>Fecha</th><th>Horario</th><th>Capacidad</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse($services as $service)
                    @php
                        $serviceStatus = \Illuminate\Support\Str::slug($service->estado ?: 'programado');
                    @endphp
                    <tr>
                        <td><strong>{{ $service->nombre }}</strong></td>
                        <td>{{ $service->sede_nombre ?? data_get($service, 'site.nombre') ?? 'Sin sede' }}</td>
                        <td>{{ $service->fecha ? \Illuminate\Support\Carbon::parse($service->fecha)->format('d/m/Y') : 'Por definir' }}</td>
                        <td>{{ $service->inicio_hora ? substr($service->inicio_hora, 0, 5) : '--:--' }} – {{ $service->fin_hora ? substr($service->fin_hora, 0, 5) : '--:--' }}</td>
                        <td>{{ $service->capacidad !== null ? number_format((int) $service->capacidad) : 'Sin límite' }}</td>
                        <td><span class="status {{ $serviceStatus }}">{{ $service->estado ?: 'Programado' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="table-empty">No hay servicios disponibles por el momento.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="dashboard-card portal-roles">
        <div class="card-heading">
            <div><span class="section-kicker">Oportunidades</span><h2>Puestos disponibles</h2></div>
            <a href="{{ route('user.services.index') }}">Ver oportunidades <x-icon name="chevron"/></a>
        </div>
        <div class="compact-role-list">
            @forelse($roles as $role)
                <a href="{{ route('user.applications.create', ['id_rol' => $role->id_rol]) }}" class="compact-role">
                    <span class="role-card-icon"><x-icon name="users"/></span>
                    <span><strong>{{ \App\Support\EmploymentRoles::displayName($role->nombre) }}</strong><small>Disponible para solicitar</small></span>
                    <x-icon name="chevron"/>
                </a>
            @empty
                <div class="inline-empty"><x-icon name="users"/><p>No hay puestos disponibles.</p></div>
            @endforelse
        </div>
    </section>

    <section class="dashboard-card portal-applications">
        <div class="card-heading">
            <div><span class="section-kicker">Seguimiento</span><h2>Mis solicitudes recientes</h2></div>
            <a href="{{ route('user.applications.index') }}">Ver historial <x-icon name="chevron"/></a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Sede</th><th>Puesto</th><th>Fecha</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse($applications as $application)
                    @php
                        $applicationStatus = \Illuminate\Support\Str::slug($application->estado ?: 'pendiente');
                    @endphp
                    <tr>
                        <td>{{ $application->site?->nombre ?? 'Sede no disponible' }}</td>
                        <td><strong>{{ \App\Support\EmploymentRoles::displayName($application->requestedRole?->nombre ?? 'Puesto no disponible') }}</strong></td>
                        <td>{{ $application->fecha_solicitud ? \Illuminate\Support\Carbon::parse($application->fecha_solicitud)->format('d/m/Y H:i') : 'Sin fecha' }}</td>
                        <td><span class="status {{ $applicationStatus }}">{{ ucfirst($application->estado ?: 'pendiente') }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="table-empty">Todavía no has enviado solicitudes.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
