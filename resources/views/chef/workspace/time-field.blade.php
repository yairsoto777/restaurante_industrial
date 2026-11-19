<div class="cw-time-field" data-cw-time-field>
    <label id="cw-time-label-{{ $name }}" for="cw-time-trigger-{{ $name }}">{{ $label }}</label>
    <input type="time" name="{{ $name }}" value="{{ $value }}" required aria-labelledby="cw-time-label-{{ $name }}" data-cw-time-value>
    <button type="button" class="cw-time-trigger" id="cw-time-trigger-{{ $name }}" aria-haspopup="dialog" aria-controls="cw-time-dialog" aria-labelledby="cw-time-label-{{ $name }} cw-time-text-{{ $name }}" aria-describedby="cw-time-error-{{ $name }}" data-cw-time-open hidden><span id="cw-time-text-{{ $name }}" data-cw-time-text>Seleccionar hora</span><x-icon name="clock"/></button>
    <small class="cw-time-error" id="cw-time-error-{{ $name }}" data-cw-time-error role="status" hidden></small>
</div>
@once
    @push('cw-dialogs')
        @include('chef.workspace.time-dialog')
    @endpush
@endonce
