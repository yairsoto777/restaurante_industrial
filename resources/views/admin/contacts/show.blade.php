@extends('layouts.app')

@section('title', 'Detalle del contacto')

@section('content')
@php
    $statusClass = \Illuminate\Support\Str::slug($contact->estado ?: 'nuevo');
    $canConvert = in_array($contact->estado, [\App\Models\CommercialContact::STATUS_NEW, \App\Models\CommercialContact::STATUS_CONTACTED], true);
@endphp
<div class="page-heading">
    <div>
        <span class="section-kicker">Solicitud de servicio</span>
        <h1>Detalle del contacto</h1>
        <p>Revisa el mensaje y registra el seguimiento realizado.</p>
    </div>
    <div class="actions">
        <a class="button" href="{{ route('admin.contacts.index') }}">Volver a contactos</a>
        @if($canConvert)
            <a class="button primary" href="{{ route('admin.contacts.convert.create', $contact) }}">Aprobar y convertir</a>
        @endif
    </div>
</div>

<div class="employment-detail-layout">
    <section class="panel employment-detail-card">
        <div class="panel-head">
            <div>
                <span class="section-kicker">Contacto #{{ $contact->id_contacto }}</span>
                <h2>{{ $contact->fullName() }}</h2>
            </div>
            <span class="status {{ $statusClass }}">{{ $contact->statusLabel() }}</span>
        </div>

        <dl class="detail-grid">
            <div><dt>Nombre(s)</dt><dd>{{ $contact->nombre }}</dd></div>
            <div><dt>Apellido paterno</dt><dd>{{ $contact->apellido_p ?: 'No registrado' }}</dd></div>
            <div><dt>Apellido materno</dt><dd>{{ $contact->apellido_m ?: 'No registrado' }}</dd></div>
            <div><dt>Empresa</dt><dd>{{ $contact->empresa ?: 'No registrada' }}</dd></div>
            <div><dt>Preferencia de contacto</dt><dd>{{ $contact->preferencia === 'llamada' ? 'Llamada' : ($contact->preferencia === 'correo' ? 'Correo' : 'No registrada') }}</dd></div>
            <div><dt>Teléfono</dt><dd>{{ $contact->telefono ?: 'No proporcionado' }}</dd></div>
            <div><dt>Correo electrónico</dt><dd>{{ $contact->correo }}</dd></div>
            <div><dt>Fecha de contacto</dt><dd>{{ $contact->fecha_contacto?->format('d/m/Y H:i') ?? 'Sin fecha' }}</dd></div>
            <div><dt>Fecha de respuesta</dt><dd>{{ $contact->fecha_respuesta?->format('d/m/Y H:i') ?? 'Sin respuesta registrada' }}</dd></div>
            <div><dt>Atendido por</dt><dd>{{ $contact->responder?->name ?? 'Sin responsable' }}</dd></div>
        </dl>

        <div class="detail-message">
            <span>Mensaje</span>
            <p>{!! nl2br(e($contact->mensaje)) !!}</p>
        </div>

        @if($contact->observaciones_respuesta)
            <div class="response-panel">
                <span class="section-kicker">Observaciones de respuesta</span>
                <p>{!! nl2br(e($contact->observaciones_respuesta)) !!}</p>
            </div>
        @endif

        @if($contact->estado === \App\Models\CommercialContact::STATUS_APPROVED && $contact->manager)
            @php($managerSite = $contact->manager->sites->first())
            <div class="response-panel">
                <span class="section-kicker">Cuenta de gerente</span>
                <p><strong>{{ $contact->manager->name }}</strong> · {{ $contact->manager->correo }}</p>
                <p>{{ $managerSite ? 'Configuración completada: '.$managerSite->nombre : 'Pendiente de configuración' }}</p>
                @if($managerSite)
                    <a class="button" href="{{ route('admin.companies.show', $managerSite) }}">Ver empresa</a>
                @else
                    <form method="POST" action="{{ route('admin.contacts.activation.resend', $contact) }}">
                        @csrf
                        <button class="button compact" type="submit">Reenviar activación</button>
                    </form>
                @endif
            </div>
        @endif
    </section>

    <aside class="decision-panel">
        <section class="panel">
            <span class="section-kicker">Seguimiento</span>
            <h2>Actualizar contacto</h2>
            <p>Registra el estado y las notas de la conversación.</p>
            <form class="form-stack" method="POST" action="{{ route('admin.contacts.update', $contact) }}">
                @csrf
                @method('PATCH')
                <label>
                    Estado
                    <select name="estado" required>
                        @foreach($statuses as $value => $label)
                            @if(($contact->estado === \App\Models\CommercialContact::STATUS_APPROVED && $value === \App\Models\CommercialContact::STATUS_APPROVED) || ($contact->estado !== \App\Models\CommercialContact::STATUS_APPROVED && $value !== \App\Models\CommercialContact::STATUS_APPROVED))
                                <option value="{{ $value }}" @selected(old('estado', $contact->estado) === $value)>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                    @if($contact->estado !== \App\Models\CommercialContact::STATUS_APPROVED)
                        <small>La aprobación se completa al crear la cuenta del gerente.</small>
                    @endif
                </label>
                <label>
                    Observaciones de respuesta
                    <textarea name="observaciones_respuesta" rows="7" maxlength="3000" placeholder="Llamadas, acuerdos o motivo de rechazo.">{{ old('observaciones_respuesta', $contact->observaciones_respuesta) }}</textarea>
                </label>
                <button class="button primary" type="submit">Guardar seguimiento</button>
            </form>
        </section>
    </aside>
</div>
@endsection
