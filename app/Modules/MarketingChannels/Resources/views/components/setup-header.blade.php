@props(['provider', 'channel' => null, 'title' => null, 'description' => null, 'backRoute' => null, 'backLabel' => null])

@php
    $config = config("marketing-channels.providers.{$provider}", []);
    $backRoute ??= route('user.channels.index');
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    <a href="{{ $backRoute }}" class="inline-flex items-center gap-1.5 rounded-md text-sm font-medium text-body transition hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/20" data-channel-hub-link>
        <i class="ph ph-arrow-left text-base"></i>
        {{ $backLabel ?? __('All channels') }}
    </a>

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-center gap-4">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary">
                <i class="ph {{ $config['icon'] ?? 'ph-plugs-connected' }} text-2xl"></i>
            </span>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="heading-2">{{ $title ?? __($config['title'] ?? $provider) }}</h2>
                    <x-marketing-channels::status-badge :channel="$channel" />
                </div>
                <p class="m-text mt-1">{{ $description ?? __($config['description'] ?? '') }}</p>
            </div>
        </div>

        @if ($slot->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
        @endif
    </div>
</div>
