@props(['name'])
<svg {{ $attributes->merge(['class' => 'ui-icon', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
@switch($name)
    @case('home')<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>@break
    @case('building')<path d="M4 21V3h11v18"/><path d="M15 9h5v12"/><path d="M8 7h3M8 11h3M8 15h3M2 21h20"/>@break
    @case('users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>@break
    @case('box')<path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="m3 8 9 5 9-5M3 8v8l9 5 9-5V8M12 13v8"/>@break
    @case('warehouse')<path d="M3 21V9l9-6 9 6v12"/><path d="M7 21v-8h10v8M7 17h10"/>@break
    @case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>@break
    @case('activity')<path d="M3 12h4l2-7 4 14 2-7h6"/>@break
    @case('map')<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/>@break
    @case('group')<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 11h-6M20 8v6"/>@break
    @case('utensils')<path d="M3 2v7a3 3 0 0 0 6 0V2M6 2v20M15 2v8h4V2v20"/>@break
    @case('dish')<path d="M4 15h16M6 15a6 6 0 0 1 12 0M3 19h18"/><path d="M12 7V5"/>@break
    @case('alert')<path d="M10.3 3.8 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>@break
    @case('chart')<path d="M3 3v18h18"/><path d="m7 16 4-5 4 3 5-7"/>@break
    @case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>@break
    @case('chevron')<path d="m9 18 6-6-6-6"/>@break
    @case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
    @case('plates')<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/>@break
    @case('cash')<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M7 9H6v2M17 13h1v2"/>@break
    @case('layers')<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>@break
    @default<circle cx="12" cy="12" r="9"/>
@endswitch
</svg>
