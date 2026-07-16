@props(['label' => '', 'name', 'value' => '', 'required' => false, 'placeholder' => '', 'rows' => 4, 'hint' => ''])

<div>
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="required">*</span>@endif</label>
    @endif
    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'textarea-field' . ($errors->has($name) ? ' is-invalid' : '')]) }}
    >{{ old($name, $value) }}</textarea>
    @if($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
