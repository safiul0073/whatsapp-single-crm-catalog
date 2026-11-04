<x-layouts.user :title="__('Storefront visitors')">
    <div class="space-y-6" x-data="{
        live: { count: {{ (int) $stats['live'] }}, visitors: [] },
        timeline: null,
        timelineVisitor: null,
        refresh() {
            fetch(@js(route('user.commerce.visitors.live')), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.json())
                .then(data => { this.live = data; })
                .catch(() => {});
        },
        openTimeline(id, label) {
            this.timelineVisitor = label;
            this.timeline = [];
            fetch(@js(route('user.commerce.visitors.index')) + '/' + id, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.json())
                .then(data => { this.timeline = data.views; })
                .catch(() => {});
        },
        init() {
            'use strict';
            this.refresh();
            setInterval(() => this.refresh(), 15000);
        }
    }">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold tracking-wider uppercase text-primary">{{ __('Commerce Management') }}</p>
                <h1 class="heading-3 text-title font-bold">{{ __('Visitors') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Who is visiting your storefront: country, IP, device and pages viewed.') }}</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-input py-2 text-sm rounded-lg border-neutral-200" />
                <button type="submit" class="btn btn-sm btn-primary inline-flex items-center gap-1.5">
                    <i class="ph ph-calendar-check text-base"></i>
                    <span>{{ __('Show day') }}</span>
                </button>
            </form>
        </header>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase text-neutral-500">{{ __('Live now') }}</p>
                <p class="mt-2 flex items-center gap-2 text-2xl font-bold text-title">
                    <span class="inline-block size-2.5 rounded-full bg-success animate-pulse"></span>
                    <span x-text="live.count">{{ $stats['live'] }}</span>
                </p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase text-neutral-500">{{ __('Unique visitors') }}</p>
                <p class="mt-2 text-2xl font-bold text-title">{{ number_format($stats['visitors']) }}</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase text-neutral-500">{{ __('Page views') }}</p>
                <p class="mt-2 text-2xl font-bold text-title">{{ number_format($stats['page_views']) }}</p>
            </div>
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase text-neutral-500">{{ __('Logged-in customers') }}</p>
                <p class="mt-2 text-2xl font-bold text-title">{{ number_format($stats['customers']) }}</p>
            </div>
        </div>

        <section class="card overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-border-soft px-5 py-3.5 bg-neutral-50/50">
                <h2 class="text-sm font-semibold text-title">{{ __('Live visitors (last :m minutes)', ['m' => \App\Modules\Commerce\Http\Controllers\User\VisitorController::LIVE_MINUTES]) }}</h2>
                <span class="text-xs text-body">{{ __('Auto-refreshes every 15 seconds') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-soft text-xs font-semibold uppercase text-neutral-600">
                            <th class="py-3 px-4">{{ __('Visitor') }}</th>
                            <th class="py-3 px-4">{{ __('IP') }}</th>
                            <th class="py-3 px-4">{{ __('Location') }}</th>
                            <th class="py-3 px-4">{{ __('Device') }}</th>
                            <th class="py-3 px-4">{{ __('Current page') }}</th>
                            <th class="py-3 px-4">{{ __('Last seen') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        <template x-for="v in live.visitors" :key="v.id">
                            <tr class="hover:bg-neutral-50 cursor-pointer" x-on:click="openTimeline(v.id, v.customer || v.ip)">
                                <td class="py-2.5 px-4 font-medium text-title" x-text="v.customer || @js(__('Guest'))"></td>
                                <td class="py-2.5 px-4 font-mono text-xs" x-text="v.ip"></td>
                                <td class="py-2.5 px-4" x-text="[v.city, v.country].filter(Boolean).join(', ') || '—'"></td>
                                <td class="py-2.5 px-4 capitalize" x-text="v.device || '—'"></td>
                                <td class="py-2.5 px-4 truncate max-w-xs" x-text="v.path"></td>
                                <td class="py-2.5 px-4 text-xs text-body" x-text="v.last_seen"></td>
                            </tr>
                        </template>
                        <tr x-show="live.visitors.length === 0">
                            <td colspan="6" class="py-6 text-center text-sm text-body">{{ __('Nobody is on the store right now.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-5">
                <h2 class="text-sm font-semibold text-title mb-3">{{ __('Top countries') }}</h2>
                @forelse($countries as $code => $total)
                    <a href="{{ request()->fullUrlWithQuery(['country' => $code, 'page' => null]) }}" class="flex justify-between py-1.5 text-sm hover:text-primary">
                        <span>{{ $code ?: __('Unknown') }}</span>
                        <span class="font-semibold">{{ $total }}</span>
                    </a>
                @empty
                    <p class="text-sm text-body">{{ __('No data for this day.') }}</p>
                @endforelse
            </section>
            <section class="card p-5">
                <h2 class="text-sm font-semibold text-title mb-3">{{ __('Top pages') }}</h2>
                @forelse($topPages as $path => $total)
                    <div class="flex justify-between gap-3 py-1.5 text-sm">
                        <span class="truncate">{{ $path }}</span>
                        <span class="font-semibold">{{ $total }}</span>
                    </div>
                @empty
                    <p class="text-sm text-body">{{ __('No data for this day.') }}</p>
                @endforelse
            </section>
        </div>

        <section class="card overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-border-soft px-5 py-3.5 bg-neutral-50/50">
                <h2 class="text-sm font-semibold text-title">{{ __('Activity on :date', ['date' => $date->format('M j, Y')]) }}</h2>
                @if(request()->filled('country'))
                    <a href="{{ request()->fullUrlWithQuery(['country' => null, 'page' => null]) }}" class="text-xs text-primary hover:underline">{{ __('Clear country filter') }} ({{ request('country') }})</a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-soft text-xs font-semibold uppercase text-neutral-600">
                            <th class="py-3 px-4">{{ __('Visitor') }}</th>
                            <th class="py-3 px-4">{{ __('IP') }}</th>
                            <th class="py-3 px-4">{{ __('Location') }}</th>
                            <th class="py-3 px-4">{{ __('Device') }}</th>
                            <th class="py-3 px-4">{{ __('Pages') }}</th>
                            <th class="py-3 px-4">{{ __('First / last seen') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Journey') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @forelse($visitors as $visitor)
                            @php
                                $label = $visitor->customer?->contact?->name ?: __('Guest');
                            @endphp
                            <tr>
                                <td class="py-2.5 px-4">
                                    <span class="font-medium text-title">{{ $label }}</span>
                                    @if($visitor->customer?->contact?->phone)
                                        <span class="block text-xs text-body">{{ $visitor->customer->contact->phone }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-4 font-mono text-xs">{{ $visitor->ip ?: '—' }}</td>
                                <td class="py-2.5 px-4">{{ collect([$visitor->city, $visitor->country_name ?: $visitor->country_code])->filter()->join(', ') ?: '—' }}</td>
                                <td class="py-2.5 px-4 capitalize">{{ $visitor->device ?: '—' }}</td>
                                <td class="py-2.5 px-4 font-semibold">{{ $visitor->day_views }}</td>
                                <td class="py-2.5 px-4 text-xs text-body">
                                    {{ \Illuminate\Support\Carbon::parse($visitor->day_first)->format('H:i') }} – {{ \Illuminate\Support\Carbon::parse($visitor->day_last)->format('H:i') }}
                                </td>
                                <td class="py-2.5 px-4 text-right">
                                    <button type="button" class="btn btn-sm btn-outline" x-on:click="openTimeline({{ $visitor->id }}, @js($label.' · '.$visitor->ip))">{{ __('View') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-sm text-body">{{ __('No visitors on this day.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($visitors->hasPages())
                <div class="border-t border-border-soft px-5 py-3">{{ $visitors->links() }}</div>
            @endif
        </section>

        <div x-show="timeline !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/50 p-4" x-on:keydown.escape.window="timeline = null">
            <div class="card w-full max-w-lg max-h-[80vh] overflow-y-auto p-5" x-on:click.outside="timeline = null">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-title" x-text="timelineVisitor"></h3>
                    <button type="button" class="text-neutral-400 hover:text-neutral-700" x-on:click="timeline = null"><i class="ph ph-x"></i></button>
                </div>
                <ol class="space-y-2 text-sm">
                    <template x-for="(view, i) in timeline || []" :key="i">
                        <li class="flex justify-between gap-3 border-b border-border-soft pb-2">
                            <span class="truncate" x-text="view.path"></span>
                            <span class="shrink-0 text-xs text-body" x-text="view.at"></span>
                        </li>
                    </template>
                </ol>
            </div>
        </div>
    </div>
</x-layouts.user>
