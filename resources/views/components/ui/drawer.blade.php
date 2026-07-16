@props(['id', 'title' => '', 'position' => 'right', 'size' => ''])

@php
$classes = match($position) {
    'left' => 'drawer-left',
    'bottom' => 'drawer-bottom',
    default => 'drawer',
};
if ($size) {
    $classes .= ' drawer-' . $size;
}
@endphp

<div id="{{ $id }}" class="{{ $classes }}" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
    <div class="bg-neutral-0 flex h-full flex-col p-6">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            @if($title)
            <h3 class="text-lg font-bold text-neutral-950">{{ $title }}</h3>
            @else
            <div></div>
            @endif
            <button type="button" class="btn-icon h-8 w-8" data-drawer-close>
                <i class="ph ph-x"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto">
            {{ $slot }}
        </div>

        {{-- Footer --}}
        @isset($footer)
        <div class="mt-6 flex items-center justify-end gap-3 border-t border-neutral-100 pt-6">
            {{ $footer }}
        </div>
        @endisset
    </div>
</div>
