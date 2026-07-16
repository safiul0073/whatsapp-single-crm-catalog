@props(['name', 'label', 'options' => [], 'value' => null])

@php $value = $value ?? request($name) @endphp

<div>
    <label class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-neutral-500">{{ __($label) }}</label>
    <select name="{{ $name }}" class="select-field min-h-9 w-full px-2.5 py-1.5 text-xs">
        <option value="">{{ __('All :label', ['label' => __($label)]) }}</option>
        @foreach($options as $optionValue => $optionLabel)
            @php
                $val = is_string($optionValue) ? $optionValue : $optionLabel;
                $display = $optionLabel;
            @endphp
            <option value="{{ $val }}" @selected(($value ?? '') == $val)>
                {{ __($display) }}
            </option>
        @endforeach
    </select>
</div>
