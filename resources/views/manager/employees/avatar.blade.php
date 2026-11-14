@php
    $nameParts = preg_split('/\s+/u', trim($avatarName), -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_strtoupper(mb_substr($nameParts[0] ?? 'N', 0, 1).mb_substr($nameParts[count($nameParts) - 1] ?? '', 0, count($nameParts) > 1 ? 1 : 0));
@endphp
<span class="mgr-avatar {{ $avatarSize ?? 'small' }}" data-employee-avatar>
    <span data-avatar-initials aria-hidden="true">{{ $initials }}</span>
    @if($avatarProfile['has_photo'] ?? false)<img src="{{ $avatarProfile['photo_url'] ?? route('manager.employees.photo', $employee) }}" alt="Foto de {{ $avatarName }}" data-avatar-photo>@else<img alt="Vista previa de la foto" data-avatar-photo hidden>@endif
</span>
