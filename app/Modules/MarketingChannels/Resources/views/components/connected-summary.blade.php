@props(['channel', 'provider', 'fields' => [], 'title' => null])

@php
    $config = config("marketing-channels.providers.{$provider}", []);
    $lastError = data_get($channel->settings, 'last_error');
@endphp

<section {{ $attributes->merge(['class' => 'section-card']) }} data-connected-summary>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="heading-5 truncate">{{ $title ?? $channel->name }}</h3>
                <x-marketing-channels::status-badge :channel="$channel" />
            </div>
            <p class="mt-0.5 text-sm text-body">{{ __($config['title'] ?? $provider) }}</p>
        </div>
        @if (($config['capabilities'] ?? []) !== [])
            <div class="flex flex-wrap gap-1.5">
                @foreach ($config['capabilities'] as $capability)
                    <span class="badge badge-soft">{{ __($capability) }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <dl class="mt-4 grid gap-3 rounded-xl bg-section p-4 sm:grid-cols-2">
        @foreach ($fields as $label => $value)
            <div class="min-w-0">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $label }}</dt>
                <dd class="mt-1 truncate text-sm font-semibold text-title">{{ filled($value) ? $value : __('Not set') }}</dd>
            </div>
        @endforeach
        @if ($channel->connected_at)
            <div class="min-w-0">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Connected') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-title">{{ $channel->connected_at->diffForHumans() }}</dd>
            </div>
        @endif
        @if ($channel->last_synced_at)
            <div class="min-w-0">
                <dt class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Last synced') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-title">{{ $channel->last_synced_at->diffForHumans() }}</dd>
            </div>
        @endif
    </dl>

    @if (filled($lastError))
        <x-ui.alert type="error" class="mt-4">{{ $lastError }}</x-ui.alert>
    @endif

    @isset($actions)
        <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-neutral-100 pt-4">{{ $actions }}</div>
    @endisset
</section>
