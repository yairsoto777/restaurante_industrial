@extends('layouts.app')

@section('title', 'Solicitudes de empleo')

@section('content')
@php
    $activeSite = $currentSite ?? null;
@endphp
<div class="page-heading">
    <div>
        <span class="section-kicker">Gestión de personal</span>
        <h1>Solicitudes de empleo</h1>
        <p>{{ $activeSite ? 'Revisa las solicitudes recibidas para '.$activeSite->nombre.'.' : 'Revisa las solicitudes correspondientes a tus sedes asignadas.' }}</p>
    </div>
    @if($activeSite)<span class="context-chip"><x-icon name="building"/>{{ $activeSite->nombre }}</span>@endif
</div>

@if(isset($managerSites) && $managerSites->isEmpty())
    <div class="alert warning">No tienes una sede asignada como gerente.</div>
@endif

<section class="panel">
    <div class="panel-head"><h2>Solicitudes recibidas</h2><span class="catalog-count">{{ $applications->total() }} registro(s)</span></div>
    <div class="table-wrap">
        <table class="applications-table manager-applications-table">
            <thead><tr><th>Solicitante</th><th>Correo</th><th>Sede</th><th>Puesto</th><th>Mensaje</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
            @forelse($applications as $application)
                @php
                    $applicationStatus = \Illuminate\Support\Str::slug($application->estado ?: 'pendiente');
                    $isPending = mb_strtolower($application->estado ?: 'pendiente') === 'pendiente';
                @endphp
                <tr>
                    <td><strong>{{ $application->user?->name ?? 'Usuario no disponible' }}</strong></td>
                    <td>{{ $application->user?->correo ?? 'Sin correo' }}</td>
                    <td>{{ $application->site?->nombre ?? 'Sede no disponible' }}</td>
                    <td>{{ \App\Support\EmploymentRoles::displayName($application->requestedRole?->nombre ?? 'Puesto no disponible') }}</td>
                    <td class="message-cell">{{ \Illuminate\Support\Str::limit($application->mensaje ?: 'Sin mensaje', 75) }}</td>
                    <td>{{ $application->fecha_solicitud ? \Illuminate\Support\Carbon::parse($application->fecha_solicitud)->format('d/m/Y H:i') : 'Sin fecha' }}</td>
                    <td><span class="status {{ $applicationStatus }}">{{ ucfirst($application->estado ?: 'pendiente') }}</span></td>
                    <td>
                        <div class="table-actions">
                            <a class="button compact" href="{{ route('manager.applications.show', $application) }}">Ver detalle</a>
                            @if($isPending)
                                <form method="POST" action="{{ route('manager.applications.approve', $application) }}" onsubmit="return confirm('¿Aprobar esta solicitud y asignar el puesto?')">
                                    @csrf @method('PATCH')
                                    <button class="button compact success" type="submit">Aprobar</button>
                                </form>
                                <details class="inline-decision">
                                    <summary class="button compact danger">Rechazar</summary>
                                    <form method="POST" action="{{ route('manager.applications.reject', $application) }}">
                                        @csrf @method('PATCH')
                                        <label>Motivo del rechazo
                                            <textarea name="observaciones_respuesta" rows="3" maxlength="1000" required placeholder="Explica brevemente el motivo."></textarea>
                                        </label>
                                        <button class="button compact danger" type="submit">Confirmar rechazo</button>
                                    </form>
                                </details>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="table-empty">No hay solicitudes de empleo para revisar.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $applications->links() }}</div>
</section>
@endsection
