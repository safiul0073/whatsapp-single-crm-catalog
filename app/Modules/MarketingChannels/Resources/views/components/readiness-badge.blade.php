@props(['report' => null])

@php
    $status = $report['status'] ?? null;
    [$variant, $label] = match ($status) {
        'ready' => ['success', __('Ready')],
        'waiting' => ['info', __('Waiting for Meta')],
        'blocked' => ['error', __('Action needed (:count)', ['count' => $report['open'] ?? 0])],
        'action' => ['warning', __('Action needed (:count)', ['count' => $report['open'] ?? 0])],
        default => [null, null],
    };
@endphp

@if ($variant)
    <span {{ $attributes->merge(['class' => 'badge badge-'.$variant.' inline-flex items-center gap-1.5']) }} data-readiness-badge="{{ $status }}">
        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
        {{ $label }}
    </span>
@endif
