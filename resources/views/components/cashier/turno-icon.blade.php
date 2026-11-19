@props(['name'])
<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('cart')<path d="M2 3h3l3 12h11l3-9H6M9 19h.01M18 19h.01"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/>@break
@case('balance')<path d="M12 3v18M6 21h12M3 7h18M6 7l-4 8h8L6 7ZM18 7l-4 8h8l-4-8Z"/>@break
@case('power')<path d="M12 2v10M6 5a9 9 0 1 0 12 0"/>@break
@default<path d="M4 14h16v7H4zM6 14l2-8h10l2 8M9 3h8v7H9zM7 18h4M16 18h1M9 11h6"/>
@endswitch
</svg>
