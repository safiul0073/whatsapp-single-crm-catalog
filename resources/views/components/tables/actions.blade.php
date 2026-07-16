@props([
    'type' => 'inline',
    'id' => null,
])

@if($type === 'dropdown')
<div class="relative inline-block">
    <button type="button" class="btn-icon h-9 w-9" data-floating-dropdown="{{ $id }}">
        <i class="ph-bold ph-dots-three"></i>
    </button>
    <div id="{{ $id }}" class="floating-dropdown-panel min-w-48">
        <div class="space-y-1">
            {{ $slot }}
        </div>
    </div>
</div>
@else
<div class="inline-flex items-center gap-1">
    {{ $slot }}
</div>
@endif
