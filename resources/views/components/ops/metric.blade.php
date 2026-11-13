@props(['label', 'value', 'note' => null, 'icon' => 'activity', 'tone' => 'info'])
<article {{ $attributes->class(['ops-metric', 'ops-metric-'.$tone]) }}>
    <span class="ops-metric-icon"><x-icon :name="$icon" /></span>
    <div><span>{{ $label }}</span><strong>{{ $value }}</strong>@if($note)<small>{{ $note }}</small>@endif</div>
</article>
