<x-layouts.user :title="__('POS')">
    @php
        $config = ['csrf' => csrf_token(), 'products' => route('user.commerce.pos.products'), 'preview' => route('user.commerce.pos.preview'), 'checkout' => route('user.commerce.pos.checkout'), 'balance' => route('user.commerce.pos.customer-balance', '__CONTACT__'), 'currency' => $settings->currency, 'precision' => $settings->precision(), 'contacts' => $contacts];
    @endphp
    <div class="space-y-6" x-data="commercePos" data-config="{{ json_encode($config) }}">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div><h1 class="heading-3 text-title">{{ __('Point of sale') }}</h1><p class="text-sm text-body">{{ __('Retail pieces and wholesale size quantities in one sale') }} · <span x-text="currencySymbol()">{{ $settings->currency }}</span></p></div>
            <x-ui.button href="{{ route('user.commerce.orders.index', ['source' => 'pos']) }}" variant="outline">{{ __('POS orders') }}</x-ui.button>
        </header>
        <p x-show="error" x-cloak x-text="error" role="alert" class="rounded-md border border-danger/30 bg-danger/10 p-4 text-sm text-title"></p>
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-md border border-border bg-bg-elevated p-3 sm:p-4">
            <div><h2 class="font-semibold text-title" x-text="mode === 'retail' ? 'Retail sale' : 'Wholesale order'"></h2><p class="text-sm text-body" x-text="mode === 'retail' ? 'Add multiple products and variants to one order.' : (wholesaleProductId ? 'One product per order · choose sizes by color.' : 'Choose one product, then enter quantities by color.')"></p></div>
            <div class="inline-flex rounded-md bg-section p-1" role="group" aria-label="{{ __('Sale type') }}">
                <button type="button" class="rounded-sm px-4 py-2 text-sm font-semibold" :class="mode === 'retail' ? 'bg-bg-elevated text-title shadow-sm' : 'text-body'" :aria-pressed="mode === 'retail'" @click="switchMode('retail')">{{ __('Retail') }}</button>
                <button type="button" class="rounded-sm px-4 py-2 text-sm font-semibold" :class="mode === 'wholesale' ? 'bg-bg-elevated text-title shadow-sm' : 'text-body'" :aria-pressed="mode === 'wholesale'" @click="switchMode('wholesale')">{{ __('Wholesale') }}</button>
            </div>
        </div>
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="min-w-0 section-card space-y-4">
                <div><label for="pos-search" class="form-label">{{ __('Search products or SKU') }}</label><input id="pos-search" type="search" class="form-input" x-model="search" @input.debounce.300ms="loadProducts()" placeholder="{{ __('Product name or SKU') }}"></div>
                <p x-show="searching" class="text-sm text-body" role="status">{{ __('Loading products…') }}</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                    <template x-for="product in products" :key="product.id">
                        <button type="button" class="group flex min-w-0 flex-col overflow-hidden rounded-md border border-border bg-bg-elevated p-2.5 text-left transition hover:border-primary/50 hover:shadow-sm focus-visible:outline-2 focus-visible:outline-primary sm:p-3" @click="openProduct(product, $event.currentTarget)" :disabled="busy || !product.available || (mode === 'retail' ? !['retail', 'both'].includes(product.mode) : !product.wholesale_enabled || (wholesaleProductId && wholesaleProductId !== product.id))" :aria-label="`Select ${product.name}`" x-data="{ imageFailed: false }">
                            <span class="mb-3 flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-sm bg-section p-2">
                                <img x-show="product.image_url && !imageFailed" :src="product.image_url" :alt="product.name" class="h-full w-full object-contain transition group-hover:scale-[1.02]" loading="lazy" x-on:error="imageFailed = true">
                                <span x-show="!product.image_url || imageFailed" class="flex flex-col items-center gap-1 text-xs text-body"><i class="ph ph-image text-2xl" aria-hidden="true"></i>{{ __('No image') }}</span>
                            </span>
                            <span class="line-clamp-2 min-h-10 text-sm font-semibold leading-5 text-title" x-text="product.name"></span>
                            <span class="mt-1 truncate text-xs text-body" x-text="product.sku"></span>
                            <span class="mt-2 flex items-center justify-between gap-2 text-xs text-body"><span x-text="`${product.available} pieces available`"></span><span class="font-semibold text-title" x-text="mode === 'retail' ? money(product.single_piece_price || product.variants[0]?.price) : (product.wholesale_price ? money(product.wholesale_price) + ' / piece' : 'Price on quote')"></span></span>
                            <span class="mt-3 inline-flex w-full items-center justify-center rounded-sm bg-primary/10 px-3 py-2 text-sm font-semibold text-primary" x-text="mode === 'retail' ? 'Choose variant' : (wholesaleProductId === product.id ? 'Add packs' : 'Choose product')"></span>
                        </button>
                    </template>
                </div>
                <p x-show="!searching && !products.length" class="text-sm text-body">{{ __('No available products match this search.') }}</p>
            </section>
            <form class="min-w-0 space-y-6 xl:sticky xl:top-4 xl:max-h-[calc(100dvh-2rem)] xl:overflow-y-auto xl:pr-1" @submit.prevent="checkout()">
                <fieldset :disabled="busy" class="space-y-6">
                    <section class="section-card space-y-4">
                        <div class="flex items-center justify-between gap-3"><div><h2 class="heading-5 text-title" x-text="mode === 'retail' ? 'Retail cart' : 'Wholesale order summary'"></h2><p x-show="mode === 'wholesale' && groups.length" class="mt-1 text-xs text-body" x-text="wholesaleSummary()"></p></div><button x-show="mode === 'wholesale' && groups.length" type="button" class="text-sm font-medium text-danger" @click="clearWholesaleDraft()">{{ __('Clear product') }}</button></div>
                        <p x-show="!groups.length" class="text-sm text-body" x-text="mode === 'retail' ? 'Choose products to build a multi-item cart.' : 'Choose one product and enter quantities for each color.'"></p>
                        <template x-for="(group, index) in groups" :key="group.key">
                            <article class="space-y-3 rounded-md border border-border p-4">
                                <div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold text-title" x-text="group.product.name"></h3><p x-show="group.mode === 'wholesale'" class="text-sm text-body" x-text="group.product.colors.find(color => String(color.id) === String(group.color_id))?.name"></p></div><button type="button" class="text-sm text-danger" @click="remove(group.key)" :aria-label="`Remove ${group.product.name}`">{{ __('Remove') }}</button></div>
                                <template x-if="group.mode === 'retail'"><div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem]"><div><label :for="`variant-${group.key}`" class="form-label">{{ __('Variant / available stock') }}</label><select :id="`variant-${group.key}`" class="form-input" x-model="group.variant_id" @change="invalidate()" required><template x-for="variant in group.product.variants" :key="variant.id"><option :value="variant.id" :disabled="variant.available < 1" x-text="`${variant.size} · ${variant.available} available`"></option></template></select></div><div><label :for="`count-${group.key}`" class="form-label">{{ __('Pieces') }}</label><input :id="`count-${group.key}`" type="number" min="1" max="10000" step="1" class="form-input" x-model.number="group.count" @input="invalidate()" required></div></div></template>
                                <template x-if="group.mode === 'wholesale'"><p class="text-xs text-body" x-text="ratio(group)"></p></template>
                                <template x-if="quote && quote.groups[index]"><div class="border-t border-border pt-2 text-sm text-title"><p x-text="`${quote.groups[index].quantity} pieces · ${money(quote.groups[index].items.reduce((sum, item) => sum + Number(item.line_total), 0))}`"></p><template x-for="item in quote.groups[index].items" :key="item.variant_id"><p class="text-xs text-body" x-text="`${item.sku}: ${item.quantity} × ${money(item.unit_price)}`"></p></template></div></template>
                            </article>
                        </template>
                    </section>
                    <section class="section-card space-y-4">
                        <h2 class="heading-5 text-title">{{ __('Customer and fulfillment') }}</h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label for="pos-fulfillment" class="form-label">{{ __('Fulfillment') }}</label><select id="pos-fulfillment" class="form-input" x-model="fulfillment"><option value="pickup">{{ __('Counter pickup') }}</option><option value="delivery">{{ __('Delivery') }}</option></select></div>
                            <div><label for="pos-customer-type" class="form-label">{{ __('Customer') }}</label><select id="pos-customer-type" class="form-input" x-model="customerType"><option value="walk_in" :disabled="fulfillment === 'delivery'">{{ __('Walk-in — fully paid pickup') }}</option><option value="existing">{{ __('Existing customer') }}</option><option value="new">{{ __('New customer') }}</option></select></div>
                        </div>
                        <template x-if="customerType === 'existing'"><div class="space-y-2"><label for="pos-contact" class="form-label">{{ __('Choose customer') }}</label><select id="pos-contact" class="form-input" x-model="contactId" @change="selectContact()" required><option value="">{{ __('Select customer') }}</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->name }} · {{ $contact->phone }}</option>@endforeach</select><template x-for="balance in balances" :key="balance.currency"><p class="text-sm text-body" x-text="`Customer outstanding: ${money(balance.amount, balance.currency)}`"></p></template></div></template>
                        <template x-if="customerType === 'new'"><div class="grid gap-4 sm:grid-cols-2"><div><label for="pos-name" class="form-label">{{ __('Name') }}</label><input id="pos-name" class="form-input" x-model="customer.name" maxlength="150" required></div><div><label for="pos-phone" class="form-label">{{ __('Phone') }}</label><input id="pos-phone" class="form-input" x-model="customer.phone" maxlength="30" required></div><div class="sm:col-span-2"><label for="pos-email" class="form-label">{{ __('Email (optional)') }}</label><input id="pos-email" type="email" class="form-input" x-model="customer.email" maxlength="255"></div></div></template>
                        <template x-if="fulfillment === 'delivery'">
                            <div class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @foreach(['name' => 'Recipient name', 'phone' => 'Delivery phone', 'line1' => 'Address', 'line2' => 'Address line 2', 'city' => 'City', 'state' => 'State / region', 'postal_code' => 'Postal code', 'country' => 'Country code'] as $field => $label)
                                        <div><label for="pos-address-{{ $field }}" class="form-label">{{ __($label) }}</label><input id="pos-address-{{ $field }}" class="form-input" x-model="address.{{ $field }}" maxlength="{{ $field === 'country' ? 2 : ($field === 'phone' || $field === 'postal_code' ? 30 : ($field === 'name' ? 150 : ($field === 'city' || $field === 'state' ? 120 : 255))) }}" @if(in_array($field, ['name', 'phone', 'line1', 'city', 'country'])) required @endif @if($field === 'country') placeholder="US" @endif></div>
                                    @endforeach
                                </div>
                                <template x-if="quote && quote.shipping_options.length"><div><label for="pos-shipping" class="form-label">{{ __('Shipping method') }}</label><select id="pos-shipping" class="form-input" x-model="shippingMethod"><option value="">{{ __('Best available shipping') }}</option><template x-for="option in quote.shipping_options" :key="option.id"><option :value="option.id" x-text="`${option.name} · ${money(option.price, option.currency)}`"></option></template></select></div></template>
                            </div>
                        </template>
                        <template x-if="fulfillment === 'pickup'"><label class="flex items-center gap-2 text-sm text-title"><input type="checkbox" x-model="handover" :disabled="customerType === 'walk_in'">{{ __('Hand over goods now') }}</label></template>
                        <p class="text-xs text-body">{{ __('A named customer is required for credit sales. Stock is deducted when goods are collected or dispatched.') }}</p>
                    </section>
                    <section class="section-card space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="heading-5 text-title">{{ __('Checkout') }}</h2><button type="button" class="btn btn-outline" @click="preview()" :disabled="busy || !groups.length">{{ __('Calculate prices') }}</button></div>
                        <p x-show="!quote" class="text-sm text-body">{{ __('Calculate prices after changing the cart or shipping details.') }}</p>
                        <template x-if="quote">
                            <div class="space-y-4">
                                <dl class="space-y-2 text-sm text-title"><div class="flex justify-between"><dt>{{ __('Subtotal') }}</dt><dd x-text="money(quote.subtotal)"></dd></div><div class="flex justify-between"><dt>{{ __('Shipping') }}</dt><dd x-text="quote.shipping_quote_required ? 'Quote required' : money(quote.shipping_amount)"></dd></div><div class="flex justify-between border-t border-border pt-2 font-semibold"><dt>{{ __('Total') }}</dt><dd x-text="quote.total === null ? 'Awaiting quote' : money(quote.total)"></dd></div></dl>
                                <template x-for="stock in quote.availability" :key="stock.variant_id"><p class="text-xs" :class="stock.required > stock.available ? 'text-danger' : 'text-body'" x-text="`${stock.sku}: ${stock.required} required / ${stock.available} available`"></p></template>
                                <template x-if="!quote.shipping_quote_required"><div class="space-y-4">
                                    <div><label for="pos-payment-type" class="form-label">{{ __('Payment today') }}</label><select id="pos-payment-type" class="form-input" x-model="paymentType"><option value="full">{{ __('Full payment') }}</option><option value="partial" :disabled="customerType === 'walk_in'">{{ __('Partial payment / deposit') }}</option><option value="none" :disabled="customerType === 'walk_in'">{{ __('No payment — customer owes total') }}</option></select></div>
                                    <template x-if="paymentType !== 'none'"><div class="grid gap-4 sm:grid-cols-2">
                                        <div><label for="pos-method" class="form-label">{{ __('Payment method') }}</label><select id="pos-method" class="form-input" x-model="method">@foreach($paymentMethods as $paymentMethod)<option value="{{ $paymentMethod['id'] }}">{{ $paymentMethod['name'] }}</option>@endforeach</select></div>
                                        <template x-if="paymentType === 'partial'"><div><label for="pos-amount" class="form-label">{{ __('Amount paid') }}</label><input id="pos-amount" type="number" :step="1 / (10 ** quote.precision)" min="0" :max="quote.total" class="form-input" x-model="amount" required></div></template>
                                        <div><label for="pos-payment-reference" class="form-label">{{ __('Payment reference (optional)') }}</label><input id="pos-payment-reference" class="form-input" x-model="reference" maxlength="150"></div>
                                        <template x-if="method === 'cash'"><div><label for="pos-tendered" class="form-label">{{ __('Cash received (optional)') }}</label><input id="pos-tendered" type="number" :step="1 / (10 ** quote.precision)" min="0" class="form-input" x-model="tendered"><p class="mt-1 text-xs text-body" x-text="`Change: ${change()}`"></p></div></template>
                                    </div></template>
                                    <p class="text-sm text-title" x-text="`Remaining balance: ${money(Math.max(0, Number(quote.total) - Number(paymentAmount())))}`"></p>
                                </div></template>
                                <p x-show="quote.shipping_quote_required" class="text-sm text-body">{{ __('Save the order and reserve stock. Prepare a delivery quote from the order page before collecting payment.') }}</p>
                                <button type="submit" class="btn btn-primary w-full" :disabled="busy || !hasStock()" x-text="quote.shipping_quote_required ? 'Save order for shipping quote' : (fulfillment === 'pickup' && handover ? 'Complete sale and hand over' : 'Save sale and reserve stock')"></button>
                            </div>
                        </template>
                    </section>
                </fieldset>
                <p x-show="busy" role="status" class="text-sm text-body">{{ __('Processing…') }}</p>
            </form>
        </div>
        <dialog x-ref="posProductModal" aria-labelledby="pos-product-title" class="m-auto max-h-[92dvh] w-[calc(100%_-_1rem)] max-w-4xl overflow-y-auto rounded-md border border-border bg-bg-elevated p-4 text-title shadow-xl backdrop:bg-black/70 sm:w-[calc(100%_-_2rem)] sm:p-6" @close="closeProductModal()">
            <template x-if="selectedProduct">
                <div class="space-y-5">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-primary" x-text="mode === 'retail' ? 'Retail selection' : 'Wholesale quantity selection'"></p><h2 id="pos-product-title" class="heading-5 mt-1" x-text="selectedProduct.name"></h2><p class="text-sm text-body" x-text="selectedProduct.sku"></p></div><button type="button" class="btn btn-outline shrink-0" autofocus @click="$refs.posProductModal.close()">{{ __('Close') }}</button></div>
                    <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                        <div class="space-y-3">
                            <button type="button" class="flex aspect-square w-full items-center justify-center overflow-hidden rounded-sm bg-section p-4" @click="openGallery(selectedProduct.gallery, selectedProduct.name, $event.currentTarget)" :disabled="!selectedProduct.gallery.length" :aria-label="`View images for ${selectedProduct.name}`">
                                <img x-show="selectedProduct.image_url && !detailImageFailed" :src="selectedProduct.image_url" :alt="selectedProduct.name" class="max-h-full max-w-full object-contain" x-on:error="detailImageFailed = true">
                                <span x-show="!selectedProduct.image_url || detailImageFailed" class="text-sm text-body">{{ __('No image available') }}</span>
                            </button>
                            <p class="text-sm text-body" x-text="`${selectedProduct.available} pieces available`"></p>
                            <p x-show="mode === 'retail'" class="font-semibold text-title" x-text="`Unit price: ${money(selectedProduct.single_piece_price || selectedProduct.variants[0]?.price)}`"></p>
                        </div>
                        <div class="space-y-5">
                            <section x-show="mode === 'retail' && selectedProduct.colors.length" class="space-y-2"><h3 class="text-sm font-semibold text-title">{{ __('Color') }} <span class="font-normal text-body" x-text="selectedProduct.colors.find(color => String(color.id) === selectedColorId)?.name"></span></h3><div class="flex flex-wrap gap-2">
                                <template x-for="color in selectedProduct.colors" :key="color.id"><button type="button" class="flex h-10 w-10 items-center justify-center rounded-full border-2 p-0.5 focus-visible:outline-2 focus-visible:outline-primary" :class="selectedColorId === String(color.id) ? 'border-primary' : 'border-border'" :style="{ backgroundColor: color.swatch_image_url ? 'transparent' : colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none', backgroundSize: 'cover', backgroundPosition: 'center' }" :data-color-id="color.id" @click="selectColor(color)" :aria-label="`${color.name} color, ${color.available} pieces available`" :aria-pressed="selectedColorId === String(color.id)"><span x-show="selectedColorId === String(color.id)" class="flex h-5 w-5 items-center justify-center rounded-full bg-white/90 text-xs text-title shadow-sm" aria-hidden="true">✓</span></button></template>
                            </div></section>
                            <section x-show="mode === 'wholesale'" class="space-y-3"><div><h3 class="text-sm font-semibold text-title">{{ __('Available colors') }}</h3><p class="text-xs text-body" x-text="`${selectedWholesaleColorIds.length} selected · click a color row to edit its size quantities`"></p></div>
                                <div class="space-y-2"><template x-for="color in selectedProduct.colors.filter(item => item.available > 0)" :key="color.id"><button type="button" class="flex w-full items-center gap-3 rounded-sm border border-border bg-bg-elevated p-3 text-left transition hover:border-primary/50 focus-visible:outline-2 focus-visible:outline-primary" @click="openWholesaleColor(color, $event.currentTarget)" :aria-label="`Edit ${color.name}, ${color.available} pieces available`" :aria-pressed="selectedWholesaleColorIds.includes(String(color.id))">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-sm border border-border" :style="{ backgroundColor: color.swatch_image_url ? 'transparent' : colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none', backgroundSize: 'cover', backgroundPosition: 'center' }"><span x-show="selectedWholesaleColorIds.includes(String(color.id))" class="rounded-full bg-white/90 px-1.5 py-0.5 text-xs font-bold text-primary" aria-hidden="true">✓</span></span>
                                    <span class="min-w-0 flex-1"><span class="block font-medium text-title" x-text="color.name"></span><span class="text-xs text-body" x-text="`${color.available} pieces available`"></span></span>
                                    <span class="shrink-0 text-sm font-semibold text-primary" x-text="selectedWholesaleColorIds.includes(String(color.id)) ? 'Edit' : 'Select'"></span>
                                </button></template><p x-show="!selectedProduct.colors.some(color => color.available > 0)" class="rounded-sm bg-section p-3 text-sm text-body">{{ __('No colors have stock available for this product.') }}</p></div>
                                <div x-show="selectedWholesaleColorIds.length" class="space-y-2 border-t border-border pt-3"><h4 class="text-sm font-semibold text-title">{{ __('Selected color quantities') }}</h4><template x-for="colorId in selectedWholesaleColorIds" :key="`selected-${colorId}`"><div class="flex items-start justify-between gap-3 rounded-sm bg-section p-3"><div class="min-w-0"><p class="font-medium text-title" x-text="wholesaleColorName(colorId)"></p><p class="text-xs text-body" x-text="Object.entries(wholesaleSizeQuantities[colorId] || {}).filter(([, quantity]) => Number(quantity) > 0).map(([size, quantity]) => `${size}: ${quantity}`).join(' · ')"></p></div><button type="button" class="shrink-0 text-sm font-medium text-danger" @click="removeWholesaleColor(colorId)" :aria-label="`Remove ${wholesaleColorName(colorId)}`">{{ __('Remove') }}</button></div></template></div>
                            </section>
                            <template x-if="mode === 'retail'"><div class="space-y-4"><section class="space-y-2"><h3 class="text-sm font-semibold text-title">{{ __('Available sizes') }}</h3><div class="flex flex-wrap gap-2"><template x-for="variant in selectedProduct.variants.filter(item => item.available > 0 && (!selectedColorId || String(item.color_id) === selectedColorId))" :key="variant.id"><button type="button" class="rounded-sm border px-3 py-2 text-sm" :class="selectedVariantId === String(variant.id) ? 'border-primary bg-primary/10 text-primary' : 'border-border text-title'" @click="selectVariant(variant)" :aria-pressed="selectedVariantId === String(variant.id)" x-text="`${variant.size} · ${variant.available}`"></button></template><p x-show="!selectedProduct.variants.some(item => item.available > 0 && (!selectedColorId || String(item.color_id) === selectedColorId))" class="text-sm text-body">{{ __('No sizes in stock for this color.') }}</p></div></section>
                                <div class="max-w-32"><label for="pos-modal-retail-quantity" class="form-label">{{ __('Pieces') }}</label><input id="pos-modal-retail-quantity" type="number" min="1" :max="selectedProduct.variants.find(item => String(item.id) === selectedVariantId)?.available || 1" class="form-input" x-model.number="selectedQuantity"></div>
                                <button type="button" class="btn btn-primary w-full sm:w-auto" @click="addRetailSelection()" :disabled="!selectedVariantId || selectedQuantity < 1 || selectedQuantity > (selectedProduct.variants.find(item => String(item.id) === selectedVariantId)?.available || 0)">{{ __('Add to retail cart') }}</button></div>
                            </template>
                            <template x-if="mode === 'wholesale'"><div class="space-y-4">
                                <p class="text-xs text-body" x-text="`Choose at least ${selectedProduct.minimum_sizes} sizes per color. Each selected size needs ${selectedProduct.color_minimum} pieces; the full order needs ${selectedProduct.minimum} pieces.`"></p>
                                <p class="text-sm text-danger" x-show="selectedWholesaleColorIds.length && !canAddWholesaleSelection()" x-text="wholesaleSelectionError()" role="status"></p>
                                <p x-show="selectedProduct.wholesale_price" class="text-sm font-medium text-title" x-text="`From ${money(selectedProduct.wholesale_price)} per piece · final price confirmed at checkout`"></p>
                                <button type="button" class="btn btn-primary w-full sm:w-auto" @click="addWholesaleSelection()" :disabled="!canAddWholesaleSelection()">{{ __('Add selected colors') }}</button>
                            </div></template>
                        </div>
                    </div>
                </div>
            </template>
        </dialog>
        <dialog x-ref="posWholesaleColorModal" aria-labelledby="pos-wholesale-color-title" class="m-auto max-h-[90dvh] w-[calc(100%_-_1rem)] max-w-3xl overflow-y-auto rounded-md border border-border bg-bg-elevated p-4 text-title shadow-xl backdrop:bg-black/70 sm:w-[calc(100%_-_2rem)] sm:p-6" @close="closeWholesaleColor()">
            <template x-if="selectedProduct && selectedWholesaleColorId">
                <div class="space-y-5">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-primary">{{ __('Wholesale color details') }}</p><h2 id="pos-wholesale-color-title" class="heading-5 mt-1" x-text="selectedProduct.name"></h2><p class="text-sm text-body" x-text="`${wholesaleColorName()} · ${selectedProduct.colors.find(color => String(color.id) === selectedWholesaleColorId)?.available || 0} pieces available`"></p></div><button type="button" class="btn btn-outline shrink-0" autofocus @click="$refs.posWholesaleColorModal.close()">{{ __('Close') }}</button></div>
                    <div class="grid gap-5 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                        <div class="space-y-3"><div class="flex aspect-square items-center justify-center overflow-hidden rounded-sm bg-section p-4"><img x-show="selectedProduct.image_url && !detailImageFailed" :src="selectedProduct.image_url" :alt="`${selectedProduct.name} · ${wholesaleColorName()}`" class="max-h-full max-w-full object-contain" x-on:error="detailImageFailed = true"><span x-show="!selectedProduct.image_url || detailImageFailed" class="text-sm text-body">{{ __('No image available') }}</span></div><p class="text-sm text-body" x-text="selectedProduct.sku"></p></div>
                        <div class="space-y-4"><div><h3 class="text-sm font-semibold text-title">{{ __('Pieces to order by size') }}</h3><p class="text-xs text-body" x-text="`Select at least ${selectedProduct.minimum_sizes} sizes. Each selected size needs at least ${selectedProduct.color_minimum} pieces.`"></p></div>
                            <div class="space-y-2"><template x-for="variant in selectedProduct.variants.filter(item => String(item.color_id) === selectedWholesaleColorId)" :key="variant.id"><label class="flex min-w-0 items-center justify-between gap-3 rounded-sm bg-section p-3"><span class="min-w-0"><span class="font-medium text-title" x-text="variant.size"></span><span class="ml-2 text-xs text-body" x-text="`${variant.available} available`"></span></span><input type="number" min="0" step="1" class="form-input w-28" :max="variant.available" :disabled="variant.available < 1" x-model.number="wholesaleSizeQuantities[selectedWholesaleColorId][variant.size]" :aria-label="`${variant.size} total pieces for ${wholesaleColorName()}`"></label></template><p x-show="!selectedProduct.variants.some(item => String(item.color_id) === selectedWholesaleColorId)" class="rounded-sm bg-section p-3 text-sm text-body">{{ __('No sizes are available for this color.') }}</p></div>
                            <p x-show="wholesaleColorSelectionError()" class="text-sm text-danger" x-text="wholesaleColorSelectionError()" role="status"></p>
                            <button type="button" class="btn btn-primary w-full sm:w-auto" @click="saveWholesaleColor()" :disabled="Boolean(wholesaleColorSelectionError())" x-text="selectedWholesaleColorIds.includes(selectedWholesaleColorId) ? 'Update color quantities' : 'Add color'"></button>
                        </div>
                    </div>
                </div>
            </template>
        </dialog>
        <dialog x-ref="posGallery" aria-labelledby="pos-gallery-title" class="m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-4xl overflow-y-auto rounded-md border border-border bg-bg-elevated p-4 text-title shadow-xl backdrop:bg-black/70 sm:p-6" @close="restoreGalleryFocus()" @keydown.left.prevent="moveGallery(-1)" @keydown.right.prevent="moveGallery(1)">
            <div class="flex items-center justify-between gap-3">
                <h2 id="pos-gallery-title" class="min-w-0 truncate font-semibold" x-text="galleryTitle"></h2>
                <button type="button" class="btn btn-outline shrink-0" autofocus @click="$refs.posGallery.close()">{{ __('Close') }}</button>
            </div>
            <template x-if="galleryImages.length">
                <div>
                    <template x-for="(image, index) in galleryImages" :key="`${image.url}-${index}`">
                        <div :class="galleryIndex === index ? '' : 'hidden'" class="py-4">
                            <img :src="image.url" :alt="image.alt || galleryTitle" class="mx-auto max-h-[65dvh] max-w-full object-contain" :class="galleryFailures[image.url] ? 'hidden' : ''" x-on:error="galleryFailures[image.url] = true">
                            <p class="hidden py-16 text-center text-body" :class="galleryFailures[image.url] ? '!block' : ''" role="status">{{ __('Image unavailable') }}</p>
                        </div>
                    </template>
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" class="btn btn-outline" :class="galleryImages.length > 1 ? '' : 'invisible'" :disabled="galleryImages.length < 2" @click="moveGallery(-1)">{{ __('Previous') }}</button>
                        <p aria-live="polite" class="text-sm text-body"><span x-text="galleryIndex + 1"></span> / <span x-text="galleryImages.length"></span></p>
                        <button type="button" class="btn btn-outline" :class="galleryImages.length > 1 ? '' : 'invisible'" :disabled="galleryImages.length < 2" @click="moveGallery(1)">{{ __('Next') }}</button>
                    </div>
                </div>
            </template>
            <p x-show="!galleryImages.length" class="py-16 text-center text-sm text-body" role="status">{{ __('No images available for this color.') }}</p>
        </dialog>
    </div>
</x-layouts.user>
