@props(['name'])
@if(in_array($name,['receipt','credit-card','transfer','print'],true))
<svg {{ $attributes->merge(['class'=>'ui-icon','viewBox'=>'0 0 24 24','fill'=>'none','stroke'=>'currentColor','stroke-width'=>'1.8','stroke-linecap'=>'round','stroke-linejoin'=>'round','aria-hidden'=>'true']) }}>
@switch($name)
@case('receipt')<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3ZM9 7h6M9 11h6M9 15h3"/>@break
@case('credit-card')<rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h3"/>@break
@case('transfer')<path d="M3 7h17l-4-4M21 17H4l4 4M20 7l-4 4M4 17l4-4"/>@break
@case('print')<path d="M6 9V3h12v6M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M6 14h12v7H6zM18 12h.01"/>@break
@endswitch
</svg>
@else<x-icon :name="$name" {{ $attributes }}/>@endif
