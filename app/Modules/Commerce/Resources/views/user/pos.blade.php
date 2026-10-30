<x-layouts.user :title="__('POS')">
    @php
        $config = [
            'csrf' => csrf_token(),
            'products' => route('user.commerce.pos.products'),
            'preview' => route('user.commerce.pos.preview'),
            'checkout' => route('user.commerce.pos.checkout'),
            'customers' => route('user.commerce.pos.customers'),
            'held' => route('user.commerce.pos.held.index'),
            'heldDestroy' => route('user.commerce.pos.held.destroy', '__HELD__'),
            'balance' => route('user.commerce.pos.customer-balance', '__CONTACT__'),
            'currency' => $settings->currency,
            'precision' => $settings->precision(),
            'paymentMethods' => $paymentMethods,
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
                @if($wholesaleEnabled ?? true)
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
                    <template x-for="(group, index) in groups" :key="group.key">
                        <article class="pos-line">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-title" x-text="group.product.name"></p>
                                    <p class="text-xs text-body">
                                        <span class="pos-badge" :class="group.mode === 'wholesale' && 'pos-badge-ws'" x-text="group.mode === 'retail' ? 'Retail' : (group.packs ? 'Packs' : 'Wholesale')"></span>
                                        <span x-text="group.mode === 'retail' ? variantLabel(group) : colorName(group)"></span>
                                    </p>
                                    <p x-show="group.mode === 'wholesale' && group.ratio" class="mt-0.5 text-xs text-body" x-text="ratio(group)"></p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-title" x-text="lineTotal(index) === null ? '…' : money(lineTotal(index))"></span>
                            </div>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div x-show="group.mode === 'retail' || group.packs" class="pos-stepper">
                                    <button type="button" @click="group.mode === 'retail' ? stepQuantity(group, -1) : stepGroupPacks(group, -1)" aria-label="{{ __('Decrease') }}"><i class="ph ph-minus"></i></button>
                                    <span x-text="group.mode === 'retail' ? group.count : `${group.packs} × ${packPieces(group)}`"></span>
                                    <button type="button" @click="group.mode === 'retail' ? stepQuantity(group, 1) : stepGroupPacks(group, 1)" aria-label="{{ __('Increase') }}"><i class="ph ph-plus"></i></button>
                                </div>
                                <span x-show="group.mode === 'wholesale' && !group.packs"></span>
                                <div class="flex items-center gap-2">
                                    @if($canDiscount)
                                        <label class="flex items-center gap-1 text-xs text-body">{{ __('Disc.') }}<input type="number" min="0" step="0.01" class="pos-mini-input" x-model="group.discount" @input.debounce.400ms="invalidate()" :aria-label="`Discount for ${group.product.name}`"></label>
                                    @endif
                                    <button type="button" class="pos-icon-btn hover:text-error" @click="remove(group.key)" :aria-label="`Remove ${group.product.name}`"><i class="ph ph-x"></i></button>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                {{-- Customer --}}
                <section class="space-y-2 border-t border-border-default pt-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-xs font-medium uppercase tracking-wide text-body">{{ __('Customer') }}</h3>
                        <select class="pos-select" x-model="fulfillment" aria-label="{{ __('Fulfillment') }}">
                            <option value="pickup">{{ __('Counter pickup') }}</option>
                            <option value="delivery">{{ __('Delivery') }}</option>
                        </select>
                    </div>
                    <template x-if="customerType === 'existing' && contactId">
                        <div class="flex items-center justify-between rounded-md bg-primary/5 px-3 py-2 text-sm">
                            <span class="min-w-0 truncate text-title" x-text="contactLabel"></span>
                            <button type="button" class="text-xs text-body hover:text-error" @click="clearCustomer()">{{ __('Change') }}</button>
                        </div>
                    </template>
                    <template x-for="balance in balances" :key="balance.currency"><p class="text-xs text-warning" x-text="`Outstanding: ${money(balance.amount, balance.currency)}`"></p></template>
                    <div x-show="!(customerType === 'existing' && contactId)" class="relative">
                        <input x-ref="posCustomer" type="search" class="form-input" x-model="customerQuery" @input.debounce.300ms="searchCustomers()" @focus="customerQuery === '' && searchCustomers()" placeholder="{{ __('Search customer by name or phone (F4)') }}" aria-label="{{ __('Search customer') }}">
                        <div x-show="customerResults.length" x-cloak @click.outside="customerResults = []" class="pos-dropdown">
                            <template x-for="contact in customerResults" :key="contact.id">
                                <button type="button" class="pos-dropdown-item" @click="pickCustomer(contact)"><span class="font-medium text-title" x-text="contact.name"></span><span class="text-xs text-body" x-text="contact.phone"></span></button>
                            </template>
                        </div>
                        <div class="mt-2 flex gap-2 text-xs">
                            <button type="button" class="pos-chip" :class="customerType === 'walk_in' && 'pos-chip-active'" @click="customerType = 'walk_in'" :disabled="fulfillment === 'delivery'">{{ __('Walk-in') }}</button>
                            <button type="button" class="pos-chip" :class="customerType === 'new' && 'pos-chip-active'" @click="customerType = 'new'">+ {{ __('New customer') }}</button>
                        </div>
                    </div>
                    <template x-if="customerType === 'new'">
                        <div class="grid grid-cols-2 gap-2">
                            <input class="form-input" x-model="customer.name" maxlength="150" placeholder="{{ __('Name') }}" required aria-label="{{ __('Name') }}">
                            <input class="form-input" x-model="customer.phone" maxlength="30" placeholder="{{ __('Phone') }}" required aria-label="{{ __('Phone') }}">
                            <input type="email" class="form-input col-span-2" x-model="customer.email" maxlength="255" placeholder="{{ __('Email (optional)') }}" aria-label="{{ __('Email') }}">
                        </div>
                    </template>
                    <template x-if="fulfillment === 'delivery'">
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(['name' => 'Recipient name', 'phone' => 'Delivery phone', 'line1' => 'Address', 'line2' => 'Address line 2', 'city' => 'City', 'state' => 'State / region', 'postal_code' => 'Postal code', 'country' => 'Country code (US)'] as $field => $label)
                                <input class="form-input @if(in_array($field, ['line1', 'line2'])) col-span-2 @endif" x-model="address.{{ $field }}" placeholder="{{ __($label) }}" aria-label="{{ __($label) }}" maxlength="{{ $field === 'country' ? 2 : 255 }}" @if(in_array($field, ['name', 'phone', 'line1', 'city', 'country'])) required @endif>
                            @endforeach
                            <template x-if="quote && quote.shipping_options.length">
                                <select class="form-input col-span-2" x-model="shippingMethod" aria-label="{{ __('Shipping method') }}">
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
        <dialog x-ref="posProductModal" aria-labelledby="pos-product-title" class="pos-dialog max-w-3xl" @close="closeProductModal()">
            <template x-if="selectedProduct">
                <div class="space-y-5">
                    <div class="flex items-start justify-between gap-3">
                        <div><p class="text-xs font-medium uppercase tracking-wide text-primary" x-text="mode === 'retail' ? 'Retail' : (selectedProduct.pack_pricing ? 'Wholesale packs' : 'Wholesale')"></p><h2 id="pos-product-title" class="mt-1 text-lg font-semibold text-title" x-text="selectedProduct.name"></h2><p class="text-sm text-body" x-text="selectedProduct.sku"></p></div>
                        <button type="button" class="pos-icon-btn" autofocus @click="$refs.posProductModal.close()" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button>
                    </div>
                    <div class="grid gap-5 md:grid-cols-[14rem_minmax(0,1fr)]">
                        <button type="button" class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-md bg-section p-3" @click="openGallery(selectedProduct.gallery, selectedProduct.name, $event.currentTarget)" :disabled="!selectedProduct.gallery.length" :aria-label="`View images for ${selectedProduct.name}`">
                            <img x-show="selectedProduct.image_url && !detailImageFailed" :src="selectedProduct.image_url" :alt="selectedProduct.name" class="max-h-full max-w-full object-contain" x-on:error="detailImageFailed = true">
                            <span x-show="!selectedProduct.image_url || detailImageFailed" class="text-sm text-body">{{ __('No image') }}</span>
                        </button>
                        <div class="space-y-4">
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
                                <div class="space-y-3">
                                    <div class="rounded-md border border-border-default">
                                        <template x-for="row in selectedProduct.pack_prices" :key="row.packs"><div class="flex justify-between border-b border-border-default px-3 py-1.5 text-sm last:border-0"><span class="text-body" x-text="`${row.packs} packs`"></span><span class="font-medium text-title" x-text="money(row.price)"></span></div></template>
                                    </div>
                                    <p class="text-xs text-body" x-text="`1 pack = 1 color box. Minimum ${selectedProduct.min_packs} packs.`"></p>
                                    <div class="space-y-2"><template x-for="color in selectedProduct.colors.filter(item => item.available > 0)" :key="color.id">
                                        <div class="flex items-center gap-3 rounded-md bg-section px-3 py-2">
                                            <span class="pos-swatch pos-swatch-sm" :style="{ backgroundColor: color.swatch_image_url ? 'transparent' : colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none' }"></span>
                                            <span class="min-w-0 flex-1 text-sm text-title"><span x-text="color.name"></span> <span class="text-xs text-body" x-text="`· ${packPiecesFor(color.id)} pcs/pack`"></span></span>
                                            <div class="pos-stepper"><button type="button" @click="stepPack(color.id, -1)" aria-label="{{ __('Fewer packs') }}"><i class="ph ph-minus"></i></button><span x-text="packCounts[color.id] || 0"></span><button type="button" @click="stepPack(color.id, 1)" aria-label="{{ __('More packs') }}"><i class="ph ph-plus"></i></button></div>
                                        </div>
                                    </template></div>
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm text-body" x-text="`${totalPacks()} packs · ${money(packTotal(selectedProduct, totalPacks()))}`"></span>
                                        <button type="button" class="btn btn-primary" @click="addPackSelection()" :disabled="!totalPacks()">{{ __('Add packs') }}</button>
                                    </div>
                                </div>
                            </template>
                            {{-- Wholesale custom sizes --}}
                            <template x-if="mode === 'wholesale' && !selectedProduct.pack_pricing">
                                <div class="space-y-3">
                                    <p x-show="selectedProduct.wholesale_price" class="text-lg font-semibold text-title" x-text="`${money(selectedProduct.wholesale_price)} / pc`"></p>
                                    <p class="text-xs text-body" x-text="`At least ${selectedProduct.minimum_sizes} sizes per color, ${selectedProduct.color_minimum} pcs per size, ${selectedProduct.minimum} pcs per product.`"></p>
                                    <div class="space-y-2"><template x-for="color in selectedProduct.colors.filter(item => item.available > 0)" :key="color.id">
                                        <button type="button" class="flex w-full items-center gap-3 rounded-md border border-border-default px-3 py-2 text-left transition hover:border-primary/50" @click="openWholesaleColor(color, $event.currentTarget)" :aria-pressed="selectedWholesaleColorIds.includes(String(color.id))">
                                            <span class="pos-swatch pos-swatch-sm" :style="{ backgroundColor: color.swatch_image_url ? 'transparent' : colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none' }"></span>
                                            <span class="min-w-0 flex-1"><span class="block text-sm text-title" x-text="color.name"></span><span class="text-xs text-body" x-text="selectedWholesaleColorIds.includes(String(color.id)) ? Object.entries(wholesaleSizeQuantities[color.id] || {}).filter(([, q]) => Number(q) > 0).map(([s, q]) => `${s}: ${q}`).join(' · ') : `${color.available} available`"></span></span>
                                            <span class="text-xs font-medium text-primary" x-text="selectedWholesaleColorIds.includes(String(color.id)) ? 'Edit' : 'Select'"></span>
                                        </button>
                                    </template></div>
                                    <p class="text-xs text-error" x-show="selectedWholesaleColorIds.length && !canAddWholesaleSelection()" x-text="wholesaleSelectionError()"></p>
                                    <button type="button" class="btn btn-primary w-full" @click="addWholesaleSelection()" :disabled="!canAddWholesaleSelection()">{{ __('Add selected colors') }}</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </dialog>

        {{-- Wholesale color sizes --}}
        <dialog x-ref="posWholesaleColorModal" aria-labelledby="pos-wholesale-color-title" class="pos-dialog max-w-lg" @close="closeWholesaleColor()">
            <template x-if="selectedProduct && selectedWholesaleColorId">
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-3"><div><h2 id="pos-wholesale-color-title" class="font-semibold text-title" x-text="`${selectedProduct.name} · ${wholesaleColorName()}`"></h2><p class="text-xs text-body">{{ __('Pieces to sell by size') }}</p></div><button type="button" class="pos-icon-btn" autofocus @click="$refs.posWholesaleColorModal.close()" aria-label="{{ __('Close') }}"><i class="ph ph-x"></i></button></div>
                    <div class="space-y-2"><template x-for="variant in selectedProduct.variants.filter(item => String(item.color_id) === selectedWholesaleColorId)" :key="variant.id"><label class="flex items-center justify-between gap-3 rounded-md bg-section px-3 py-2"><span class="text-sm text-title"><span x-text="variant.size"></span> <span class="text-xs text-body" x-text="`· ${variant.available} available`"></span></span><input type="number" min="0" step="1" class="form-input w-24" :max="variant.available" :disabled="variant.available < 1" x-model.number="wholesaleSizeQuantities[selectedWholesaleColorId][variant.size]" :aria-label="`${variant.size} pieces`"></label></template></div>
                    <p x-show="wholesaleColorSelectionError()" class="text-xs text-error" x-text="wholesaleColorSelectionError()"></p>
                    <button type="button" class="btn btn-primary w-full" @click="saveWholesaleColor()" :disabled="Boolean(wholesaleColorSelectionError())" x-text="selectedWholesaleColorIds.includes(selectedWholesaleColorId) ? 'Update color' : 'Add color'"></button>
                </div>
            </template>
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
