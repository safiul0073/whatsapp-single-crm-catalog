@props([
    'label' => '',
    'name',
    'value' => '',
    'required' => false,
    'placeholder' => '',
    'icon' => 'ph ph-calendar',
    'hint' => '',
    'mode' => 'date',
])

@php
    $modeClass = match($mode) {
        'range'    => 'datepicker-range',
        'datetime' => 'datetime-picker',
        'time'     => 'time-picker',
        default    => 'datepicker',
    };

    if (!$placeholder) {
        $placeholder = match($mode) {
            'range'    => __('Select date range'),
            'datetime' => __('Select date & time'),
            'time'     => __('Select time'),
            default    => __('Select date'),
        };
    }

    if ($mode === 'time') {
        $icon = 'ph ph-clock';
    }
@endphp

<div>
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="required">*</span>@endif</label>
    @endif
    <div class="input-group">
        <i class="{{ $icon }} input-icon-left"></i>
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $name }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            readonly
            {{ $attributes->merge(['class' => 'input-field has-icon-left ' . $modeClass . ($errors->has($name) ? ' is-invalid' : '')]) }}
        />
    </div>
    @if($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
