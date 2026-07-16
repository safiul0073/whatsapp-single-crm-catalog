@props(['label' => '', 'name', 'options' => [], 'selected' => [], 'required' => false, 'hint' => '', 'columns' => 2])

@php
    $selectedValues = is_array($selected) ? $selected : (is_string($selected) ? explode(',', $selected) : []);
    $oldValues = old($name, $selectedValues);
    if (!is_array($oldValues)) {
        $oldValues = explode(',', $oldValues);
    }
    $oldValues = array_map('trim', $oldValues);
@endphp

<div>
    @if($label)
        <label class="form-label">{{ $label }} @if($required)<span class="required">*</span>@endif</label>
    @endif
    <div class="grid grid-cols-{{ $columns }} gap-3">
        @foreach($options as $optionValue => $optionLabel)
            <label class="flex cursor-pointer items-center gap-3">
                <input
                    type="checkbox"
                    name="{{ $name }}[]"
                    value="{{ $optionValue }}"
                    class="checkbox-field"
                    @checked(in_array((string) $optionValue, $oldValues))
                />
                <span class="text-sm text-neutral-600">{{ __($optionLabel) }}</span>
            </label>
        @endforeach
    </div>
    @if($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
