<x-layouts.user :title="__('POS')">
    @php
        $config = [
            'csrf' => csrf_token(),
            'products' => route('user.commerce.pos.products'),
            'preview' => route('user.commerce.pos.preview'),
            'checkout' => route('user.commerce.pos.checkout'),
            'customers' => route('user.commerce.pos.customers'),
            'customerStore' => route('user.commerce.pos.customers.store'),
            'addressStore' => route('user.commerce.pos.customers.addresses.store', '__CONTACT__'),
            'addressSuggestions' => route('user.commerce.pos.address-suggestions'),
            'postalLookup' => route('user.commerce.pos.postal-lookup'),
            'held' => route('user.commerce.pos.held.index'),
            'heldDestroy' => route('user.commerce.pos.held.destroy', '__HELD__'),
            'balance' => route('user.commerce.pos.customer-balance', '__CONTACT__'),
            'currency' => $settings->currency,
            'precision' => $settings->precision(),
            'paymentMethods' => $paymentMethods,
            'mode' => ($retailEnabled ?? true) ? 'retail' : 'wholesale',
        ];
    @endphp
    <div class="pos-shell" x-data="commercePos" data-config="{{ json_encode($config) }}" @keydown.window="shortcut($event)">
        {{-- Top bar --}}
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-title">{{ __('Point of sale') }}</h1>
                <p class="text-sm text-body">{{ __('Scan or search, build the cart, take payment.') }} · <span x-text="currencySymbol()">{{ $settings->currency }}</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if(($wholesaleEnabled ?? true) && ($retailEnabled ?? true))
                    <div class="inline-flex rounded-md bg-section p-1" role="group" aria-label="{{ __('Add products as') }}">
                        <button type="button" class="pos-segment" :class="mode === 'retail' && 'pos-segment-active'" :aria-pressed="mode === 'retail'" @click="mode = 'retail'">{{ __('Retail') }}</button>
                        <button type="button" class="pos-segment" :class="mode === 'wholesale' && 'pos-segment-active'" :aria-pressed="mode === 'wholesale'" @click="mode = 'wholesale'">{{ __('Wholesale') }}</button>
                    </div>
                @endif
                <button type="button" class="btn btn-outline btn-sm" @click="heldOpen = true">
                    <i class="ph ph-pause-circle"></i> {{ __('Held sales') }} <span class="pos-count" x-show="held.length" x-text="held.length"></span>
                </button>
                <x-ui.button size="sm" href="{{ route('user.commerce.orders.index', ['source' => 'pos']) }}" variant="outline">{{ __('POS orders') }}</x-ui.button>
            </div>
        </header>

        <div class="pos-grid">
            {{-- Catalog --}}
            <section class="pos-panel min-w-0 space-y-4">
                <div class="relative">
                    <i class="ph ph-barcode pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-body"></i>
                    <input x-ref="posSearch" type="search" class="form-input pl-10" x-model="search" @input.debounce.300ms="loadProducts()" @keydown.enter.prevent="scan()" placeholder="{{ __('Scan barcode or search product / SKU  (F2)') }}" aria-label="{{ __('Scan or search products') }}">
                </div>
                @if($categories->isNotEmpty())
                    <div class="flex gap-2 overflow-x-auto pb-1" role="group" aria-label="{{ __('Categories') }}">
                        <button type="button" class="pos-chip" :class="category === '' && 'pos-chip-active'" @click="setCategory('')">{{ __('All') }}</button>
                        @foreach($categories as $category)
                            <button type="button" class="pos-chip" :class="category === '{{ $category->id }}' && 'pos-chip-active'" @click="setCategory('{{ $category->id }}')">{{ $category->name }}</button>
                        @endforeach
                    </div>
                @endif
                <p x-show="searching" class="text-sm text-body" role="status">{{ __('Loading products…') }}</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-3 2xl:grid-cols-4">
                    <template x-for="product in products" :key="product.id">
                        <button type="button" class="pos-tile group" @click="openProduct(product, $event.currentTarget)" :disabled="busy || !canSell(product)" :aria-label="`Select ${product.name}`" x-data="{ imageFailed: false }">
                            <span class="pos-tile-image">
                                <img x-show="product.image_url && !imageFailed" :src="product.image_url" :alt="product.name" class="h-full w-full object-contain transition group-hover:scale-[1.03]" loading="lazy" x-on:error="imageFailed = true">
                                <i x-show="!product.image_url || imageFailed" class="ph ph-image text-2xl text-body" aria-hidden="true"></i>
                                <span x-show="mode === 'wholesale' && product.pack_pricing" class="pos-badge absolute left-2 top-2">{{ __('Packs') }}</span>
                            </span>
                            <span class="line-clamp-2 min-h-10 text-sm font-medium leading-5 text-title" x-text="product.name"></span>
                            <span class="mt-2 flex items-center justify-between gap-2 text-xs">
                                <span class="text-body" x-text="`${product.available} in stock`"></span>
                                <span class="font-semibold text-title" x-text="displayPrice(product)"></span>
                            </span>
                        </button>
                    </template>
                </div>
                <p x-show="!searching && !products.length" class="py-10 text-center text-sm text-body">{{ __('No available products match this search.') }}</p>
            </section>

            {{-- Cart & checkout --}}
            <form class="pos-panel pos-cart" @submit.prevent="checkout()">
                <div class="flex items-center justify-between gap-2 border-b border-border-default pb-3">
                    <h2 class="font-semibold text-title">{{ __('Current sale') }} <span class="text-sm font-normal text-body" x-show="groups.length" x-text="`· ${itemCount()} pcs`"></span></h2>
                    <div class="flex items-center gap-1">
                        <button type="button" class="pos-icon-btn" @click="holdSale()" :disabled="!groups.length" title="{{ __('Hold sale (F8)') }}" aria-label="{{ __('Hold sale') }}"><i class="ph ph-pause"></i></button>
                        <button type="button" class="pos-icon-btn hover:text-error" @click="clearCart()" :disabled="!groups.length" title="{{ __('Clear cart') }}" aria-label="{{ __('Clear cart') }}"><i class="ph ph-trash"></i></button>
                    </div>
                </div>

                {{-- Lines --}}
                <div class="pos-lines">
                    <div x-show="!groups.length" class="flex flex-col items-center gap-2 py-10 text-center text-sm text-body">
                        <i class="ph ph-shopping-cart-simple text-3xl"></i>
                        <span>{{ __('Scan a barcode or tap a product to start.') }}</span>
                    </div>
                    <template x-for="section in cartSections()" :key="section.key">
                        <div>
                            {{-- Retail card --}}
                            <template x-if="section.type === 'retail'">
                                <article class="pos-line">
                                    <div class="flex items-start gap-2.5">
                                        <div class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border-default bg-section p-0.5">
                                            <img x-show="colorThumbUrl(section.group)" :src="colorThumbUrl(section.group)" :alt="section.group.product.name" class="h-full w-full object-contain">
                                            <i x-show="!colorThumbUrl(section.group)" class="ph ph-image text-base text-body" aria-hidden="true"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="truncate text-sm font-medium text-title" x-text="section.group.product.name"></p>
                                                    <p class="text-xs text-body">
                                                        <span class="pos-badge">{{ __('Retail') }}</span>
                                                        <span x-text="variantLabel(section.group)"></span>
                                                    </p>
                                                </div>
                                                <div class="shrink-0 text-right">
                                                    <p x-show="unitPiecePrice(section) !== null" class="text-[11px] text-body" x-text="`${money(unitPiecePrice(section))} / pc`"></p>
                                                    <p class="text-sm font-semibold text-title tabular-nums" x-text="lineTotal(section.index) === null ? '…' : money(lineTotal(section.index))"></p>
                                                </div>
                                            </div>
                                            <div class="mt-2 flex items-center justify-between gap-2">
                                                <div class="pos-stepper">
                                                    <button type="button" @click="stepQuantity(section.group, -1)" aria-label="{{ __('Decrease') }}"><i class="ph ph-minus"></i></button>
                                                    <span x-text="section.group.count"></span>
                                                    <button type="button" @click="stepQuantity(section.group, 1)" aria-label="{{ __('Increase') }}"><i class="ph ph-plus"></i></button>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    @if($canDiscount)
                                                        <label class="flex items-center gap-1 text-xs text-body">{{ __('Disc.') }}<input type="number" min="0" step="0.01" class="pos-mini-input" x-model="section.group.discount" @input.debounce.400ms="invalidate()" :aria-label="`Discount for ${section.group.product.name}`"></label>
                                                    @endif
                                                    <button type="button" class="pos-icon-btn hover:text-error" @click="remove(section.group.key)" :aria-label="`Remove ${section.group.product.name}`"><i class="ph ph-trash"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </template>

                            {{-- Wholesale grouped pack card --}}
                            <template x-if="section.type === 'wholesale'">
                                <article class="pos-ws-group">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="pos-ws-badge" x-text="section.product.pack_pricing ? '{{ __('Wholesale pack') }}' : '{{ __('Wholesale') }}'"></span>
                                                <span class="text-xs font-medium text-body" x-text="`${sectionPieces(section)} pcs`"></span>
                                            </div>
                                            <p class="truncate text-sm font-semibold text-title" x-text="section.product.name"></p>
                                        </div>
                                        <button type="button" class="pos-icon-btn text-error/80 hover:bg-error/10 hover:text-error" @click="removeProductGroup(section.product.id)" :aria-label="`Remove ${section.product.name}`"><i class="ph ph-trash"></i></button>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border-default bg-section p-0.5">
                                            <img x-show="section.product.image_url" :src="section.product.image_url" :alt="section.product.name" class="h-full w-full object-contain">
                                            <i x-show="!section.product.image_url" class="ph ph-package text-lg text-body" aria-hidden="true"></i>
                                        </div>
                                        <div class="min-w-0 flex-1 space-y-1">
                                            <p class="text-xs text-body">
                                                <span x-text="`${sectionPieces(section)} pieces`"></span>
                                                <span> · </span>
                                                <strong class="text-title tabular-nums" x-text="sectionTotal(section) === null ? '…' : money(sectionTotal(section))"></strong>
                                            </p>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button type="button" class="rounded-md border border-border-default bg-bg-elevated px-2.5 py-1 text-xs font-medium text-title transition hover:border-primary/50 hover:bg-section" @click="togglePackDetails(section.product.id)" x-text="expandedPackDetails[section.product.id] ? '{{ __('Hide pack details') }}' : '{{ __('View pack details') }}'"></button>
                                                <button type="button" class="pos-link" @click="openProduct(section.product, $event.currentTarget)">+ {{ __('Add color') }}</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div x-show="expandedPackDetails[section.product.id]" x-cloak class="rounded-lg border border-border-default bg-section/60 p-2.5 text-xs text-body space-y-1">
                                        <p class="font-semibold text-title">{{ __('Pack & color breakdown') }}</p>
                                        <template x-for="entry in section.entries" :key="`detail-${entry.group.key}`">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="truncate" x-text="`${colorName(entry.group)}${entry.group.packs ? ` (${entry.group.packs} × ${packPieces(entry.group)} pcs)` : ''}: ${groupSizeSummary(entry.group)}`"></span>
                                                <span class="shrink-0 font-medium text-title" x-text="`${groupPieces(entry.group)} pcs`"></span>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="space-y-2">
                                        <template x-for="entry in section.entries" :key="entry.group.key">
                                            <div class="pos-ws-item">
                                                <div class="flex items-start justify-between gap-2.5">
                                                    <div class="min-w-0 flex-1 space-y-1.5">
                                                        <p class="flex items-center gap-1.5 text-xs">
                                                            <span class="size-2.5 shrink-0 rounded-full border border-black/15" :style="{ backgroundColor: colorDot(entry.group) }"></span>
                                                            <strong class="text-title" x-text="`${groupPieces(entry.group)} pieces`"></strong>
                                                            <span class="truncate text-body" x-text="`(${colorName(entry.group)}${entry.group.packs ? ` · ${entry.group.packs} ${entry.group.packs === 1 ? 'pack' : 'packs'}` : ''})`"></span>
                                                        </p>
                                                        <p x-show="groupSizeSummary(entry.group)" class="text-[11px] leading-4 text-body" x-text="groupSizeSummary(entry.group)"></p>
                                                        <div class="flex flex-wrap items-center gap-2 pt-0.5">
                                                            <div x-show="entry.group.packs" class="pos-stepper bg-bg-elevated">
                                                                <button type="button" @click="stepGroupPacks(entry.group, -1)" aria-label="{{ __('Decrease packs') }}"><i class="ph ph-minus"></i></button>
                                                                <span x-text="entry.group.packs"></span>
                                                                <button type="button" @click="stepGroupPacks(entry.group, 1)" :disabled="entry.group.packs >= maxPacksForGroup(entry.group)" aria-label="{{ __('Increase packs') }}"><i class="ph ph-plus"></i></button>
                                                            </div>
                                                            @if($canDiscount)
                                                                <label class="flex items-center gap-1 text-[11px] text-body">{{ __('Disc.') }}<input type="number" min="0" step="0.01" class="pos-mini-input h-7! w-14!" x-model="entry.group.discount" @input.debounce.400ms="invalidate()" :aria-label="`Discount for ${colorName(entry.group)}`"></label>
                                                            @endif
                                                            <button type="button" class="pos-icon-btn size-7! hover:text-error" @click="remove(entry.group.key)" :aria-label="`Remove ${colorName(entry.group)}`"><i class="ph ph-x"></i></button>
                                                        </div>
                                                    </div>
                                                    <div class="flex shrink-0 items-center gap-2">
                                                        <div class="text-right">
                                                            <p x-show="unitPiecePrice(entry) !== null" class="text-[11px] text-body tabular-nums" x-text="`${money(unitPiecePrice(entry))} / pc`"></p>
                                                            <p class="text-sm font-semibold text-title tabular-nums" x-text="lineTotal(entry.index) === null ? '…' : money(lineTotal(entry.index))"></p>
                                                        </div>
                                                        <div class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border-default bg-bg-elevated p-0.5">
                                                            <img x-show="colorThumbUrl(entry.group)" :src="colorThumbUrl(entry.group)" :alt="colorName(entry.group)" class="h-full w-full object-contain">
                                                            <i x-show="!colorThumbUrl(entry.group)" class="ph ph-image text-sm text-body" aria-hidden="true"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="flex items-center justify-between border-t border-border-default pt-2 text-xs">
                                        <span class="text-body">{{ __('Wholesale group') }}</span>
                                        <span class="text-body">{{ __('Group total:') }} <strong class="text-sm text-title tabular-nums" x-text="sectionTotal(section) === null ? '…' : money(sectionTotal(section))"></strong></span>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Customer --}}
                <section class="space-y-3 border-t border-border-default pt-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-body">{{ __('Customer') }}</h3>
                        <select class="pos-select" x-model="fulfillment" aria-label="{{ __('Fulfillment') }}">
                            <option value="pickup">{{ __('Counter pickup') }}</option>
                            <option value="delivery">{{ __('Delivery') }}</option>
                        </select>
                    </div>
                    <template x-if="customerType === 'existing' && contactId">
                        <div class="pos-card">
                            <span class="pos-avatar" x-text="initials(contactLabel.split(' · ')[0])"></span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-title" x-text="contactLabel.split(' · ')[0]"></p>
                                <p class="truncate text-xs text-body" x-text="contactLabel.split(' · ')[1] || ''"></p>
                                <template x-for="balance in balances" :key="balance.currency"><p class="text-xs text-warning" x-text="`Outstanding: ${money(balance.amount, balance.currency)}`"></p></template>
                            </div>
                            <button type="button" class="pos-link" @click="clearCustomer()">{{ __('Change') }}</button>
                        </div>
                    </template>
                    <div x-show="!(customerType === 'existing' && contactId)" class="space-y-2">
                        <button x-ref="posCustomer" type="button" class="pos-card pos-card-empty w-full" @click="openCustomerModal()"><i class="ph ph-user-plus"></i>{{ __('Add customer') }}<kbd class="text-[10px] text-body">F4</kbd></button>
                        <div class="flex gap-2 text-xs">
                            <button type="button" class="pos-chip" :class="customerType === 'walk_in' && 'pos-chip-active'" @click="customerType = 'walk_in'" :disabled="fulfillment === 'delivery'">{{ __('Walk-in') }}</button>
                        </div>
                    </div>
                    <template x-if="fulfillment === 'delivery' && customerType === 'existing'">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <h4 class="text-xs font-medium uppercase tracking-wide text-body">{{ __('Shipping address') }}</h4>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="pos-link" x-show="address.line1" @click="openAddressModal('edit')">{{ __('Edit') }}</button>
                                    <button type="button" class="pos-link" x-show="savedAddresses.length > 1" @click="openAddressModal()">{{ __('Change') }}</button>
                                    <button type="button" class="pos-link" @click="openAddressModal('create')">+ {{ __('New address') }}</button>
                                </div>
                            </div>
                            <template x-if="address.line1">
                                <div class="pos-card">
                                    <i class="ph ph-map-pin shrink-0 text-lg text-primary"></i>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-title" x-text="address.name"></p>
                                        <p class="text-xs text-body" x-text="addressSummary()"></p>
                                        <p class="text-xs text-body" x-text="address.phone"></p>
                                    </div>
                                </div>
                            </template>
                            <button type="button" x-show="!address.line1" class="pos-card pos-card-empty w-full" @click="openAddressModal()"><i class="ph ph-plus"></i>{{ __('Add shipping address') }}</button>
                            <template x-if="quote && quote.shipping_options.length">
                                <select class="form-input" x-model="shippingMethod" aria-label="{{ __('Shipping method') }}">
                                    <option value="">{{ __('Best available shipping') }}</option>
                                    <template x-for="option in quote.shipping_options" :key="option.id"><option :value="option.id" x-text="`${option.name} · ${money(option.price, option.currency)}`"></option></template>
                                </select>
                            </template>
                        </div>
                    </template>
                    <label x-show="fulfillment === 'pickup'" class="flex items-center gap-2 text-xs text-body"><input type="checkbox" class="app-checkbox" x-model="handover" :disabled="customerType === 'walk_in'">{{ __('Hand over goods now') }}</label>
                </section>

                {{-- Totals --}}
                <section class="space-y-2 border-t border-border-default pt-3 text-sm">
                    @if($canDiscount)
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-body">{{ __('Order discount') }}</span>
                            <div class="flex items-center gap-1">
                                <select class="pos-select" x-model="discountType" aria-label="{{ __('Discount type') }}"><option value="amount" x-text="currencySymbol()"></option><option value="percent">%</option></select>
                                <input type="number" min="0" step="0.01" class="pos-mini-input w-20" x-model="discountValue" aria-label="{{ __('Discount value') }}">
                            </div>
                        </div>
                    @endif
                    <dl class="space-y-1.5">
                        <div class="flex justify-between text-body"><dt>{{ __('Subtotal') }}</dt><dd x-text="quote ? money(quote.subtotal) : '—'"></dd></div>
                        <div x-show="quote && Number(quote.discount_amount) > 0" class="flex justify-between text-body"><dt>{{ __('Discount') }}</dt><dd class="text-primary" x-text="quote ? `− ${money(quote.discount_amount)}` : ''"></dd></div>
                        <div x-show="fulfillment === 'delivery'" class="flex justify-between text-body"><dt>{{ __('Shipping') }}</dt><dd x-text="!quote ? '—' : (quote.shipping_quote_required ? 'Quote required' : money(quote.shipping_amount))"></dd></div>
                        <div class="flex items-baseline justify-between border-t border-border-default pt-2"><dt class="font-medium text-title">{{ __('Total') }}</dt><dd class="text-2xl font-semibold text-title" x-text="!quote ? (previewing ? '…' : '—') : (quote.total === null ? 'Awaiting quote' : money(quote.total))"></dd></div>
                    </dl>
                    <p x-show="error" x-cloak class="rounded-md bg-error/10 px-3 py-2 text-xs text-error" x-text="error" role="alert"></p>
                    <template x-if="quote"><div><template x-for="stock in quote.availability.filter(item => item.required > item.available)" :key="stock.variant_id"><p class="text-xs text-error" x-text="`${stock.sku}: only ${stock.available} available`"></p></template></div></template>
                </section>

                {{-- Payment --}}
                <section x-show="quote && !quote.shipping_quote_required" class="space-y-2 border-t border-border-default pt-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-body">{{ __('Payment') }}</h3>
                        <select class="pos-select" x-model="paymentType" aria-label="{{ __('Payment today') }}">
                            <option value="full">{{ __('Paid in full') }}</option>
                            <option value="partial" :disabled="customerType === 'walk_in'">{{ __('Deposit') }}</option>
                            <option value="none" :disabled="customerType === 'walk_in'">{{ __('On credit') }}</option>
                        </select>
                    </div>
                    <template x-for="row in payments" :key="row.key">
                        <div class="grid grid-cols-[minmax(0,1fr)_6.5rem_auto] items-center gap-2">
                            <select class="form-input" x-model="row.method" aria-label="{{ __('Payment method') }}">
                                <template x-for="method in config.paymentMethods" :key="method.id"><option :value="method.id" x-text="method.name"></option></template>
                            </select>
                            <input type="number" min="0" :step="1 / (10 ** precision())" class="form-input" x-model="row.amount" aria-label="{{ __('Amount') }}">
                            <button type="button" class="pos-icon-btn hover:text-error" x-show="payments.length > 1" @click="removePaymentRow(row.key)" aria-label="{{ __('Remove payment') }}"><i class="ph ph-x"></i></button>
                            <input x-show="row.method !== 'cash'" class="form-input col-span-3" x-model="row.reference" maxlength="150" placeholder="{{ __('Reference (optional)') }}" aria-label="{{ __('Payment reference') }}">
                            <template x-if="row.method === 'cash'">
                                <div class="col-span-3 space-y-2">
                                    <input type="number" min="0" :step="1 / (10 ** precision())" class="form-input" x-model="row.tendered" placeholder="{{ __('Cash received') }}" aria-label="{{ __('Cash received') }}">
                                    <div class="flex flex-wrap gap-1.5"><template x-for="value in quickCash()" :key="value"><button type="button" class="pos-chip" @click="setTendered(value)" x-text="money(value)"></button></template></div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <div class="flex items-center justify-between text-xs">
                        <button type="button" x-show="paymentType !== 'none' && remaining() > 0" class="font-medium text-primary hover:underline" @click="addPaymentRow()">+ {{ __('Split payment') }}</button>
                        <span x-show="cashRow() && change() > 0" class="ml-auto text-sm font-medium text-primary" x-text="`Change ${money(change())}`"></span>
                    </div>
                    <p x-show="paymentType !== 'full'" class="text-xs text-body" x-text="`Balance after today: ${money(remaining())}`"></p>
                </section>
                <p x-show="quote && quote.shipping_quote_required" class="text-xs text-body">{{ __('Save the order now; prepare a delivery quote from the order page before collecting payment.') }}</p>

                <button type="submit" class="btn btn-primary w-full py-3 text-base" :disabled="!canCheckout()">
                    <i class="ph ph-check-circle"></i>
                    <span x-text="busy ? 'Processing…' : (!quote ? 'Charge' : (quote.shipping_quote_required ? 'Save order for quote' : `Charge ${money(paymentType === 'none' ? 0 : paidTotal())}`))"></span>
                </button>
                <p x-show="paymentError()" class="text-center text-xs text-body" x-text="paymentError()"></p>
                <p class="hidden text-center text-[11px] text-body lg:block">F2 {{ __('search') }} · F4 {{ __('customer') }} · F8 {{ __('hold') }} · F9 {{ __('charge') }}</p>
            </form>
        </div>

        {{-- Held sales drawer --}}
        <div x-show="heldOpen" x-cloak class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="heldOpen = false" @keydown.escape.window="heldOpen = false">
            <aside class="flex h-full w-full max-w-sm flex-col gap-3 bg-bg-elevated p-5 shadow-xl">
                <div class="flex items-center justify-between"><h2 class="font-semibold text-title">{{ __('Held sales') }}</h2><button type="button" class="pos-icon-btn" @click="heldOpen = false" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button></div>
                <p x-show="!held.length" class="text-sm text-body">{{ __('No held sales.') }}</p>
                <div class="space-y-2 overflow-y-auto">
                    <template x-for="sale in held" :key="sale.id">
                        <div class="rounded-md border border-border-default p-3">
                            <p class="text-sm font-medium text-title" x-text="sale.label"></p>
                            <p class="text-xs text-body" x-text="`${sale.payload.lines.length} lines · ${new Date(sale.created_at).toLocaleString()}`"></p>
                            <div class="mt-2 flex gap-2"><button type="button" class="btn btn-primary btn-sm" @click="resumeHeld(sale)">{{ __('Resume') }}</button><button type="button" class="btn btn-outline btn-sm" @click="deleteHeld(sale)">{{ __('Discard') }}</button></div>
                        </div>
                    </template>
                </div>
            </aside>
        </div>

        {{-- Product dialog --}}
        <dialog x-ref="posProductModal" aria-labelledby="pos-product-title" class="pos-dialog max-w-4xl" @close="closeProductModal()">
            <template x-if="selectedProduct">
                <div class="space-y-5">
                    <div class="flex items-start justify-between gap-3 border-b border-border-default pb-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary" x-text="mode === 'retail' ? 'Retail' : (selectedProduct.pack_pricing ? 'Wholesale · Pack order' : 'Wholesale')"></p>
                            <h2 id="pos-product-title" class="mt-0.5 text-lg font-semibold text-title" x-text="selectedProduct.name"></h2>
                            <p class="text-xs text-body" x-text="selectedProduct.sku"></p>
                        </div>
                        <button type="button" class="pos-icon-btn" autofocus @click="$refs.posProductModal.close()" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button>
                    </div>
                    <div class="grid gap-5 md:grid-cols-[15rem_minmax(0,1fr)]">
                        <div class="space-y-2.5">
                            <button type="button" class="group relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-xl border border-border-default bg-section p-3 transition hover:border-primary/50" @click="openGallery(activePreviewImages(), activePreviewTitle(), $event.currentTarget)" :disabled="!activePreviewImages().length" :aria-label="`View images for ${activePreviewTitle()}`">
                                <img x-show="activePreviewImage() && !detailImageFailed" :src="activePreviewImage()" :alt="activePreviewTitle()" class="max-h-full max-w-full object-contain transition group-hover:scale-[1.02]" x-on:error="detailImageFailed = true">
                                <span x-show="!activePreviewImage() || detailImageFailed" class="text-sm text-body">{{ __('No image') }}</span>
                                <span x-show="activePreviewImages().length" class="absolute right-2 bottom-2 inline-flex items-center gap-1 rounded-full bg-bg-elevated/90 px-2.5 py-1 text-[11px] font-medium text-title shadow-sm backdrop-blur-xs">
                                    <i class="ph ph-images"></i> <span x-text="activePreviewImages().length"></span>
                                </span>
                            </button>
                            <div x-show="activePreviewColor()" class="flex items-center justify-between gap-2 rounded-lg bg-section px-3 py-2 text-xs">
                                <span class="flex min-w-0 items-center gap-2 font-medium text-title">
                                    <span class="size-3.5 shrink-0 rounded-full border border-black/15" :style="{ backgroundColor: activePreviewColor()?.swatch_image_url ? 'transparent' : colorHex(activePreviewColor()), backgroundImage: activePreviewColor()?.swatch_image_url ? `url('${activePreviewColor().swatch_image_url}')` : 'none' }"></span>
                                    <span class="truncate" x-text="activePreviewColor()?.name"></span>
                                </span>
                                <span class="shrink-0 text-body" x-text="`${activePreviewColor()?.available ?? 0} in stock`"></span>
                            </div>
                        </div>
                        <div class="min-w-0 space-y-4">
                            {{-- Retail --}}
                            <template x-if="mode === 'retail'">
                                <div class="space-y-4">
                                    <p class="text-lg font-semibold text-title" x-text="money(selectedProduct.single_piece_price || selectedProduct.variants[0]?.price)"></p>
                                    <div x-show="selectedProduct.colors.length" class="space-y-2"><h3 class="text-sm font-medium text-title">{{ __('Color') }} <span class="font-normal text-body" x-text="selectedProduct.colors.find(color => String(color.id) === selectedColorId)?.name"></span></h3>
                                        <div class="flex flex-wrap gap-2"><template x-for="color in selectedProduct.colors" :key="color.id"><button type="button" class="pos-swatch" :class="selectedColorId === String(color.id) && 'pos-swatch-active'" :style="{ backgroundColor: color.swatch_image_url ? 'transparent' : colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none' }" @click="selectColor(color)" :aria-label="`${color.name}, ${color.available} available`" :aria-pressed="selectedColorId === String(color.id)"></button></template></div>
                                    </div>
                                    <div class="space-y-2"><h3 class="text-sm font-medium text-title">{{ __('Size') }}</h3>
                                        <div class="flex flex-wrap gap-2"><template x-for="variant in selectedProduct.variants.filter(item => item.available > 0 && (!selectedColorId || String(item.color_id) === selectedColorId))" :key="variant.id"><button type="button" class="pos-chip" :class="selectedVariantId === String(variant.id) && 'pos-chip-active'" @click="selectVariant(variant)" :aria-pressed="selectedVariantId === String(variant.id)" x-text="`${variant.size} · ${variant.available}`"></button></template></div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="pos-stepper"><button type="button" @click="selectedQuantity = Math.max(1, selectedQuantity - 1)" aria-label="{{ __('Decrease') }}"><i class="ph ph-minus"></i></button><input type="number" min="1" class="w-12 border-0 bg-transparent text-center" x-model.number="selectedQuantity" aria-label="{{ __('Pieces') }}"><button type="button" @click="selectedQuantity++" aria-label="{{ __('Increase') }}"><i class="ph ph-plus"></i></button></div>
                                        <button type="button" class="btn btn-primary flex-1" @click="addRetailSelection()" :disabled="!selectedVariantId || selectedQuantity < 1 || selectedQuantity > (selectedProduct.variants.find(item => String(item.id) === selectedVariantId)?.available || 0)">{{ __('Add to cart') }}</button>
                                    </div>
                                </div>
                            </template>
                            {{-- Wholesale whole packs --}}
                            <template x-if="mode === 'wholesale' && selectedProduct.pack_pricing">
                                <div class="space-y-4">
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="row in selectedProduct.pack_prices" :key="row.packs">
                                            <div class="pos-tier-pill" :class="activePackTier()?.packs === row.packs && 'pos-tier-pill-active'">
                                                <span class="font-semibold text-title" x-text="`${row.packs} ${row.packs === 1 ? 'pack' : 'packs'} · ${money(row.price)}`"></span>
                                                <span class="text-[11px] text-body" x-text="`${money(Number(row.price) / row.packs)} / pack`"></span>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-2">
                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-body">{{ __('1. Choose colors') }}</h3>
                                            <span class="text-xs text-body" x-text="`Min ${selectedProduct.min_packs} packs`"></span>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="color in selectedProduct.colors.filter(item => item.available > 0)" :key="color.id">
                                                <button type="button" class="pos-color-pill"
                                                    :class="{ 'pos-color-pill-selected': (packCounts[color.id] || 0) > 0, 'pos-color-pill-active': selectedWholesaleColorId === String(color.id) && (packCounts[color.id] || 0) > 0 }"
                                                    :disabled="maxPacksFor(color.id) < 1"
                                                    :aria-pressed="(packCounts[color.id] || 0) > 0"
                                                    @click="togglePackColor(color)">
                                                    <span class="pos-swatch pos-swatch-sm" :style="{ backgroundColor: color.swatch_image_url ? 'transparent' : colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none' }"></span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-xs font-semibold text-title" x-text="color.name"></span>
                                                        <span class="block text-[11px] text-body" x-text="(packCounts[color.id] || 0) > 0 ? `${packCounts[color.id]} pack${packCounts[color.id] === 1 ? '' : 's'}` : `${maxPacksFor(color.id)} packs avail`"></span>
                                                    </span>
                                                    <i x-show="(packCounts[color.id] || 0) > 0" class="ph-fill ph-check-circle text-sm text-primary" aria-hidden="true"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <h3 class="text-xs font-semibold uppercase tracking-wide text-body">{{ __('2. Pack counts & size breakdown') }}</h3>
                                        <div x-show="!totalPacks()" class="rounded-lg border border-dashed border-border-default bg-section/60 px-4 py-5 text-center text-xs text-body">
                                            {{ __('Tap one or more colors above to add packs and preview sizes.') }}
                                        </div>
                                        <div x-show="totalPacks() > 0" class="max-h-56 space-y-2 overflow-y-auto pr-0.5">
                                            <template x-for="color in selectedProduct.colors.filter(item => (packCounts[item.id] || 0) > 0)" :key="color.id">
                                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border px-3 py-2.5 transition"
                                                    :class="selectedWholesaleColorId === String(color.id) ? 'border-primary bg-primary/5' : 'border-border-default bg-bg-elevated'">
                                                    <button type="button" class="flex min-w-0 flex-1 items-center gap-2.5 text-left" @click="selectedWholesaleColorId = String(color.id); detailImageFailed = false;">
                                                        <span class="pos-swatch pos-swatch-sm" :style="{ backgroundColor: color.swatch_image_url ? 'transparent' : colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none' }"></span>
                                                        <span class="min-w-0">
                                                            <span class="block truncate text-sm font-medium text-title" x-text="color.name"></span>
                                                            <span class="block truncate text-xs text-body" x-text="packRatioText(color.id) ? `${packRatioText(color.id)} · ${packPiecesFor(color.id)} pcs/pack` : `${color.available} available`"></span>
                                                        </span>
                                                    </button>
                                                    <div class="flex items-center gap-2">
                                                        <div class="pos-stepper">
                                                            <button type="button" @click="stepPack(color.id, -1)" aria-label="{{ __('Fewer packs') }}"><i class="ph ph-minus"></i></button>
                                                            <input type="number" min="1" :max="maxPacksFor(color.id)" class="w-10 border-0 bg-transparent text-center text-xs font-semibold text-title" :value="packCounts[color.id] || 0" @change="setPackCount(color.id, $event.target.value)" aria-label="{{ __('Packs') }}">
                                                            <button type="button" @click="stepPack(color.id, 1)" :disabled="(packCounts[color.id] || 0) >= maxPacksFor(color.id)" aria-label="{{ __('More packs') }}"><i class="ph ph-plus"></i></button>
                                                        </div>
                                                        <span x-show="packPiecesFor(color.id) > 0" class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary tabular-nums" x-text="`${(packCounts[color.id] || 0) * packPiecesFor(color.id)} pcs`"></span>
                                                        <button type="button" class="pos-icon-btn hover:text-error" @click="stepPack(color.id, -(packCounts[color.id] || 0))" :aria-label="`Remove ${color.name}`"><i class="ph ph-trash"></i></button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="pos-modal-footer space-y-2.5">
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-medium text-title" x-text="`${totalPacks()} ${totalPacks() === 1 ? 'pack' : 'packs'}${totalPackPieces() ? ` · ${totalPackPieces()} pcs` : ''}`"></span>
                                                <span class="text-body" x-text="`MOQ: ${wholesaleStats().combined} / ${wholesaleStats().target} packs`"></span>
                                            </div>
                                            <div class="pos-moq-track"><div class="pos-moq-fill" :style="`width: ${wholesaleStats().progress}%`"></div></div>
                                        </div>
                                        <p x-show="totalPacks() > 0 && packSelectionError()" class="text-xs text-error" x-text="packSelectionError()"></p>
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <span class="block text-[11px] text-body">{{ __('Estimated total') }}</span>
                                                <span class="text-lg font-semibold text-title tabular-nums" x-text="money(packTotal(selectedProduct, totalPacks()))"></span>
                                            </div>
                                            <button type="button" class="btn btn-primary px-5" @click="addPackSelection()" :disabled="!canAddPackSelection()" x-text="totalPacks() ? `Add ${totalPacks()} ${totalPacks() === 1 ? 'pack' : 'packs'}` : 'Add packs'"></button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            {{-- Wholesale custom sizes & inline pack builder --}}
                            <template x-if="mode === 'wholesale' && !selectedProduct.pack_pricing">
                                <div class="space-y-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p x-show="selectedProduct.wholesale_price" class="text-xl font-semibold text-title" x-text="`${money(selectedProduct.wholesale_price)} / pc`"></p>
                                        <div class="flex flex-wrap gap-1.5 text-[11px]">
                                            <span class="rounded-full bg-section px-2.5 py-1 font-medium text-body" x-text="`Min ${selectedProduct.minimum} pcs`"></span>
                                            <span class="rounded-full bg-section px-2.5 py-1 font-medium text-body" x-text="`${selectedProduct.minimum_sizes}+ sizes/color`"></span>
                                            <span class="rounded-full bg-section px-2.5 py-1 font-medium text-body" x-text="`${selectedProduct.color_minimum}+ pc/size`"></span>
                                        </div>
                                    </div>

                                    {{-- Step 1: Visual Color Picker --}}
                                    <div class="space-y-2">
                                        <h3 class="text-xs font-semibold uppercase tracking-wide text-body">{{ __('1. Choose colors (tap to add & edit)') }}</h3>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="color in selectedProduct.colors.filter(item => item.available > 0)" :key="color.id">
                                                <button type="button" class="pos-color-pill"
                                                    :class="{ 'pos-color-pill-selected': selectedWholesaleColorIds.includes(String(color.id)), 'pos-color-pill-active': selectedWholesaleColorId === String(color.id) && selectedWholesaleColorIds.includes(String(color.id)) }"
                                                    :aria-pressed="selectedWholesaleColorIds.includes(String(color.id))"
                                                    @click="toggleWholesaleColor(color)">
                                                    <span class="pos-swatch pos-swatch-sm" :style="{ backgroundColor: color.swatch_image_url ? 'transparent' : colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none' }"></span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-xs font-semibold text-title" x-text="color.name"></span>
                                                        <span class="block text-[11px] text-body" x-text="selectedWholesaleColorIds.includes(String(color.id)) ? `${wholesaleColorPieces(color.id)} pcs` : `${color.available} avail`"></span>
                                                    </span>
                                                    <i x-show="selectedWholesaleColorIds.includes(String(color.id))" class="ph-fill ph-check-circle text-sm text-primary" aria-hidden="true"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Step 2: Inline Packs & Size Quantities --}}
                                    <div class="space-y-2.5">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h3 class="text-xs font-semibold uppercase tracking-wide text-body">{{ __('2. Adjust packs & size quantities') }}</h3>
                                            <div x-show="selectedWholesaleColorIds.length > 1" class="flex flex-wrap gap-1.5">
                                                <template x-for="colorId in selectedWholesaleColorIds" :key="colorId">
                                                    <button type="button" class="pos-chip flex items-center gap-1.5" :class="selectedWholesaleColorId === colorId && 'pos-chip-active'" @click="focusWholesaleColor(colorId)">
                                                        <span x-text="`${wholesaleColorName(colorId)} · ${wholesaleColorPieces(colorId)}`"></span>
                                                        <span class="opacity-70 hover:opacity-100" @click.stop="removeWholesaleColor(colorId)" :aria-label="`Remove ${wholesaleColorName(colorId)}`">×</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        <div x-show="!selectedWholesaleColorIds.length" class="rounded-lg border border-dashed border-border-default bg-section/60 px-4 py-5 text-center text-xs text-body">
                                            {{ __('Tap one or more colors above to set pack counts and size quantities.') }}
                                        </div>

                                        <template x-if="selectedWholesaleColorId && selectedWholesaleColorIds.includes(selectedWholesaleColorId) && wholesaleSizeQuantities[selectedWholesaleColorId]">
                                            <div class="space-y-3 rounded-xl border border-border-default bg-section/40 p-3.5">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="pos-swatch pos-swatch-sm" :style="{ backgroundColor: activePreviewColor()?.swatch_image_url ? 'transparent' : colorHex(activePreviewColor()), backgroundImage: activePreviewColor()?.swatch_image_url ? `url('${activePreviewColor().swatch_image_url}')` : 'none' }"></span>
                                                        <div>
                                                            <p class="text-sm font-semibold text-title" x-text="wholesaleColorName(selectedWholesaleColorId)"></p>
                                                            <p class="text-[11px] text-body" x-text="`${wholesaleColorPieces(selectedWholesaleColorId)} pcs selected · ${wholesaleRemainingStock(activePreviewColor() || { id: selectedWholesaleColorId, available: 0 })} left`"></p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex items-center gap-1.5 rounded-lg border border-border-default bg-bg-elevated px-2 py-1 text-xs">
                                                            <span class="font-medium text-body">{{ __('Packs') }}</span>
                                                            <div class="pos-stepper border-0">
                                                                <button type="button" class="size-7!" @click="stepWholesaleColorPack(selectedWholesaleColorId, -1)" :disabled="wholesalePackCount(selectedWholesaleColorId) <= 1" aria-label="{{ __('Decrease packs') }}"><i class="ph ph-minus"></i></button>
                                                                <span class="min-w-6! px-1! font-semibold" x-text="wholesalePackCount(selectedWholesaleColorId)"></span>
                                                                <button type="button" class="size-7!" @click="stepWholesaleColorPack(selectedWholesaleColorId, 1)" aria-label="{{ __('Increase packs') }}"><i class="ph ph-plus"></i></button>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="pos-icon-btn hover:text-error" @click="removeWholesaleColor(selectedWholesaleColorId)" :aria-label="`Remove ${wholesaleColorName(selectedWholesaleColorId)}`"><i class="ph ph-trash"></i></button>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                                    <template x-for="variant in selectedProduct.variants.filter(item => String(item.color_id) === selectedWholesaleColorId)" :key="variant.id">
                                                        <div class="pos-size-tile" :class="Number(wholesaleSizeQuantities[selectedWholesaleColorId][variant.size] || 0) > 0 && 'pos-size-tile-active'">
                                                            <div class="flex items-baseline justify-between gap-1">
                                                                <span class="text-sm font-semibold text-title" x-text="variant.size"></span>
                                                                <span class="text-[11px] text-body" x-text="`${variant.available} avail`"></span>
                                                            </div>
                                                            <div class="pos-stepper w-full justify-between bg-bg-elevated">
                                                                <button type="button" @click="stepWholesaleSize(selectedWholesaleColorId, variant.size, -1)" :disabled="variant.available < 1 || Number(wholesaleSizeQuantities[selectedWholesaleColorId][variant.size] || 0) < 1" aria-label="{{ __('Decrease size') }}"><i class="ph ph-minus"></i></button>
                                                                <input type="number" min="0" step="1" class="w-12 border-0 bg-transparent text-center text-xs font-semibold text-title" :max="variant.available" :disabled="variant.available < 1" x-model.number="wholesaleSizeQuantities[selectedWholesaleColorId][variant.size]" @change="clampWholesaleSize(selectedWholesaleColorId, variant.size)" :aria-label="`${variant.size} pieces`">
                                                                <button type="button" @click="stepWholesaleSize(selectedWholesaleColorId, variant.size, 1)" :disabled="variant.available < 1 || Number(wholesaleSizeQuantities[selectedWholesaleColorId][variant.size] || 0) >= variant.available" aria-label="{{ __('Increase size') }}"><i class="ph ph-plus"></i></button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                                <p x-show="wholesaleColorSelectionError(selectedWholesaleColorId)" class="text-xs text-error" x-text="wholesaleColorSelectionError(selectedWholesaleColorId)"></p>
                                            </div>
                                        </template>
                                    </div>

                                    <div class="pos-modal-footer space-y-2.5">
                                        <div class="space-y-1">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-medium text-title" x-text="`${selectedWholesaleColorIds.length} ${selectedWholesaleColorIds.length === 1 ? 'color' : 'colors'} · ${wholesaleStats().totalPieces} pcs`"></span>
                                                <span class="text-body" x-text="`MOQ: ${wholesaleStats().combined} / ${wholesaleStats().target} pcs`"></span>
                                            </div>
                                            <div class="pos-moq-track"><div class="pos-moq-fill" :style="`width: ${wholesaleStats().progress}%`"></div></div>
                                        </div>
                                        <p class="text-xs text-error" x-show="selectedWholesaleColorIds.length && wholesaleSelectionError() && !wholesaleColorSelectionError(selectedWholesaleColorId)" x-text="wholesaleSelectionError()"></p>
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <span class="block text-[11px] text-body">{{ __('Estimated total') }}</span>
                                                <span class="text-lg font-semibold text-title tabular-nums" x-text="money(wholesaleStats().estimatedTotal)"></span>
                                            </div>
                                            <button type="button" class="btn btn-primary px-5" @click="addWholesaleSelection()" :disabled="!canAddWholesaleSelection()" x-text="selectedWholesaleColorIds.length ? `Add ${selectedWholesaleColorIds.length} ${selectedWholesaleColorIds.length === 1 ? 'color' : 'colors'} (${wholesaleStats().totalPieces} pcs)` : '{{ __('Add selected colors') }}'"></button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </dialog>

        {{-- Customer: search or create --}}
        <dialog x-ref="posCustomerModal" aria-labelledby="pos-customer-title" class="pos-dialog max-w-lg">
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="pos-customer-title" class="font-semibold text-title" x-text="customerView === 'search' ? '{{ __('Select customer') }}' : '{{ __('New customer') }}'"></h2>
                        <p class="text-xs text-body" x-text="customerView === 'search' ? '{{ __('Search your contacts or create a new customer.') }}' : '{{ __('Saved to your contacts and selected for this sale.') }}'"></p>
                    </div>
                    <button type="button" class="pos-icon-btn" @click="$refs.posCustomerModal.close()" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button>
                </div>
                <div x-show="customerView === 'search'" class="space-y-3">
                    <input x-ref="posCustomerSearch" type="search" class="form-input" x-model="customerQuery" @input.debounce.300ms="searchCustomers()" placeholder="{{ __('Search by name, phone or email') }}" aria-label="{{ __('Search customer') }}">
                    <ul class="pos-result-list">
                        <template x-for="contact in customerResults" :key="contact.id">
                            <li>
                                <button type="button" class="pos-result" @click="pickCustomer(contact)">
                                    <span class="pos-avatar" x-text="initials(contact.name)"></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-title" x-text="contact.name"></span><span class="block truncate text-xs text-body" x-text="[contact.phone, contact.email].filter(Boolean).join(' · ')"></span></span>
                                    <span class="shrink-0 text-xs text-body" x-show="contact.addresses.length" x-text="`${contact.addresses.length} ${contact.addresses.length === 1 ? 'address' : 'addresses'}`"></span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <p x-show="!customerResults.length && !customerSearching" x-cloak class="py-3 text-center text-sm text-body">{{ __('No customers found.') }}</p>
                    <button type="button" class="btn btn-outline w-full" @click="showCustomerForm()">+ {{ __('Create new customer') }}</button>
                </div>
                <form x-show="customerView === 'create'" x-cloak class="space-y-4" @submit.prevent="saveCustomer()">
                    <div class="pos-form-grid">
                        <label class="pos-field"><span>{{ __('Name') }}</span><input x-ref="posCustomerName" class="form-input" x-model="customerForm.name" maxlength="150" required autocomplete="off"></label>
                        <div class="pos-field"><label for="pos-customer-phone">{{ __('Phone') }}</label><x-ui.phone-input id="pos-customer-phone" x-model="customerForm.phone" :required="true" /></div>
                        <label class="pos-field pos-field-wide"><span>{{ __('Email (optional)') }}</span><input type="email" class="form-input" x-model="customerForm.email" maxlength="255" autocomplete="off"></label>
                    </div>
                    <p x-show="formError" x-cloak class="rounded-md bg-error/10 px-3 py-2 text-xs text-error" x-text="formError" role="alert"></p>
                    <div class="flex justify-between gap-2"><button type="button" class="btn btn-outline" @click="customerView = 'search'">{{ __('Back to search') }}</button><button type="submit" class="btn btn-primary" :disabled="savingCustomer">{{ __('Save customer') }}</button></div>
                </form>
            </div>
        </dialog>

        {{-- Shipping address: choose or add --}}
        <dialog x-ref="posAddressModal" aria-labelledby="pos-address-title" class="pos-dialog max-w-lg">
            <div class="space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="pos-address-title" class="font-semibold text-title" x-text="addressView === 'choose' ? '{{ __('Choose shipping address') }}' : '{{ __('New shipping address') }}'"></h2>
                        <p class="text-xs text-body" x-text="contactLabel.split(' · ')[0]"></p>
                    </div>
                    <button type="button" class="pos-icon-btn" @click="$refs.posAddressModal.close()" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button>
                </div>
                <div x-show="addressView === 'choose'" class="space-y-3">
                    <ul class="pos-result-list">
                        <template x-for="saved in savedAddresses" :key="saved.id">
                            <li>
                                <button type="button" class="pos-result" :class="String(saved.id) === addressId && 'pos-result-active'" @click="chooseAddress(saved)">
                                    <i class="ph ph-map-pin shrink-0 text-lg text-primary"></i>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium text-title" x-text="saved.name"></span><span class="block text-xs text-body" x-text="addressSummary(saved)"></span><span class="block text-xs text-body" x-text="saved.phone"></span></span>
                                    <span x-show="saved.is_default" class="pos-badge pos-badge-ws">{{ __('Default') }}</span>
                                </button>
                            </li>
                        </template>
                    </ul>
                    <button type="button" class="btn btn-outline w-full" @click="startAddressForm()">+ {{ __('Add new address') }}</button>
                </div>
                <form x-show="addressView === 'create'" x-cloak class="space-y-4" @submit.prevent="saveAddress()">
                    @include('commerce::user.partials.pos-address-fields')
                    <p x-show="formError" x-cloak class="rounded-md bg-error/10 px-3 py-2 text-xs text-error" x-text="formError" role="alert"></p>
                    <div class="flex justify-between gap-2">
                        <button type="button" class="btn btn-outline" x-show="savedAddresses.length" @click="addressView = 'choose'">{{ __('Back') }}</button>
                        <span x-show="!savedAddresses.length"></span>
                        <button type="submit" class="btn btn-primary" :disabled="savingAddress">{{ __('Save address') }}</button>
                    </div>
                </form>
            </div>
        </dialog>

        {{-- Gallery --}}
        <dialog x-ref="posGallery" aria-labelledby="pos-gallery-title" class="pos-dialog max-w-4xl" @close="restoreGalleryFocus()" @keydown.left.prevent="moveGallery(-1)" @keydown.right.prevent="moveGallery(1)">
            <div class="flex items-center justify-between gap-3"><h2 id="pos-gallery-title" class="min-w-0 truncate font-semibold" x-text="galleryTitle"></h2><button type="button" class="pos-icon-btn" autofocus @click="$refs.posGallery.close()" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button></div>
            <template x-if="galleryImages.length">
                <div>
                    <template x-for="(image, index) in galleryImages" :key="`${image.url}-${index}`">
                        <div :class="galleryIndex === index ? '' : 'hidden'" class="py-4"><img :src="image.url" :alt="image.alt || galleryTitle" class="mx-auto max-h-[65dvh] max-w-full object-contain" :class="galleryFailures[image.url] ? 'hidden' : ''" x-on:error="galleryFailures[image.url] = true"></div>
                    </template>
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" class="btn btn-outline btn-sm" :class="galleryImages.length > 1 ? '' : 'invisible'" @click="moveGallery(-1)">{{ __('Previous') }}</button>
                        <p aria-live="polite" class="text-sm text-body"><span x-text="galleryIndex + 1"></span> / <span x-text="galleryImages.length"></span></p>
                        <button type="button" class="btn btn-outline btn-sm" :class="galleryImages.length > 1 ? '' : 'invisible'" @click="moveGallery(1)">{{ __('Next') }}</button>
                    </div>
                </div>
            </template>
            <p x-show="!galleryImages.length" class="py-16 text-center text-sm text-body">{{ __('No images available.') }}</p>
        </dialog>
    </div>
</x-layouts.user>
