<div class="nav-section"><span>Inicio</span><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><x-icon name="home"/><b>Panel principal</b></a></div>
<div class="nav-section"><span>Recepción</span>
    <a class="nav-link {{ request()->routeIs('receptions.index','receptions.orders.show') ? 'active' : '' }}" href="{{ route('receptions.index') }}"><x-icon name="calendar"/><b>Entregas programadas</b></a>
    <a class="nav-link {{ request()->routeIs('receptions.register','receptions.create','receptions.corrections.create') ? 'active' : '' }}" href="{{ route('receptions.register') }}"><x-icon name="plus"/><b>Registrar recepción</b></a>
    <a class="nav-link {{ request()->routeIs('receptions.history','receptions.receipts.show') ? 'active' : '' }}" href="{{ route('receptions.history') }}"><x-icon name="layers"/><b>Historial de recepciones</b></a>
</div>
<div class="nav-section"><span>Control de calidad</span><a class="nav-link {{ request()->routeIs('incidents.*') ? 'active' : '' }}" href="{{ route('incidents.index') }}"><x-icon name="alert"/><b>Incidencias</b></a></div>
