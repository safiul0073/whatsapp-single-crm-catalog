@props(['type' => 'info', 'dismissible' => false])

@php
$colors = [
    'success' => 'bg-green-50 border-green-200 text-green-800',
    'error'   => 'bg-red-50 border-red-200 text-red-800',
    'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
    'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
];

$icons = [
    'success' => 'ph-check-circle',
    'error'   => 'ph-warning-circle',
    'warning' => 'ph-warning',
    'info'    => 'ph-info',
];

$iconColors = [
    'success' => 'text-green-500',
    'error'   => 'text-red-500',
    'warning' => 'text-amber-500',
    'info'    => 'text-blue-500',
];

$closeColors = [
    'success' => 'text-green-400 hover:text-green-600',
    'error'   => 'text-red-400 hover:text-red-600',
    'warning' => 'text-amber-400 hover:text-amber-600',
    'info'    => 'text-blue-400 hover:text-blue-600',
];

$colorClass = $colors[$type] ?? $colors['info'];
$iconClass = $icons[$type] ?? $icons['info'];
$iconColor = $iconColors[$type] ?? $iconColors['info'];
$closeColor = $closeColors[$type] ?? $closeColors['info'];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border px-4 py-3 ' . $colorClass]) }} role="alert">
    <div class="flex items-start gap-3">
        <i class="ph {{ $iconClass }} mt-0.5 text-lg {{ $iconColor }}"></i>
        <div class="flex-1">
            {{ $slot }}
        </div>
        @if($dismissible)
        <button type="button" onclick="this.closest('[role=alert]').remove()" class="ml-auto {{ $closeColor }}">
            <i class="ph ph-x text-lg"></i>
        </button>
        @endif
    </div>
</div>
