@php($section = request()->query('modulo', 'panel'))
@foreach(['Inicio' => ['panel' => ['home', 'Panel principal']], 'Producción' => [
    'servicio' => ['calendar', 'Servicio actual'], 'comandas' => ['layers', 'Comandas'],
    'preparacion' => ['clock', 'En preparación'], 'listos' => ['dish', 'Platillos listos'],
    'historial' => ['clock', 'Historial de comandas'],
]] as $group => $entries)
    <div class="nav-section kp-nav">
        <span>{{ $group }}</span>
        @foreach($entries as $key => [$icon, $label])
            <a class="nav-link @if($section === $key) active @endif" href="{{ route('kitchen.index', ['modulo' => $key]) }}"
                @if($section === $key) aria-current="page" @endif><x-icon :name="$icon"/><b>{{ $label }}</b></a>
        @endforeach
    </div>
@endforeach
