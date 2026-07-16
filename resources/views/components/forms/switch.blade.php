@props([
    'name' => null,
    'checked' => false,
    'value' => '1',
    'uncheckedValue' => null,
    'submitOnChange' => false,
    'label' => null,
    'title' => null,
])

@php
    $oldKey = $name ? str_replace(['][', '[', ']'], ['.', '.', ''], $name) : null;
    $isChecked = $oldKey ? (bool) old($oldKey, $checked) : (bool) $checked;
    $accessibleLabel = $label ?: $title;
@endphp

<label class="form-switch" @if($title) title="{{ $title }}" @endif>
    @if($name && $uncheckedValue !== null)
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}" />
    @endif

    <input
        type="checkbox"
        @if($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        @checked($isChecked)
        @if($submitOnChange) onchange="this.form.submit()" @endif
        @if($accessibleLabel) aria-label="{{ $accessibleLabel }}" @endif
        {{ $attributes->class(['form-switch__input peer']) }}
    />
    <span class="form-switch__track" aria-hidden="true"></span>

    @if($label)
        <span class="form-switch__label">{{ $label }}</span>
    @endif
</label>
