<x-layouts.user :title="__('Meta Ads')">
    @php
        $attribution = $report['attribution'];
        $formatRevenue = fn (array $revenue): string => $revenue === []
            ? '0'
            : collect($revenue)->map(fn (string $amount, string $currency): string => trim($currency.' '.number_format((float) $amount, 2)))->implode(' · ');
        $periodLabels = ['7d' => __('Last 7 days'), '30d' => __('Last 30 days'), '90d' => __('Last 90 days')];
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="heading-2">{{ __('Meta Ads') }}</h2>
            <p class="m-text mt-1">
                {{ __('Ad results from Meta next to the chats and orders those ads brought in.') }}
                {{ $report['since']->toFormattedDateString() }} – {{ $report['until']->toFormattedDateString() }}
            </p>
        </div>
        <nav class="flex flex-wrap gap-2" data-meta-ads-periods>
            @can('meta-ads.manage')
                <a href="{{ route('user.meta-ads.ads.index') }}" class="btn-sm btn-outline" data-meta-ads-manage><i class="ph ph-megaphone text-base"></i> {{ __('My ads') }}</a>
            @endcan
            @foreach ($periods as $period)
                <a href="{{ route('user.meta-ads.index', ['period' => $period]) }}" class="btn-sm {{ $report['period'] === $period ? 'btn-primary' : 'btn-outline' }}">{{ $periodLabels[$period] ?? $period }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-ui.kpi-card :title="__('Chats from ads')" :value="number_format($attribution['conversations'])" icon="ph-chats-circle" />
        <x-ui.kpi-card :title="__('Orders from those chats')" :value="number_format($attribution['orders'])" icon="ph-shopping-bag" color="success" />
        <x-ui.kpi-card :title="__('Revenue from those chats')" :value="$formatRevenue($attribution['revenue'])" icon="ph-currency-circle-dollar" color="warning" />
    </div>

    @if (! $report['connected'])
        <section class="section-card mt-6" data-meta-ads-empty>
            <h3 class="heading-5">{{ __('Connect your Meta Business to see ad spend') }}</h3>
            <p class="mt-1 text-sm text-body">{{ __('Chats from click-to-message ads are tracked automatically. To see spend, results and ROAS, connect your Meta Business and choose your ad accounts.') }}</p>
            <a href="{{ route('user.channels.index') }}" class="btn btn-primary mt-4">{{ __('Go to Channels') }}</a>
        </section>
    @elseif ($report['accounts'] === [])
        <section class="section-card mt-6" data-meta-ads-empty>
            <h3 class="heading-5">{{ __('No ad accounts selected') }}</h3>
            <p class="mt-1 text-sm text-body">{{ __('Choose which ad accounts to report on from your Meta Business assets.') }}</p>
            <a href="{{ route('user.meta-social.business.show') }}" class="btn btn-primary mt-4">{{ __('Manage assets') }}</a>
        </section>
    @endif

    @foreach ($report['accounts'] as $account)
        <section class="section-card mt-6" data-meta-ads-account="{{ $account['id'] }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="heading-5">{{ $account['name'] }}</h3>
                <span class="text-xs text-body">{{ $account['id'] }} @if ($account['currency']) · {{ $account['currency'] }} @endif</span>
            </div>

            @if ($account['error'])
                <x-ui.alert type="error" class="mt-4">{{ $account['error'] }}</x-ui.alert>
            @elseif ($account['summary'])
                @php($summary = $account['summary'])
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <x-ui.kpi-card :title="__('Spend')" :value="trim($account['currency'].' '.number_format((float) $summary['spend'], 2))" icon="ph-wallet" />
                    <x-ui.kpi-card :title="__('Clicks')" :value="number_format($summary['clicks'])" icon="ph-cursor-click" />
                    <x-ui.kpi-card :title="__('Messaging chats (Meta)')" :value="number_format($summary['conversations'])" icon="ph-chat-teardrop-text" />
                    <x-ui.kpi-card :title="__('Purchases (Meta)')" :value="number_format($summary['purchases'])" icon="ph-shopping-cart" color="success" />
                    <x-ui.kpi-card :title="__('ROAS (Meta)')" :value="$summary['roas'] !== null ? number_format((float) $summary['roas'], 2).'×' : '—'" icon="ph-trend-up" color="warning" />
                </div>

                @if ($account['campaigns'] !== [])
                    <div class="mt-5 overflow-x-auto rounded-md border border-border-soft">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-border-soft bg-section">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Campaign') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Spend') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Clicks') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Chats') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Purchases') }}</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('ROAS') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($account['campaigns'] as $campaign)
                                    <tr class="border-b border-border-soft last:border-0">
                                        <td class="px-4 py-3 font-medium text-heading">{{ $campaign['name'] ?: $campaign['id'] }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format((float) $campaign['spend'], 2) }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($campaign['clicks']) }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($campaign['conversations']) }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format($campaign['purchases']) }}</td>
                                        <td class="px-4 py-3 text-right">{{ $campaign['roas'] !== null ? number_format((float) $campaign['roas'], 2).'×' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <p class="mt-4 text-sm text-body">{{ __('No ad activity in this period.') }}</p>
            @endif
        </section>
    @endforeach

    <section class="section-card mt-6" data-meta-ads-attribution>
        <h3 class="heading-5">{{ __('Chats started from ads') }}</h3>
        <p class="mt-0.5 text-xs text-body">{{ __('Recorded from the first message of click-to-WhatsApp, Messenger and Instagram ads. Orders count when the same contact orders after the ad chat.') }}</p>

        @if ($attribution['ads'] === [])
            <p class="mt-4 text-sm text-body">{{ __('No chats from ads in this period yet.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto rounded-md border border-border-soft">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-soft bg-section">
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Ad') }}</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Channel') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Chats') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Orders') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Revenue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attribution['ads'] as $ad)
                            <tr class="border-b border-border-soft last:border-0">
                                <td class="px-4 py-3">
                                    <span class="block font-medium text-heading">{{ $ad['headline'] ?: __('Untitled ad') }}</span>
                                    @if ($ad['ad_id'])
                                        <span class="block text-xs text-body">{{ __('Ad ID: :id', ['id' => $ad['ad_id']]) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 capitalize">{{ $ad['provider'] }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($ad['conversations']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($ad['orders']) }}</td>
                                <td class="px-4 py-3 text-right">{{ $formatRevenue($ad['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.user>
