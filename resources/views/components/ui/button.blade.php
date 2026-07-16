@props(['variant' => 'primary', 'size' => '', 'type' => 'button', 'href' => ''])

@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn btn-' . $variant . ($size ? ' btn-' . $size : '')]) }}>
    {{ $slot }}
</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'btn btn-' . $variant . ($size ? ' btn-' . $size : '')]) }}>
    {{ $slot }}
</button>
@endif
