@aware(['type' => 'inline'])

@props([
    'icon' => '',
    'label' => '',
    'href' => '',
    'variant' => 'default',
    'divider' => false,
])

@if($divider)
    @if($type === 'dropdown')
        <div class="my-1 h-px bg-neutral-100"></div>
    @endif
@elseif($type === 'dropdown')
    @php
        $classes = 'floating-dropdown-item';
        if ($variant === 'danger') $classes .= ' text-error hover:bg-error/10';
    @endphp

    @if($href)
        <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
            @if($icon)<i class="ph ph-{{ $icon }} text-lg"></i>@endif
            @if($slot->isNotEmpty())
                {{ $slot }}
            @else
                <span>{{ $label }}</span>
            @endif
        </a>
    @else
        <button type="button" {{ $attributes->merge(['class' => $classes]) }}>
            @if($icon)<i class="ph ph-{{ $icon }} text-lg"></i>@endif
            @if($slot->isNotEmpty())
                {{ $slot }}
            @else
                <span>{{ $label }}</span>
            @endif
        </button>
    @endif
@else
    @php
        $classes = 'btn-icon h-9 w-9';
        if ($variant === 'danger') $classes .= ' text-error';
    @endphp

    @if($href)
        <a href="{{ $href }}" title="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
            @if($slot->isNotEmpty())
                {{ $slot }}
            @else
                <i class="ph ph-{{ $icon }}"></i>
            @endif
        </a>
    @else
        <button type="button" title="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
            @if($slot->isNotEmpty())
                {{ $slot }}
            @else
                <i class="ph ph-{{ $icon }}"></i>
            @endif
        </button>
    @endif
@endif
