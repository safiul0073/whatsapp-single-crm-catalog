@props(['title', 'value', 'icon', 'color' => 'primary', 'change' => null, 'changeType' => 'success'])

<div {{ $attributes->merge(['class' => 'kpi-card']) }}>
    <div class="mb-4 flex items-center justify-between">
        <div class="kpi-icon-wrapper bg-{{ $color }}/10 text-{{ $color }}">
            <i class="ph-bold {{ $icon }} text-2xl"></i>
        </div>
        @if($change)
        <span class="badge badge-{{ $changeType }}">{{ $change }}</span>
        @endif
    </div>
    <p class="s-body mb-1 font-semibold tracking-wider text-neutral-400 uppercase">{{ $title }}</p>
    <h4 class="heading-4 text-neutral-950">{{ $value }}</h4>
</div>
