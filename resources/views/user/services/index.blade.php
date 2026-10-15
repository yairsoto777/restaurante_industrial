@extends('layouts.app')

@section('title', 'Servicios disponibles')

@section('content')
<div class="page-heading">
    <div><span class="section-kicker">Consulta</span><h1>Servicios disponibles</h1><p>Conoce la programación registrada en todas las sedes.</p></div>
    <a class="button primary" href="{{ route('user.applications.create') }}">Solicitar puesto</a>
</div>

<section class="panel">
    <div class="panel-head"><h2>Programación de servicios</h2><span class="catalog-count">{{ $services->total() }} registro(s)</span></div>
    <div class="table-wrap">
        <table class="catalog-table {{ $services->isEmpty() ? 'services-table-empty' : '' }}">
            <thead><tr><th>Servicio</th><th>Sede</th><th>Fecha</th><th>Inicio</th><th>Fin</th><th>Capacidad</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse($services as $service)
                @php
                    $serviceStatus = \Illuminate\Support\Str::slug($service->estado ?: 'programado');
                @endphp
                <tr>
                    <td><strong>{{ $service->nombre }}</strong></td>
                    <td>{{ $service->sede_nombre ?? data_get($service, 'site.nombre') ?? 'Sin sede' }}</td>
                    <td>{{ $service->fecha ? \Illuminate\Support\Carbon::parse($service->fecha)->format('d/m/Y') : 'Por definir' }}</td>
                    <td>{{ $service->inicio_hora ? substr($service->inicio_hora, 0, 5) : '--:--' }}</td>
                    <td>{{ $service->fin_hora ? substr($service->fin_hora, 0, 5) : '--:--' }}</td>
                    <td>{{ $service->capacidad !== null ? number_format((int) $service->capacidad) : 'Sin límite' }}</td>
                    <td><span class="status {{ $serviceStatus }}">{{ $service->estado ?: 'Programado' }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        <div class="services-empty-state">
                            <span class="services-empty-icon"><x-icon name="calendar"/></span>
                            <strong>No hay servicios programados en este momento.</strong>
                            <p>Puedes explorar los puestos disponibles mientras se publican nuevos servicios.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($services->hasPages())<div class="pagination-wrap">{{ $services->links() }}</div>@endif
</section>

<section class="services-opportunities" aria-labelledby="services-opportunities-title">
    <div class="opportunities-heading">
        <div>
            <span class="section-kicker">Oportunidades disponibles</span>
            <h2 id="services-opportunities-title">Conoce las áreas en las que puedes colaborar</h2>
            <p>Explora los diferentes puestos disponibles dentro de nuestras sedes y solicita el que mejor se adapte a tu perfil.</p>
        </div>
    </div>

    <div class="opportunities-summary">
        <div class="opportunities-summary-icon"><x-icon name="group"/></div>
        <div class="opportunities-summary-copy">
            <span>Oportunidades disponibles</span>
            <strong>{{ $opportunities->count() }} {{ $opportunities->count() === 1 ? 'puesto disponible' : 'puestos disponibles' }}</strong>
        </div>
        <a class="button" href="{{ route('user.applications.index') }}">Ver mis solicitudes</a>
    </div>

    <div class="role-card-grid service-opportunity-grid">
        @forelse($opportunities as $opportunity)
            <article class="role-card service-opportunity-card opportunity-{{ \Illuminate\Support\Str::slug($opportunity['categoria']) }}">
                <div class="opportunity-card-top">
                    <div class="role-card-icon"><x-icon :name="$opportunity['icono']"/></div>
                    <span class="role-category">{{ $opportunity['categoria'] }}</span>
                </div>
                <div class="role-card-copy">
                    <h3>{{ $opportunity['nombre'] }}</h3>
                    <p>{{ $opportunity['descripcion'] }}</p>
                </div>
                <a
                    class="button primary opportunity-card-action"
                    href="{{ route('user.applications.create', ['id_rol' => $opportunity['id_rol']]) }}"
                    aria-label="Solicitar puesto de {{ $opportunity['nombre'] }}"
                >
                    Solicitar puesto
                    <x-icon name="chevron"/>
                </a>
            </article>
        @empty
            <div class="empty-panel full-width">
                <x-icon name="users"/>
                <h2>Sin puestos disponibles</h2>
                <p>No hay roles laborales habilitados para solicitud en este momento.</p>
            </div>
        @endforelse
    </div>
</section>
@endsection
