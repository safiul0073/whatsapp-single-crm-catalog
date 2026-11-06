<x-layouts.user :title="__('Live visitors')">
    <div class="space-y-5" x-data="visitorLive('{{ route('user.commerce.visitors.live') }}', '{{ route('user.commerce.visitors.index') }}', {{ (int) $liveCount }})">
        @include('commerce::user.visitors.partials.header')

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Online now') }}</p>
                <p class="visitor-stat-value flex items-center gap-2"><span class="inline-block size-2.5 animate-pulse rounded-full bg-success"></span><span x-text="live.count">{{ $liveCount }}</span></p>
                <p class="mt-1 text-xs text-body">{{ __('Active in the last :m minutes', ['m' => \App\Modules\Commerce\Storefront\Services\VisitorAnalyticsService::LIVE_MINUTES]) }}</p>
            </div>
            <div class="visitor-stat sm:col-span-2">
                <p class="visitor-stat-label">{{ __('Active cities right now') }}</p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <template x-for="place in live.cities" :key="place.city">
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-border-soft bg-section px-2.5 py-1 text-xs">
                            <i class="ph ph-map-pin text-primary"></i>
                            <span class="font-medium text-title" x-text="place.city"></span>
                            <span class="font-semibold tabular-nums text-primary" x-text="place.count"></span>
                        </span>
                    </template>
                    <span x-show="loaded && live.cities.length === 0" class="text-xs text-body">{{ __('Nobody is on the store right now.') }}</span>
                </div>
            </div>
        </div>

        <section class="card overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-border-soft bg-neutral-50/50 px-4 py-3">
                <h2 class="text-sm font-semibold text-title">{{ __('Visitors on the store') }}</h2>
                <span class="text-xs text-body">{{ __('Auto-refreshes every 15 seconds') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-soft text-xs font-semibold uppercase text-neutral-600">
                            <th class="px-4 py-2.5">{{ __('Visitor') }}</th>
                            <th class="px-4 py-2.5">{{ __('City') }}</th>
                            <th class="px-4 py-2.5">{{ __('Country') }}</th>
                            <th class="px-4 py-2.5">{{ __('Device') }}</th>
                            <th class="px-4 py-2.5">{{ __('Current page') }}</th>
                            <th class="px-4 py-2.5">{{ __('IP') }}</th>
                            <th class="px-4 py-2.5">{{ __('Last seen') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        <template x-for="v in live.visitors" :key="v.id">
                            <tr class="cursor-pointer hover:bg-neutral-50" x-on:click="openTimeline(v.id, v.customer || v.ip)">
                                <td class="px-4 py-2.5 font-medium text-title" x-text="v.customer || @js(__('Guest'))"></td>
                                <td class="px-4 py-2.5">
                                    <span class="font-medium text-title" x-text="v.city || '—'"></span>
                                    <span class="block text-xs text-body" x-show="v.region" x-text="v.region"></span>
                                </td>
                                <td class="px-4 py-2.5" x-text="v.country || '—'"></td>
                                <td class="px-4 py-2.5">
                                    <span class="capitalize text-title" x-text="v.device || '—'"></span>
                                    <span class="block text-xs text-body" x-text="[v.os, v.browser].filter(Boolean).join(' · ')"></span>
                                </td>
                                <td class="max-w-xs truncate px-4 py-2.5" x-text="v.path || '—'"></td>
                                <td class="px-4 py-2.5 font-mono text-xs" x-text="v.ip"></td>
                                <td class="px-4 py-2.5 text-xs text-body" x-text="v.last_seen"></td>
                            </tr>
                        </template>
                        <tr x-show="loaded && live.visitors.length === 0">
                            <td colspan="7" class="py-6 text-center text-sm text-body">{{ __('Nobody is on the store right now.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        @include('commerce::user.visitors.partials.journey')
    </div>
</x-layouts.user>
