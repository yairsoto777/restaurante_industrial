@extends('layouts.app')

@section('title', 'Detalle de solicitud')

@section('content')
@php
    $applicationStatus = \Illuminate\Support\Str::slug($application->estado ?: 'pendiente');
    $isPending = mb_strtolower($application->estado ?: 'pendiente') === 'pendiente';
@endphp
<div class="page-heading">
    <div><span class="section-kicker">Gestión de personal</span><h1>Detalle de solicitud</h1><p>Verifica la información antes de aprobar o rechazar el puesto solicitado.</p></div>
    <a class="button" href="{{ route('manager.applications.index') }}">Volver a solicitudes</a>
</div>

<div class="employment-detail-layout">
    <section class="panel employment-detail-card">
        <div class="panel-head">
            <div><span class="section-kicker">Solicitud #{{ $application->id_solicitud }}</span><h2>{{ $application->user?->name ?? 'Usuario no disponible' }}</h2></div>
            <span class="status {{ $applicationStatus }}">{{ ucfirst($application->estado ?: 'pendiente') }}</span>
        </div>

        <dl class="detail-grid">
            <div><dt>Correo electrónico</dt><dd>{{ $application->user?->correo ?? 'Sin correo' }}</dd></div>
            <div><dt>Fecha de solicitud</dt><dd>{{ $application->fecha_solicitud ? \Illuminate\Support\Carbon::parse($application->fecha_solicitud)->format('d/m/Y H:i') : 'Sin fecha' }}</dd></div>
            <div><dt>Sede solicitada</dt><dd>{{ $application->site?->nombre ?? 'Sede no disponible' }}</dd></div>
            <div><dt>Rol solicitado</dt><dd>{{ \App\Support\EmploymentRoles::displayName($application->requestedRole?->nombre ?? 'Puesto no disponible') }}</dd></div>
        </dl>

        <div class="detail-message">
            <span>Mensaje del solicitante</span>
            <p>{!! nl2br(e($application->mensaje ?: 'Sin mensaje u observaciones.')) !!}</p>
        </div>

        @unless($isPending)
        <div class="response-panel">
            <span class="section-kicker">Respuesta registrada</span>
            <dl class="detail-grid">
                <div><dt>Respondió</dt><dd>{{ $application->responder?->name ?? 'Usuario no disponible' }}</dd></div>
                <div><dt>Fecha de respuesta</dt><dd>{{ $application->fecha_respuesta ? \Illuminate\Support\Carbon::parse($application->fecha_respuesta)->format('d/m/Y H:i') : 'Sin fecha' }}</dd></div>
            </dl>
            <p>{{ $application->observaciones_respuesta ?: 'Sin observaciones adicionales.' }}</p>
        </div>
        @endunless
    </section>

    @if($isPending)
    <aside class="decision-panel">
        <section class="panel">
            <span class="section-kicker">Decisión</span>
            <h2>Aprobar solicitud</h2>
            <p>Se asignará el rol solicitado al usuario en {{ $application->site?->nombre ?? 'la sede indicada' }}.</p>
            <form method="POST" action="{{ route('manager.applications.approve', $application) }}" class="form-stack" onsubmit="return confirm('¿Aprobar esta solicitud y asignar el puesto?')">
                @csrf @method('PATCH')
                <label>Observaciones <small>Opcional</small><textarea name="observaciones_respuesta" rows="3" maxlength="1000" placeholder="Agrega una nota para el solicitante.">{{ old('observaciones_respuesta') }}</textarea></label>
                <button class="button success" type="submit">Aprobar y asignar puesto</button>
            </form>
        </section>

        <section class="panel rejection-panel">
            <span class="section-kicker">Decisión</span>
            <h2>Rechazar solicitud</h2>
            <p>La solicitud cambiará a rechazada y no se asignará ninguna sede ni rol.</p>
            <form method="POST" action="{{ route('manager.applications.reject', $application) }}" class="form-stack">
                @csrf @method('PATCH')
                <label>Motivo del rechazo<textarea name="observaciones_respuesta" rows="4" maxlength="1000" required placeholder="Explica brevemente el motivo del rechazo.">{{ old('observaciones_respuesta') }}</textarea></label>
                <button class="button danger" type="submit">Rechazar solicitud</button>
            </form>
        </section>
    </aside>
    @endif
</div>
@endsection
