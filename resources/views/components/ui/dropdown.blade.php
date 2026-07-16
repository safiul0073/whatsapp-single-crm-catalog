@props(['id', 'label' => '', 'searchable' => false])

<div class="floating-dropdown relative inline-block">
    <button type="button" class="floating-select-trigger" data-floating-dropdown="{{ $id }}">
        <span class="floating-select-label">{{ $label }}</span>
        <i class="ph ph-caret-down text-sm text-neutral-400 transition-transform"></i>
    </button>
    <div id="{{ $id }}" class="floating-dropdown-panel hidden" style="display: none;">
        @if($searchable)
        <div class="border-b border-neutral-100 p-2">
            <input type="text" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-primary-500" placeholder="Search..." />
        </div>
        @endif
        <div class="floating-dropdown-items p-1">
            {{ $slot }}
        </div>
    </div>
</div>
