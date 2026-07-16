@props(['label' => '', 'name', 'options' => [], 'selected' => '', 'required' => false, 'placeholder' => 'Select...', 'id' => null])

@php
    $fieldId = $id ?: $name;
@endphp

<div>
    @if($label)
        <label for="{{ $fieldId }}" class="form-label">{{ $label }} @if($required)<span class="required">*</span>@endif</label>
    @endif
    <select
        name="{{ $name }}"
        id="{{ $fieldId }}"
        {{ $attributes->except('id')->merge(['class' => 'select-field' . ($errors->has($name) ? ' is-invalid' : '')]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(old($name, $selected) == $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
        {{ $slot }}
    </select>
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
