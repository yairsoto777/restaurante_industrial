@props(['person'])
@if($person->photo ?? null)<img class="cw-avatar" src="{{ $person->photo }}" alt="{{ $person->nombre }}" loading="lazy">
@else<span class="cw-avatar cw-initials" aria-label="Sin fotografía">{{ mb_substr($person->nombre,0,1) }}{{ mb_substr($person->apellido_p ?? '',0,1) }}</span>@endif
