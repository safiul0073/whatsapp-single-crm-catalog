<x-layouts.user :title="__('Cart #:id', ['id' => $cart->id])">
    @php
        $canManage = auth()->user()->can('commerce.customers.manage');
        $contact = $cart->customer?->contact;
        $phone = $contact?->phone ?: $cart->customer?->verified_phone;
        $whatsApp = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
        $warningsByItem = collect($summary['warnings'])->groupBy(fn (array $w): string => (string) $w['item_id']);
        $groupedItemIds = collect($summary['groups'])->flatMap(fn (array $g) => collect($g['items'])->pluck('id'))->all();
        $looseItems = collect($summary['items'])->reject(fn (array $item): bool => in_array($item['id'], $groupedItemIds, true))->values();
        $money = fn ($amount): string => $summary['currency'].' '.number_format((float) $amount, $summary['precision']);
    @endphp
    <div class="space-y-5">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('user.commerce.carts.index') }}" class="text-xs font-semibold text-primary hover:underline"><i class="ph ph-arrow-left"></i> {{ __('All carts') }}</a>
                <h1 class="heading-3 font-bold text-title">{{ __('Cart #:id', ['id' => $cart->id]) }}</h1>
                <p class="mt-1 text-sm text-body">
                    {{ __('Last activity') }}: {{ $lastActivity ? \Illuminate\Support\Carbon::parse($lastActivity)->diffForHumans() : '—' }}
                    · {{ __('Expires') }} {{ $cart->expires_at->format('M j, Y') }}
                </p>
            </div>
            @if($canManage && $summary['items'] !== [])
                <form method="POST" action="{{ route('user.commerce.carts.clear', $cart) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline text-error hover:border-error hover:text-error" data-confirm data-confirm-title="{{ __('Clear this cart?') }}" data-confirm-message="{{ __('Every item and pack is removed from the shopper\'s cart. This cannot be undone.') }}" data-confirm-button="{{ __('Clear cart') }}" data-confirm-variant="danger">
                        <i class="ph ph-trash"></i> {{ __('Clear cart') }}
                    </button>
                </form>
            @endif
        </header>

        <section class="card">
            @if($cart->customer)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase text-neutral-600">{{ __('Customer') }}</p>
                        <a href="{{ route('user.commerce.customers.show', $cart->customer) }}" class="text-base font-semibold text-title hover:text-primary">{{ $contact?->name ?: __('Customer #:id', ['id' => $cart->customer->id]) }}</a>
                        <p class="text-sm text-body">{{ collect([$phone, $contact?->email])->filter()->implode(' · ') }}</p>
                    </div>
                    @if($whatsApp)
                        <a href="https://wa.me/{{ $whatsApp }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline"><i class="ph ph-whatsapp-logo"></i> {{ __('Message on WhatsApp') }}</a>
                    @endif
                </div>
            @else
                <p class="text-sm text-body"><span class="font-semibold text-title">{{ __('Guest shopper.') }}</span> {{ __('This shopper has not logged in, so there is no name or phone number. Changes still show in their cart the next time it loads.') }}</p>
            @endif
        </section>

        <section class="card overflow-hidden p-0">
            <div class="flex items-center justify-between border-b border-border-soft bg-neutral-50/50 px-4 py-3">
                <h2 class="text-sm font-semibold text-title">{{ trans_choice(':count item|:count items', count($summary['items']), ['count' => count($summary['items'])]) }}</h2>
                @if($summary['coupon_code'])
                    <span class="text-xs text-body">{{ __('Coupon') }}: <span class="font-mono font-semibold">{{ $summary['coupon_code'] }}</span></span>
                @endif
            </div>

            @if($summary['items'] === [])
                <p class="py-8 text-center text-sm text-body">{{ __('This cart is empty.') }}</p>
            @endif

            <div class="divide-y divide-border-soft">
                @foreach($summary['groups'] as $group)
                    @continue($group['items'] === [])
                    @if($group['mode'] === 'wholesale')
                        <div class="space-y-2 px-4 py-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ __('Wholesale pack') }}</span>
                                    <span class="ml-1 font-medium text-title">{{ $group['name'] }}</span>
                                    <span class="block text-xs text-body">{{ trans_choice(':count piece|:count pieces', $group['total_pieces'], ['count' => $group['total_pieces']]) }} · {{ $money($group['total']) }}</span>
                                </div>
                                @if($canManage)
                                    <form method="POST" action="{{ route('user.commerce.carts.groups.destroy', [$cart, $group['id']]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline text-error hover:border-error hover:text-error" data-confirm data-confirm-title="{{ __('Remove this pack?') }}" data-confirm-message="{{ __('The whole pack is removed from the shopper\'s cart.') }}" data-confirm-button="{{ __('Remove pack') }}" data-confirm-variant="danger">{{ __('Remove pack') }}</button>
                                    </form>
                                @endif
                            </div>
                            @foreach($group['violations'] as $violation)
                                <p class="text-xs text-error">{{ $violation }}</p>
                            @endforeach
                            <ul class="space-y-1 text-sm">
                                @foreach($group['items'] as $item)
                                    <li class="flex justify-between gap-3 text-body">
                                        <span>{{ $item['name'] }} · {{ collect([$item['color_name'], $item['size']])->filter()->implode(' / ') }}</span>
                                        <span class="tabular-nums">× {{ $item['quantity'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        @foreach($group['items'] as $item)
                            @include('commerce::user.carts.partials.retail-line', ['item' => $item])
                        @endforeach
                    @endif
                @endforeach
                @foreach($looseItems as $item)
                    @include('commerce::user.carts.partials.retail-line', ['item' => $item])
                @endforeach
            </div>

            @if($summary['items'] !== [])
                <dl class="space-y-1 border-t border-border-soft px-4 py-3 text-sm">
                    <div class="flex justify-between"><dt class="text-body">{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ $money($summary['subtotal']) }}</dd></div>
                    @if((float) $summary['discount'] > 0)
                        <div class="flex justify-between"><dt class="text-body">{{ __('Discount') }}</dt><dd class="tabular-nums">− {{ $money($summary['discount']) }}</dd></div>
                    @endif
                    <div class="flex justify-between font-semibold text-title"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ $money($summary['total']) }}</dd></div>
                </dl>
            @endif
        </section>

        @if($canManage && $retailEnabled)
            <section class="card space-y-3" x-data="cartProductPicker('{{ route('user.commerce.carts.products') }}')">
                <h2 class="text-sm font-semibold text-title">{{ __('Add a product') }}</h2>
                <input type="search" class="form-input w-full" placeholder="{{ __('Search by product name or SKU') }}" x-model="search" x-on:input.debounce.300ms="load()">
                <p class="text-xs text-body" x-show="loading">{{ __('Searching…') }}</p>
                <p class="text-xs text-body" x-show="!loading && loaded && products.length === 0">{{ __('No retail products found.') }}</p>
                <ul class="max-h-72 divide-y divide-border-soft overflow-y-auto rounded-lg border border-border-soft" x-show="products.length > 0">
                    <template x-for="product in products" :key="product.id">
                        <li>
                            <button type="button" class="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-neutral-50" x-on:click="choose(product)" :class="product.id === selected?.id ? 'bg-primary/5' : ''">
                                <img x-show="product.image_url" :src="product.image_url" alt="" class="size-10 rounded object-cover">
                                <span class="flex-1">
                                    <span class="block text-sm font-medium text-title" x-text="product.name"></span>
                                    <span class="block text-xs text-body" x-text="product.sku"></span>
                                </span>
                            </button>
                        </li>
                    </template>
                </ul>
                <form method="POST" action="{{ route('user.commerce.carts.items.store', $cart) }}" class="flex flex-wrap items-end gap-3" x-show="selected">
                    @csrf
                    <label class="visitor-filter-label grow">
                        {{ __('Size / variant') }}
                        <select name="variant_id" class="visitor-select w-full" x-model="variantId" required>
                            <template x-for="variant in selected?.variants ?? []" :key="variant.id">
                                <option :value="variant.id" :disabled="variant.available < 1" x-text="variant.label + ' — ' + (variant.available > 0 ? variant.available + ' {{ __('available') }}' : '{{ __('out of stock') }}')"></option>
                            </template>
                        </select>
                    </label>
                    <label class="visitor-filter-label">
                        {{ __('Quantity') }}
                        <input type="number" name="quantity" min="1" max="10000" value="1" class="form-input w-24" required>
                    </label>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Add to cart') }}</button>
                </form>
            </section>
        @endif
    </div>
</x-layouts.user>
