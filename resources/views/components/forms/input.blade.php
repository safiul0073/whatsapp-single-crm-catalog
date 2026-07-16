@props(['label' => '', 'name', 'type' => 'text', 'value' => '', 'required' => false, 'placeholder' => '', 'icon' => '', 'hint' => '', 'id' => null])

@php
    $fieldId = $id ?: $name;
@endphp

<div>
    @if($label)
        <label for="{{ $fieldId }}" class="form-label">{{ $label }} @if($required)<span class="required">*</span>@endif</label>
    @endif
    @if($icon)
    <div class="input-group">
        <i class="{{ $icon }} input-icon-left"></i>
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $fieldId }}"
            value="{{ old($name, $value) }}"
            placeholder="{{ $placeholder }}"
            {{ $attributes->except('id')->merge(['class' => 'input-field has-icon-left' . ($errors->has($name) ? ' is-invalid' : '')]) }}
        />
    </div>
    @else
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $fieldId }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->except('id')->merge(['class' => 'input-field' . ($errors->has($name) ? ' is-invalid' : '')]) }}
    />
    @endif
    @if($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
