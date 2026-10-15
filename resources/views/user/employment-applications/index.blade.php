@extends('layouts.app')

@section('title', 'Mis solicitudes')

@section('content')
<div class="page-heading">
    <div><span class="section-kicker">Seguimiento</span><h1>Mis solicitudes</h1><p>Consulta el estado y la respuesta de cada solicitud de empleo.</p></div>
    @if(auth()->user()->isCommonUser())
    <a class="button primary" href="{{ route('user.applications.create') }}">Nueva solicitud</a>
    @endif
</div>

<section class="panel">
    <div class="panel-head"><h2>Historial de solicitudes</h2><span class="catalog-count">{{ $applications->total() }} registro(s)</span></div>
    <div class="table-wrap">
        <table class="applications-table">
            <thead><tr><th>Sede</th><th>Puesto</th><th>Mensaje</th><th>Fecha</th><th>Estado</th><th>Respuesta</th></tr></thead>
            <tbody>
            @forelse($applications as $application)
                @php
                    $applicationStatus = \Illuminate\Support\Str::slug($application->estado ?: 'pendiente');
                @endphp
                <tr>
                    <td><strong>{{ $application->site?->nombre ?? 'Sede no disponible' }}</strong></td>
                    <td>{{ \App\Support\EmploymentRoles::displayName($application->requestedRole?->nombre ?? 'Puesto no disponible') }}</td>
                    <td class="message-cell">{{ \Illuminate\Support\Str::limit($application->mensaje ?: 'Sin mensaje', 90) }}</td>
                    <td>{{ $application->fecha_solicitud ? \Illuminate\Support\Carbon::parse($application->fecha_solicitud)->format('d/m/Y H:i') : 'Sin fecha' }}</td>
                    <td><span class="status {{ $applicationStatus }}">{{ ucfirst($application->estado ?: 'pendiente') }}</span></td>
                    <td class="message-cell">{{ $application->observaciones_respuesta ?: ($applicationStatus === 'pendiente' ? 'En espera de revisión' : 'Sin observaciones') }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="table-empty">Aún no has enviado una solicitud de empleo.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrap">{{ $applications->links() }}</div>
</section>
@endsection
