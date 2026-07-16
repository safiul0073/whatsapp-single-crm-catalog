@props(['label' => '', 'name', 'value', 'checked' => false])

<div class="radio-wrapper">
    <label class="radio-label">
        <input
            type="radio"
            name="{{ $name }}"
            value="{{ $value }}"
            @checked(old($name) === (string) $value || (!old($name) && $checked))
            {{ $attributes->merge(['class' => 'radio-field']) }}
        />
        @if($label)
            <span class="radio-text">{{ $label }}</span>
        @endif
    </label>
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
