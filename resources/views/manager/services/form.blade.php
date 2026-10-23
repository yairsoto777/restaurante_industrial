@extends('layouts.app')
@section('title', $service->exists ? 'Editar servicio' : 'Registrar servicio')
@section('content')
<div class="chef-page chef-page-narrow">
    <section class="chef-hero" aria-labelledby="service-form-title">
        <div class="chef-hero-icon"><x-icon name="calendar" /></div>
        <div class="chef-hero-copy"><span class="chef-eyebrow">Servicios · {{ $site->nombre }}</span><h1 id="service-form-title">{{ $service->exists ? 'Editar propuesta' : 'Registrar servicio' }}</h1><p>Define fecha, horario y capacidad. La sede se asigna desde tu sesión autorizada.</p></div>
        <a class="chef-button chef-button-secondary" href="{{ route('services.index') }}">Volver a servicios</a>
    </section>
    <section class="chef-panel chef-form-panel">
        <div class="chef-panel-heading"><div><span class="chef-kicker">Datos del servicio</span><h2>{{ $service->exists ? $service->nombre : 'Nueva propuesta' }}</h2></div></div>
        <form method="POST" action="{{ $service->exists ? route('kitchen.services.update', $service) : route('kitchen.services.store') }}" class="chef-form">
            @csrf @if($service->exists) @method('PUT') @endif
            <label class="chef-field chef-field-wide"><span>Nombre del servicio</span><input name="nombre" value="{{ old('nombre', $service->nombre) }}" required maxlength="100" placeholder="Ej. Comida ejecutiva"></label>
            <label class="chef-field"><span>Fecha</span><input type="date" name="fecha" value="{{ old('fecha', $service->fecha?->format('Y-m-d')) }}" required></label>
            <label class="chef-field"><span>Capacidad prevista</span><input type="number" name="capacidad" value="{{ old('capacidad', $service->capacidad) }}" min="1" max="100000" step="1" required></label>
            <label class="chef-field"><span>Hora de inicio</span><input type="time" name="inicio_hora" value="{{ old('inicio_hora', $service->inicio_hora ? substr($service->inicio_hora, 0, 5) : '') }}" required></label>
            <label class="chef-field"><span>Hora de finalización</span><input type="time" name="fin_hora" value="{{ old('fin_hora', $service->fin_hora ? substr($service->fin_hora, 0, 5) : '') }}" required></label>
            <div class="chef-form-note chef-field-wide"><x-icon name="clock" /><span>Al guardar, la propuesta queda pendiente de revisión por la persona responsable de aprobación.</span></div>
            <div class="chef-form-actions chef-field-wide"><a class="chef-button chef-button-secondary" href="{{ route('services.index') }}">Cancelar</a><button class="chef-button chef-button-primary" type="submit">{{ $service->exists ? 'Guardar cambios' : 'Enviar a revisión' }}</button></div>
        </form>
    </section>
</div>
@endsection
