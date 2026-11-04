<x-layouts.user :title="__('Meta channels connected')">
    @php
        $ready = $channels->filter(fn (array $row): bool => ($row['report']['status'] ?? null) === 'ready')->count();
        $failed = $channels->filter(fn (array $row): bool => $row['channel']->status?->value === 'error')->count();
    @endphp

    <div class="space-y-4">
        <a href="{{ route('user.channels.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-body hover:text-primary">
            <i class="ph ph-arrow-left text-base"></i>
            {{ __('All channels') }}
        </a>

        <div class="min-w-0">
            <h2 class="heading-2">{{ __('Here is where each channel stands') }}</h2>
            <p class="m-text mt-1">
                {{ trans_choice(':count channel was connected.|:count channels were connected.', $channels->count(), ['count' => $channels->count()]) }}
                @if ($ready === $channels->count() && $channels->isNotEmpty())
                    {{ __('All of them are ready to use.') }}
                @else
                    {{ __('Some still need a few steps in Meta before messages can flow. Each step below says why and how.') }}
                @endif
            </p>
        </div>

        <x-marketing-channels::status-banner />

        @foreach ($activationErrors as $activationError)
            <x-ui.alert type="error" data-activation-error>{{ $activationError }}</x-ui.alert>
        @endforeach

        @foreach ($channels as $row)
            @php($channel = $row['channel'])
            <section class="space-y-3" data-activation-channel="{{ $channel->provider }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-primary/10 text-primary">
                            <i class="ph {{ config('marketing-channels.providers.'.$channel->provider.'.icon', 'ph-plugs') }} text-xl"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-base font-semibold text-title">{{ $channel->name }}</p>
                            <p class="text-xs text-body">{{ __(config('marketing-channels.providers.'.$channel->provider.'.label', $channel->provider)) }} @if ($channel->provider_display_id) · {{ $channel->provider_display_id }} @endif</p>
                        </div>
                        <x-marketing-channels::status-badge :channel="$channel" />
                    </div>
                    <a href="{{ $row['manage_url'] }}" class="btn-sm btn-outline">
                        <i class="ph ph-gear-six text-base"></i>
                        {{ __('Manage') }}
                    </a>
                </div>

                @if ($channel->status?->value === 'error' && filled(data_get($channel->settings, 'last_error')))
                    <x-ui.alert type="error">{{ data_get($channel->settings, 'last_error') }}</x-ui.alert>
                @endif

                <x-marketing-channels::readiness-checklist :report="$row['report']" :channel="$channel" />
            </section>
        @endforeach

        <div class="flex flex-wrap gap-2 pt-2">
            <a href="{{ route('user.channels.index') }}" class="btn btn-primary">{{ __('Go to Channels') }}</a>
            <a href="{{ route('user.meta-social.business.show') }}" class="btn btn-outline">{{ __('Change selection') }}</a>
        </div>
    </div>
</x-layouts.user>
