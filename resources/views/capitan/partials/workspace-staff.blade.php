@forelse($asignaciones->groupBy('id_usuario_mesero') as $personAssignments)
@php($person=$personAssignments->first())
<article class="cw-person"><x-capitan.avatar :person="$person"/><div><strong>{{ $person->nombre }} {{ $person->apellido_p }}</strong><small>{{ $personAssignments->pluck('zona')->unique()->implode(', ') }}</small><small>{{ $mesasServicio->whereIn('id_zona',$personAssignments->pluck('id_zona'))->count() }} mesas a cargo · <span class="cw-badge green">Asignado</span></small></div></article>
@empty<p class="cw-empty">Sin meseros asignados{{ $jornadaSupported ? ' a esta jornada' : '' }}.</p>@endforelse
