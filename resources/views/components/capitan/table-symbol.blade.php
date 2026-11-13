@props(['number'])
<div {{ $attributes->class(['captain-table-symbol']) }} aria-hidden="true">
    <span class="captain-chair captain-chair-top"></span><span class="captain-chair captain-chair-right"></span>
    <span class="captain-chair captain-chair-bottom"></span><span class="captain-chair captain-chair-left"></span>
    <div class="captain-table-surface"><x-icon name="plates" /><strong>{{ $number }}</strong></div>
</div>
