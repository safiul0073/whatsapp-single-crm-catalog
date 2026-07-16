@php
    $firstName = trim(explode(' ', auth()->user()->name ?? 'Marcus')[0] ?? 'Marcus');

    $channelStatus = $whatsAppChannel?->status?->value ?? $whatsAppChannel?->status ?? 'draft';
    $channelStatusLabel = ucfirst(str_replace('_', ' ', $channelStatus));

    $quickActions = [
        [__('New campaign'), route('user.campaigns.create'), '<path stroke-linecap="round" stroke-linejoin="round" d="m3 11 18-5v12L3 13v-2z" />'],
        [__('Create template'), route('user.message-templates.index'), '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3H4V6zM4 9h16v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V9z" />'],
        [__('Import contacts'), route('user.contacts.index', ['import' => 1]), '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0-12 4 4m-4-4-4 4M4 21h16" />'],
        [__('Build a chatbot'), route('user.chatbots.index'), '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8V4m0 4a4 4 0 0 0-4 4v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a4 4 0 0 0-4-4z" />'],
    ];
@endphp

<x-layouts.user :title="__('Dashboard')">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="heading-2">{{ __('Welcome back') }}, {{ $firstName }}</h2>
            <p class="m-text mt-1">{{ __("Here's how your WhatsApp marketing is performing.") }}</p>
        </div>
        <div data-range-group data-range-value="{{ $range }}"
            class="inline-flex rounded-full border border-neutral-200 bg-neutral-0 p-1">
            <a href="{{ route('user.dashboard', ['range' => '7d']) }}" class="range-btn {{ $range === '7d' ? 'is-active' : '' }}">7d</a>
            <a href="{{ route('user.dashboard', ['range' => '30d']) }}" class="range-btn {{ $range === '30d' ? 'is-active' : '' }}">30d</a>
            <a href="{{ route('user.dashboard', ['range' => '90d']) }}" class="range-btn {{ $range === '90d' ? 'is-active' : '' }}">90d</a>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($kpis as $card)
            <div class="stat-card">
                <div class="f-between">
                    <p class="text-xs font-bold tracking-wider text-neutral-400 uppercase">{{ $card['label'] }}</p>
                    <span class="stat-card__icon">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            {!! $card['svg'] !!}
                        </svg>
                    </span>
                </div>
                <p class="mt-3 font-title text-3xl font-extrabold text-title">{{ $card['value'] }}</p>
                <p class="mt-1 text-xs font-semibold {{ $card['metaClass'] }}">{{ $card['meta'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="dashboard-card mt-6">
        <div class="flex flex-wrap items-center justify-between gap-4 p-6">
            <div class="flex min-w-0 items-center gap-3">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                    <i class="ph ph-whatsapp-logo text-2xl"></i>
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-title text-lg font-bold text-title">{{ __('WhatsApp Business') }}</p>
                        @if ($whatsAppChannel)
                            <span class="badge badge-success">{{ $channelStatusLabel }}</span>
                        @else
                            <span class="badge badge-neutral">{{ __('Not connected') }}</span>
                        @endif
                    </div>
                    @if ($whatsAppChannel)
                        <p class="m-text mt-1">
                            {{ __('WABA') }} {{ $whatsAppChannel->provider_account_id ?? __('not set') }}
                            · {{ __('Phone ID') }} {{ $whatsAppChannel->provider_phone_id ?? __('not set') }}
                        </p>
                    @else
                        <p class="m-text mt-1">{{ __('Connect your WhatsApp Business account to send campaigns and receive inbox replies.') }}</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($whatsAppChannel)
                    <a href="{{ route('user.whatsapp-cloud.channel-setup') }}" class="btn btn-outline">
                        <i class="ph ph-gear-six text-base"></i>
                        {{ __('Manage channel') }}
                    </a>
                @else
                    <a href="{{ route('user.whatsapp-cloud.channel-setup') }}" class="btn btn-primary">
                        <i class="ph ph-plus text-base"></i>
                        {{ __('Connect WhatsApp') }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        @foreach ($charts as [$title, $type, $color, $values])
            <div class="dashboard-card p-6">
                <div class="f-between">
                    <p class="font-title text-base font-bold text-title">{{ $title }}</p>
                    <span class="badge badge-soft">{{ $range }}</span>
                </div>
                <div class="chart-box mt-6">
                    <canvas data-chart="{{ $type }}" data-chart-color="{{ $color }}"
                        data-chart-values="{{ $values }}" data-chart-labels="{{ $chartLabels }}"></canvas>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="dashboard-card p-6 lg:col-span-2">
            <div class="f-between">
                <p class="font-title text-base font-bold text-title">{{ __('Recent conversations') }}</p>
                <a href="{{ route('user.inbox.index') }}" class="text-sm font-semibold text-primary hover:underline">
                    {{ __('View inbox') }} →
                </a>
            </div>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse ($recentConversations as $conv)
                    <div class="flex items-center gap-3 py-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary/10 text-sm font-bold text-primary">
                            {{ $conv['initials'] }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-title">{{ $conv['name'] }}</p>
                            <p class="truncate text-xs text-body">{{ $conv['message'] }}</p>
                        </div>
                        <span class="{{ $conv['statusClass'] }}">{{ $conv['status'] }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-neutral-400">{{ __('No recent conversations.') }}</p>
                @endforelse
            </div>
        </div>

        <div class="dashboard-card p-6">
            <p class="font-title text-base font-bold text-title">{{ __('Quick actions') }}</p>
            <div class="mt-4 flex flex-col gap-2.5">
                @foreach ($quickActions as [$label, $href, $svg])
                    <a href="{{ $href }}"
                        class="flex items-center gap-3 rounded-xl border border-neutral-200 p-3 transition-colors hover:border-primary/30 hover:bg-primary/5">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                {!! $svg !!}
                            </svg>
                        </span>
                        <span class="text-sm font-semibold text-title">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <p class="sr-only">
        {{ __('Launch campaigns, manage conversations, and automate WhatsApp replies from one dashboard.') }}
    </p>
</x-layouts.user>
