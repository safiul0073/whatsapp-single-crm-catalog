<x-layouts.user :title="__('Channels')">
    @php
        $connectedTotal = collect($cards)->sum('connected_count');
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('Channels') }}</h2>
            <p class="m-text mt-1">{{ __('Connect the channels your workspace uses for inbox, campaigns and automation. Each channel has its own guided setup.') }}</p>
        </div>
        <span class="badge badge-soft">{{ trans_choice(':count channel connected|:count channels connected', $connectedTotal, ['count' => $connectedTotal]) }}</span>
    </div>

    <x-marketing-channels::status-banner />

    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <article class="card flex flex-col p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/30" data-channel-card="{{ $card['key'] }}">
                <div class="flex items-start justify-between gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph {{ $card['icon'] }} text-2xl"></i>
                    </span>
                    <x-marketing-channels::status-badge :status="$card['status']" />
                </div>

                <h3 class="heading-5 mt-4">{{ __($card['title']) }}</h3>
                <p class="mt-1 text-sm text-body">{{ __($card['description']) }}</p>

                @if ($card['accounts']->isNotEmpty())
                    <ul class="mt-3 space-y-1">
                        @foreach ($card['accounts']->take(3) as $account)
                            <li class="flex items-center gap-2 text-sm text-title">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $account->status === \App\Modules\MarketingChannels\Enums\ChannelAccountStatus::Connected ? 'bg-success' : 'bg-neutral-300' }}"></span>
                                <span class="truncate">{{ $account->name }}</span>
                            </li>
                        @endforeach
                        @if ($card['accounts']->count() > 3)
                            <li class="text-xs text-body">{{ __('+:count more', ['count' => $card['accounts']->count() - 3]) }}</li>
                        @endif
                    </ul>
                @endif

                <div class="mt-4 flex flex-wrap gap-1.5">
                    @foreach ($card['capabilities'] as $capability)
                        <span class="badge badge-neutral">{{ __($capability) }}</span>
                    @endforeach
                </div>

                <div class="mt-auto flex items-center justify-between gap-3 border-t border-neutral-100 pt-4">
                    @if ($card['internal'])
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-body">
                            <i class="ph ph-robot text-base"></i>
                            {{ __('Managed automatically') }}
                        </span>
                    @elseif ($card['setup_url'] && $card['permission'])
                        @can($card['permission'])
                            <a href="{{ $card['setup_url'] }}" class="btn-sm {{ $card['account'] ? 'btn-outline' : 'btn-primary' }}" data-channel-setup-link="{{ $card['key'] }}">
                                <i class="ph {{ $card['account'] ? 'ph-gear-six' : 'ph-plus' }} text-base"></i>
                                {{ $card['account'] ? __('Manage') : __('Set up') }}
                            </a>
                        @else
                            <span class="text-xs font-medium text-body">{{ __('Ask a workspace admin for access') }}</span>
                        @endcan
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</x-layouts.user>
