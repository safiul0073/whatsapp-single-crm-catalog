<x-layouts.user :title="__('Visitors overview')">
    @php
        $topCityCards = $cities->take(\App\Modules\Commerce\Storefront\Services\VisitorAnalyticsService::CITY_CARDS);
        $maxCityViews = max(1, (int) $cities->max('views'));
        $maxCountryViews = max(1, (int) $countries->max('views'));
        $agentLabel = fn (?string $key): string => \App\Modules\Commerce\Storefront\Services\UserAgentParser::label($key);
        $agentIcon = fn (?string $key): string => \App\Modules\Commerce\Storefront\Services\UserAgentParser::ICONS[$key] ?? 'ph-question';
        $listUrl = fn (array $filters) => route('user.commerce.visitors.list', [...request()->only(['range', 'from', 'to']), ...$filters]);
    @endphp
    <div class="space-y-5">
        @include('commerce::user.visitors.partials.header')

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <a href="{{ route('user.commerce.visitors.live-page') }}" class="visitor-stat hover:border-primary/40">
                <p class="visitor-stat-label">{{ __('Live now') }}</p>
                <p class="visitor-stat-value flex items-center gap-2"><span class="inline-block size-2.5 animate-pulse rounded-full bg-success"></span>{{ number_format($stats['live']) }}</p>
            </a>
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Unique visitors') }}</p>
                <p class="visitor-stat-value">{{ number_format($stats['visitors']) }}</p>
            </div>
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Page views') }}</p>
                <p class="visitor-stat-value">{{ number_format($stats['page_views']) }}</p>
            </div>
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Views / visitor') }}</p>
                <p class="visitor-stat-value">{{ number_format($stats['views_per_visitor'], 1) }}</p>
            </div>
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Cities') }}</p>
                <p class="visitor-stat-value">{{ number_format($stats['cities']) }}</p>
            </div>
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Logged-in customers') }}</p>
                <p class="visitor-stat-value">{{ number_format($stats['customers']) }}</p>
            </div>
        </div>

        <section class="space-y-3" aria-labelledby="top-cities-heading">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="top-cities-heading" class="heading-5 text-title">{{ __('Top cities by page views') }}</h2>
                <span class="text-xs text-body">
                    {{ __('Share of all page views in this period') }}
                    @if($stats['unlocated'] > 0)
                        · {{ trans_choice(':count view has no location yet|:count views have no location yet', $stats['unlocated'], ['count' => number_format($stats['unlocated'])]) }}
                        <i class="ph ph-info" title="{{ __('Local or private IPs, or the location lookup is still pending.') }}"></i>
                    @endif
                </span>
            </div>
            @if($topCityCards->isEmpty())
                <p class="visitor-panel text-center text-sm text-body">{{ $stats['page_views'] ? __('No visits with a known city in this period yet.') : __('No visits in this period yet.') }}</p>
            @else
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    @foreach($topCityCards as $index => $city)
                        <a href="{{ $listUrl(['city' => $city['city'], 'country' => $city['country_code']]) }}" class="visitor-city-card hover:border-primary/40">
                            <span class="visitor-city-rank">#{{ $index + 1 }}</span>
                            <p class="truncate pr-6 text-sm font-semibold text-title">{{ $city['city'] ?: __('Unknown city') }}</p>
                            <p class="truncate text-xs text-body">{{ collect([$city['region'], $city['country_code']])->filter()->implode(', ') ?: '—' }}</p>
                            <p class="visitor-city-views">{{ number_format($city['views']) }}</p>
                            <p class="mt-1 text-xs text-body">{{ __('views') }} · {{ number_format($city['visitors']) }} {{ __('visitors') }}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <div class="visitor-bar-track"><div class="visitor-bar" data-visitor-bar data-width="{{ $city['share'] }}"></div></div>
                                <span class="shrink-0 text-xs font-semibold tabular-nums text-title">{{ $city['share'] }}%</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="visitor-panel lg:col-span-2">
                <h2 class="visitor-panel-title">{{ __('Traffic trend') }}</h2>
                <div class="visitor-chart" data-visitor-trend data-series="{{ json_encode($daily) }}" data-views-label="{{ __('Page views') }}" data-visitors-label="{{ __('Visitors') }}"></div>
            </section>
            <section class="visitor-panel">
                <h2 class="visitor-panel-title">{{ __('Device types') }}</h2>
                @forelse($deviceTypes as $row)
                    <a href="{{ $listUrl(['device' => $row['key'] === 'unknown' ? null : $row['key']]) }}" class="block py-1.5 text-sm hover:text-primary">
                        <span class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 font-medium"><i class="ph {{ $agentIcon($row['key']) }} text-base text-body"></i>{{ $agentLabel($row['key']) }}</span>
                            <span class="tabular-nums"><strong>{{ number_format($row['views']) }}</strong> <span class="text-xs text-body">{{ $row['share'] }}%</span></span>
                        </span>
                        <span class="visitor-bar-track mt-1 block"><span class="visitor-bar block" data-visitor-bar data-width="{{ $row['share'] }}"></span></span>
                    </a>
                @empty
                    <p class="text-sm text-body">{{ __('No data for this period.') }}</p>
                @endforelse
            </section>
        </div>

        <section class="space-y-3" aria-labelledby="platforms-heading">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="platforms-heading" class="heading-5 text-title">{{ __('Platforms') }}</h2>
                <span class="text-xs text-body">{{ __('Operating system of each visit') }}</span>
            </div>
            @if($platforms->isEmpty())
                <p class="visitor-panel text-center text-sm text-body">{{ __('No visits in this period yet.') }}</p>
            @else
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    @foreach($platforms as $row)
                        <a href="{{ $listUrl(['os' => in_array($row['key'], ['unknown'], true) ? null : $row['key']]) }}" class="visitor-city-card hover:border-primary/40">
                            <i class="ph {{ $agentIcon($row['key']) }} visitor-platform-icon"></i>
                            <p class="text-sm font-semibold text-title">{{ $agentLabel($row['key']) }}</p>
                            <p class="visitor-city-views">{{ number_format($row['views']) }}</p>
                            <p class="mt-1 text-xs text-body">{{ __('views') }} · {{ number_format($row['visitors']) }} {{ __('visitors') }}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <div class="visitor-bar-track"><div class="visitor-bar" data-visitor-bar data-width="{{ $row['share'] }}"></div></div>
                                <span class="shrink-0 text-xs font-semibold tabular-nums text-title">{{ $row['share'] }}%</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="visitor-panel">
                <h2 class="visitor-panel-title">{{ __('All cities') }}</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-border-soft text-xs font-semibold uppercase text-body">
                                <th class="py-2 pr-2">#</th>
                                <th class="py-2 pr-2">{{ __('City') }}</th>
                                <th class="py-2 pr-2 text-right">{{ __('Views') }}</th>
                                <th class="py-2 pr-2 text-right">{{ __('Visitors') }}</th>
                                <th class="w-1/3 py-2">{{ __('Share') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-soft">
                            @forelse($cities as $index => $city)
                                <tr>
                                    <td class="py-2 pr-2 text-xs text-body">{{ $index + 1 }}</td>
                                    <td class="py-2 pr-2">
                                        <a href="{{ $listUrl(['city' => $city['city'], 'country' => $city['country_code']]) }}" class="font-medium text-title hover:text-primary">{{ $city['city'] ?: __('Unknown city') }}</a>
                                        <span class="text-xs text-body">{{ $city['country_code'] }}</span>
                                    </td>
                                    <td class="py-2 pr-2 text-right font-semibold tabular-nums">{{ number_format($city['views']) }}</td>
                                    <td class="py-2 pr-2 text-right tabular-nums text-body">{{ number_format($city['visitors']) }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="visitor-bar-track"><div class="visitor-bar" data-visitor-bar data-width="{{ round($city['views'] / $maxCityViews * 100) }}"></div></div>
                                            <span class="w-10 shrink-0 text-right text-xs tabular-nums text-body">{{ $city['share'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-sm text-body">{{ __('No data for this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="space-y-4">
                <section class="visitor-panel">
                    <h2 class="visitor-panel-title">{{ __('Top countries') }}</h2>
                    @forelse($countries as $country)
                        <a href="{{ $listUrl(['country' => $country['country_code']]) }}" class="block py-1.5 text-sm hover:text-primary">
                            <span class="flex justify-between gap-3">
                                <span class="font-medium">{{ $country['country_code'] ?: __('Unknown') }}</span>
                                <span class="tabular-nums"><strong>{{ number_format($country['views']) }}</strong> <span class="text-xs text-body">· {{ number_format($country['visitors']) }} {{ __('visitors') }}</span></span>
                            </span>
                            <span class="visitor-bar-track mt-1 block"><span class="visitor-bar block" data-visitor-bar data-width="{{ round($country['views'] / $maxCountryViews * 100) }}"></span></span>
                        </a>
                    @empty
                        <p class="text-sm text-body">{{ __('No data for this period.') }}</p>
                    @endforelse
                </section>
                <section class="visitor-panel">
                    <h2 class="visitor-panel-title">{{ __('Browsers') }}</h2>
                    <div class="flex flex-wrap gap-1.5">
                        @forelse($browsers as $row)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-border-soft bg-section px-2.5 py-1 text-xs">
                                <span class="font-medium text-title">{{ $agentLabel($row['key']) }}</span>
                                <span class="font-semibold tabular-nums text-primary">{{ number_format($row['views']) }}</span>
                                <span class="text-body">{{ $row['share'] }}%</span>
                            </span>
                        @empty
                            <span class="text-sm text-body">{{ __('No data for this period.') }}</span>
                        @endforelse
                    </div>
                </section>
                <div class="grid gap-4 sm:grid-cols-2 sm:items-start">
                    <section class="visitor-panel">
                        <h2 class="visitor-panel-title">{{ __('Top pages') }}</h2>
                        @forelse($topPages as $path => $total)
                            <div class="flex justify-between gap-3 py-1 text-sm">
                                <span class="truncate" title="{{ $path }}">{{ $path }}</span>
                                <span class="font-semibold tabular-nums">{{ number_format($total) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-body">{{ __('No data for this period.') }}</p>
                        @endforelse
                    </section>
                    <section class="visitor-panel">
                        <h2 class="visitor-panel-title">{{ __('Top referrers') }}</h2>
                        @forelse($referrers as $host => $total)
                            <div class="flex justify-between gap-3 py-1 text-sm">
                                <span class="truncate" title="{{ $host }}">{{ $host }}</span>
                                <span class="font-semibold tabular-nums">{{ number_format($total) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-body">{{ __('No outside referrers in this period.') }}</p>
                        @endforelse
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
