@props(['variant' => 'primary'])
<span {{ $attributes->merge(['class' => 'badge badge-' . $variant]) }}>{{ $slot }}</span>
