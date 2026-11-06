<x-layouts.user :title="__('All visitors')">
    <div class="space-y-5" x-data="visitorJourneyModal('{{ route('user.commerce.visitors.index') }}')">
        @include('commerce::user.visitors.partials.header')

        <form method="GET" class="visitor-panel flex flex-wrap items-end gap-3 !p-3">
            @foreach(request()->only(['range', 'from', 'to', 'date']) as $name => $value)
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label class="visitor-filter-label">
                {{ __('City') }}
                <select name="city" class="visitor-select">
                    <option value="">{{ __('All cities') }}</option>
                    @foreach($cityOptions as $city)
                        <option value="{{ $city }}" @selected(request('city') === $city)>{{ $city }}</option>
                    @endforeach
                </select>
            </label>
            <label class="visitor-filter-label">
                {{ __('Country') }}
                <select name="country" class="visitor-select">
                    <option value="">{{ __('All') }}</option>
                    @foreach($countryOptions as $code)
                        <option value="{{ $code }}" @selected(strtoupper((string) request('country')) === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </label>
            <label class="visitor-filter-label">
                {{ __('Device') }}
                <select name="device" class="visitor-select">
                    <option value="">{{ __('All') }}</option>
                    @foreach(['mobile' => __('Mobile'), 'desktop' => __('Desktop'), 'tablet' => __('Tablet'), 'bot' => __('Bot')] as $value => $label)
                        <option value="{{ $value }}" @selected(request('device') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="visitor-filter-label">
                {{ __('Platform') }}
                <select name="os" class="visitor-select">
                    <option value="">{{ __('All') }}</option>
                    @foreach(['ios', 'android', 'windows', 'macos', 'linux', 'chromeos', 'other'] as $value)
                        <option value="{{ $value }}" @selected(request('os') === $value)>{{ \App\Modules\Commerce\Storefront\Services\UserAgentParser::label($value) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="visitor-filter-toggle">
                <input type="checkbox" name="customers" value="1" class="app-checkbox" @checked(request()->boolean('customers'))>
                {{ __('Logged-in customers only') }}
            </label>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
                @if(request()->hasAny(['city', 'country', 'device', 'os', 'customers']))
                    <a href="{{ route('user.commerce.visitors.list', request()->only(['range', 'from', 'to', 'date'])) }}" class="btn btn-outline btn-sm">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>

        <section class="card overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-border-soft bg-neutral-50/50 px-4 py-3">
                <h2 class="text-sm font-semibold text-title">{{ trans_choice(':count visitor|:count visitors', $visitors->total(), ['count' => number_format($visitors->total())]) }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-soft text-xs font-semibold uppercase text-neutral-600">
                            <th class="px-4 py-2.5">{{ __('Visitor') }}</th>
                            <th class="px-4 py-2.5">{{ __('City') }}</th>
                            <th class="px-4 py-2.5">{{ __('Device') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Pages') }}</th>
                            <th class="px-4 py-2.5">{{ __('First / last view') }}</th>
                            <th class="px-4 py-2.5">{{ __('IP') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Journey') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @forelse($visitors as $visitor)
                            @php
                                $label = $visitor->customer?->contact?->name ?: __('Guest');
                                $first = \Illuminate\Support\Carbon::parse($visitor->range_first);
                                $last = \Illuminate\Support\Carbon::parse($visitor->range_last);
                            @endphp
                            <tr>
                                <td class="px-4 py-2.5">
                                    <span class="font-medium text-title">{{ $label }}</span>
                                    @if($visitor->customer?->contact?->phone)
                                        <span class="block text-xs text-body">{{ $visitor->customer->contact->phone }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="font-medium text-title">{{ $visitor->city ?: '—' }}</span>
                                    <span class="block text-xs text-body">{{ collect([$visitor->region, $visitor->country_name ?: $visitor->country_code])->filter()->implode(', ') }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="capitalize text-title">{{ $visitor->device ?: '—' }}</span>
                                    <span class="block text-xs text-body">{{ collect([$visitor->os ? \App\Modules\Commerce\Storefront\Services\UserAgentParser::label($visitor->os) : null, $visitor->browser ? \App\Modules\Commerce\Storefront\Services\UserAgentParser::label($visitor->browser) : null])->filter()->implode(' · ') }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ $visitor->range_views }}</td>
                                <td class="px-4 py-2.5 text-xs text-body">
                                    {{ $first->format('M j, H:i') }} – {{ $last->format($first->isSameDay($last) ? 'H:i' : 'M j, H:i') }}
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs">{{ $visitor->ip ?: '—' }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <button type="button" class="btn btn-sm btn-outline" x-on:click="openTimeline({{ $visitor->id }}, @js($label.' · '.$visitor->ip))">{{ __('View') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-sm text-body">{{ __('No visitors match these filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($visitors->hasPages())
                <div class="border-t border-border-soft px-4 py-3">{{ $visitors->links() }}</div>
            @endif
        </section>

        @include('commerce::user.visitors.partials.journey')
    </div>
</x-layouts.user>
