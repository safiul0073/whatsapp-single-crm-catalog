@props(['label' => '', 'name', 'options' => [], 'selected' => '', 'required' => false, 'placeholder' => 'Select...', 'multiple' => false])

<div>
    @if($label)
        <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="required">*</span>@endif</label>
    @endif
    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge(['class' => $multiple ? 'ts-multi' : 'ts-basic']) }}
        @if($multiple) multiple @endif
    >
        @if($placeholder && !$multiple)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach($options as $optionValue => $optionLabel)
            @if($multiple)
                <option value="{{ $optionValue }}" @selected(in_array($optionValue, (array) old($name, is_array($selected) ? $selected : explode(',', $selected))))>
                    {{ $optionLabel }}
                </option>
            @else
                <option value="{{ $optionValue }}" @selected(old($name, $selected) == $optionValue)>
                    {{ $optionLabel }}
                </option>
            @endif
        @endforeach
        {{ $slot }}
    </select>
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
