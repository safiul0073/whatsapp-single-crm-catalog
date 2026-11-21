<x-layouts.user :title="__('Carts')">
    <div class="space-y-5">
        <header>
            <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ __('Commerce Management') }}</p>
            <h1 class="heading-3 font-bold text-title">{{ __('Carts') }}</h1>
            <p class="mt-1 text-sm text-body">{{ __('Products shoppers have added to their cart but not ordered yet. Adding to cart does not hold stock.') }}</p>
        </header>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Open carts') }}</p>
                <p class="visitor-stat-value">{{ number_format($stats['open']) }}</p>
            </div>
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Known customers') }}</p>
                <p class="visitor-stat-value">{{ number_format($stats['customers']) }}</p>
            </div>
            <div class="visitor-stat">
                <p class="visitor-stat-label">{{ __('Pieces in carts') }}</p>
                <p class="visitor-stat-value">{{ number_format($stats['pieces']) }}</p>
            </div>
        </div>

        <form method="GET" class="visitor-panel flex flex-wrap items-end gap-3 !p-3">
            <label class="visitor-filter-label">
                {{ __('Shopper') }}
                <select name="who" class="visitor-select">
                    @foreach(['all' => __('All carts'), 'customers' => __('Logged-in customers'), 'guests' => __('Guests')] as $value => $label)
                        <option value="{{ $value }}" @selected($who === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="visitor-filter-label">
                {{ __('Search') }}
                <input type="search" name="search" value="{{ $search }}" maxlength="100" class="form-input" placeholder="{{ __('Name, phone or email') }}">
            </label>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
                @if($who !== 'all' || $search !== '')
                    <a href="{{ route('user.commerce.carts.index') }}" class="btn btn-outline btn-sm">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>

        <section class="card overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-border-soft bg-neutral-50/50 px-4 py-3">
                <h2 class="text-sm font-semibold text-title">{{ trans_choice(':count cart|:count carts', $carts->total(), ['count' => number_format($carts->total())]) }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border-soft text-xs font-semibold uppercase text-neutral-600">
                            <th class="px-4 py-2.5">{{ __('Shopper') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Items') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Pieces') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Value') }}</th>
                            <th class="px-4 py-2.5">{{ __('Last activity') }}</th>
                            <th class="px-4 py-2.5">{{ __('Expires') }}</th>
                            <th class="px-4 py-2.5 text-right">{{ __('Cart') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-soft">
                        @forelse($carts as $cart)
                            @php
                                $contact = $cart->customer?->contact;
                                $phone = $contact?->phone ?: $cart->customer?->verified_phone;
                                $whatsApp = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
                                $lastActivity = $cart->last_activity ? \Illuminate\Support\Carbon::parse($cart->last_activity) : $cart->updated_at;
                            @endphp
                            <tr>
                                <td class="px-4 py-2.5">
                                    @if($cart->customer)
                                        <a href="{{ route('user.commerce.customers.show', $cart->customer) }}" class="font-medium text-title hover:text-primary">{{ $contact?->name ?: __('Customer #:id', ['id' => $cart->customer->id]) }}</a>
                                        @if($phone)
                                            <span class="flex items-center gap-2 text-xs text-body">
                                                {{ $phone }}
                                                @if($whatsApp)
                                                    <a href="https://wa.me/{{ $whatsApp }}" target="_blank" rel="noopener" class="text-success hover:underline"><i class="ph ph-whatsapp-logo"></i> {{ __('WhatsApp') }}</a>
                                                @endif
                                            </span>
                                        @endif
                                    @else
                                        <span class="font-medium text-title">{{ __('Guest') }}</span>
                                        <span class="block text-xs text-body">{{ __('Not logged in') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($cart->items_count) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold tabular-nums">{{ number_format((int) $cart->pieces) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $currency }} {{ number_format((float) $cart->value, $precision) }}</td>
                                <td class="px-4 py-2.5 text-xs text-body" title="{{ $lastActivity?->format('M j, Y H:i') }}">{{ $lastActivity?->diffForHumans() }}</td>
                                <td class="px-4 py-2.5 text-xs text-body">{{ $cart->expires_at->format('M j, Y') }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <a href="{{ route('user.commerce.carts.show', $cart) }}" class="btn btn-sm btn-outline">{{ auth()->user()->can('commerce.customers.manage') ? __('View & edit') : __('View') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-sm text-body">{{ __('No open carts match these filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($carts->hasPages())
                <div class="border-t border-border-soft px-4 py-3">{{ $carts->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.user>
