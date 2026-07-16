@props(['label' => '', 'name', 'value' => '1', 'checked' => false])

<div class="checkbox-wrapper">
    <label class="checkbox-label">
        <input
            type="checkbox"
            name="{{ $name }}"
            value="{{ $value }}"
            @checked(old($name, $checked))
            {{ $attributes->merge(['class' => 'checkbox-field']) }}
        />
        @if($label)
            <span class="checkbox-text">{{ $label }}</span>
        @endif
    </label>
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
