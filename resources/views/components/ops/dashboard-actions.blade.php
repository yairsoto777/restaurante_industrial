<nav class="ops-quick-actions" aria-label="Acciones de tu rol">
    @if(auth()->user()->hasRole('Capitan de meseros'))
        <strong>Supervisión del comedor</strong><a class="button primary" href="{{ route('capitan.index') }}"><x-icon name="activity" /> Panel del capitán</a><a class="button" href="{{ route('capitan.index',['modulo'=>'servicios']) }}">Servicio en vivo</a><a class="button" href="{{ route('capitan.mesas.index') }}">Mesas y zonas</a>
    @elseif(auth()->user()->hasRole('Mesero'))
        <strong>Tu jornada de atención</strong><a class="button primary" href="{{ route('waiter.index') }}"><x-icon name="plates" /> Atender mis mesas</a><a class="button" href="{{ route('services.index') }}">Servicios aprobados</a><a class="button" href="{{ route('incidents.index') }}#registrar-incidencia">Reportar incidencia</a>
    @elseif(auth()->user()->hasRole('Cajero'))
        <strong>Control de caja y cobros</strong><a class="button primary" href="{{ route('cash.index') }}"><x-icon name="cash" /> Cuentas y turno actual</a><a class="button" href="{{ route('cash.history') }}">Historial de turnos</a><a class="button" href="{{ route('incidents.index') }}#registrar-incidencia">Reportar incidencia</a>
    @endif
</nav>
