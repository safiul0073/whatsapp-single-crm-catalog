@props(['label' => '', 'name', 'checked' => false])

@php
    $isChecked = old($name, $checked);
@endphp

<div class="toggle-wrapper">
    <label class="toggle-label flex items-center gap-3 cursor-pointer">
        <input type="hidden" name="{{ $name }}" value="{{ $isChecked ? '1' : '0' }}" data-toggle-input />
        <button
            type="button"
            class="switch{{ $isChecked ? ' checked' : '' }}"
            aria-checked="{{ $isChecked ? 'true' : 'false' }}"
            data-action="toggle-switch"
            {{ $attributes }}
        >
            <span class="switch-dot"></span>
        </button>
        @if($label)
            <span class="toggle-text text-sm text-neutral-600">{{ $label }}</span>
        @endif
    </label>
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
