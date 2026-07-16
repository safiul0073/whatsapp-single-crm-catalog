@props(['variant' => 'primary', 'label' => 'Save'])

<button type="submit" {{ $attributes->merge(['class' => 'btn btn-' . $variant]) }}>
    {{ $slot->isNotEmpty() ? $slot : $label }}
</button>
