<x-layouts.user :title="__('POS')">
    @php
        $config = ['csrf' => csrf_token(), 'products' => route('user.commerce.pos.products'), 'preview' => route('user.commerce.pos.preview'), 'checkout' => route('user.commerce.pos.checkout'), 'balance' => route('user.commerce.pos.customer-balance', '__CONTACT__'), 'currency' => $settings->currency, 'precision' => $settings->precision(), 'contacts' => $contacts];
    @endphp
    <div class="space-y-6" x-data="commercePos" data-config="{{ json_encode($config) }}">
        <header class="flex flex-wrap items-center justify-between gap-4">
            <div><h1 class="heading-3 text-title">{{ __('Point of sale') }}</h1><p class="text-sm text-body">{{ __('Retail pieces and wholesale packs in one sale') }} · {{ $settings->currency }}</p></div>
            <x-ui.button href="{{ route('user.commerce.orders.index', ['source' => 'pos']) }}" variant="outline">{{ __('POS orders') }}</x-ui.button>
        </header>
        <p x-show="error" x-cloak x-text="error" role="alert" class="rounded-xl border border-danger/30 bg-danger/10 p-4 text-sm text-title"></p>
        <div class="grid items-start gap-6 xl:grid-cols-5">
            <section class="section-card space-y-4 xl:col-span-2">
                <div><label for="pos-search" class="form-label">{{ __('Search products or SKU') }}</label><input id="pos-search" type="search" class="form-input" x-model="search" @input.debounce.300ms="loadProducts()" placeholder="{{ __('Product name or SKU') }}"></div>
                <p x-show="searching" class="text-sm text-body" role="status">{{ __('Loading products…') }}</p>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                    <template x-for="product in products" :key="product.id">
                        <article class="flex items-center justify-between gap-3 rounded-xl border border-border p-4">
                            <div class="min-w-0"><h2 class="font-semibold text-title" x-text="product.name"></h2><p class="break-all text-xs text-body" x-text="product.sku"></p><p class="text-xs text-body" x-text="`${product.variants.reduce((sum, variant) => sum + variant.available, 0)} pieces available`"></p></div>
                            <button type="button" class="btn btn-outline shrink-0" @click="add(product)" :disabled="busy || !product.variants.length" :aria-label="`Add ${product.name}`">{{ __('Add') }}</button>
                        </article>
                    </template>
                </div>
                <p x-show="!searching && !products.length" class="text-sm text-body">{{ __('No available products match this search.') }}</p>
            </section>
            <form class="space-y-6 xl:col-span-3" @submit.prevent="checkout()">
                <fieldset :disabled="busy" class="space-y-6">
                    <section class="section-card space-y-4">
                        <h2 class="heading-5 text-title">{{ __('Cart') }}</h2>
                        <p x-show="!groups.length" class="text-sm text-body">{{ __('Add a product to start a sale.') }}</p>
                        <template x-for="(group, index) in groups" :key="group.key">
                            <article class="space-y-3 rounded-xl border border-border p-4">
                                <div class="flex items-start justify-between gap-3"><h3 class="font-semibold text-title" x-text="group.product.name"></h3><button type="button" class="text-sm text-danger" @click="remove(group.key)" :aria-label="`Remove ${group.product.name}`">{{ __('Remove') }}</button></div>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div><label :for="`mode-${group.key}`" class="form-label">{{ __('Sell as') }}</label><select :id="`mode-${group.key}`" class="form-input" x-model="group.mode"><option value="retail" :disabled="group.product.mode === 'wholesale'">{{ __('Retail pieces') }}</option><option value="wholesale" :disabled="!group.product.wholesale_enabled">{{ __('Wholesale packs') }}</option></select></div>
                                    <template x-if="group.mode === 'retail'"><div><label :for="`variant-${group.key}`" class="form-label">{{ __('Variant / available stock') }}</label><select :id="`variant-${group.key}`" class="form-input" x-model="group.variant_id" required><template x-for="variant in group.product.variants" :key="variant.id"><option :value="variant.id" x-text="`${variant.name} · ${variant.available} available`"></option></template></select></div></template>
                                    <template x-if="group.mode === 'wholesale'"><div><label :for="`color-${group.key}`" class="form-label">{{ __('Pack color') }}</label><select :id="`color-${group.key}`" class="form-input" x-model="group.color_id" required><template x-for="color in group.product.colors" :key="color.id"><option :value="color.id" x-text="color.name"></option></template></select></div></template>
                                    <div><label :for="`count-${group.key}`" class="form-label" x-text="group.mode === 'retail' ? 'Pieces' : 'Packs'"></label><input :id="`count-${group.key}`" type="number" min="1" :max="group.mode === 'retail' ? 10000 : 100" step="1" class="form-input" x-model="group.count" required></div>
                                </div>
                                <p x-show="group.mode === 'wholesale'" class="text-xs text-body" x-text="ratio(group)"></p>
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
                        <template x-if="customerType === 'existing'"><div class="space-y-2"><label for="pos-contact" class="form-label">{{ __('Choose customer') }}</label><select id="pos-contact" class="form-input" x-model="contactId" @change="selectContact()" required><option value="">{{ __('Select customer') }}</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->name }} · {{ $contact->phone }}</option>@endforeach</select><template x-for="balance in balances" :key="balance.currency"><p class="text-sm text-body" x-text="`Customer outstanding: ${balance.currency} ${balance.amount}`"></p></template></div></template>
                        <template x-if="customerType === 'new'"><div class="grid gap-4 sm:grid-cols-2"><div><label for="pos-name" class="form-label">{{ __('Name') }}</label><input id="pos-name" class="form-input" x-model="customer.name" maxlength="150" required></div><div><label for="pos-phone" class="form-label">{{ __('Phone') }}</label><input id="pos-phone" class="form-input" x-model="customer.phone" maxlength="30" required></div><div class="sm:col-span-2"><label for="pos-email" class="form-label">{{ __('Email (optional)') }}</label><input id="pos-email" type="email" class="form-input" x-model="customer.email" maxlength="255"></div></div></template>
                        <template x-if="fulfillment === 'delivery'">
                            <div class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @foreach(['name' => 'Recipient name', 'phone' => 'Delivery phone', 'line1' => 'Address', 'line2' => 'Address line 2', 'city' => 'City', 'state' => 'State / region', 'postal_code' => 'Postal code', 'country' => 'Country code'] as $field => $label)
                                        <div><label for="pos-address-{{ $field }}" class="form-label">{{ __($label) }}</label><input id="pos-address-{{ $field }}" class="form-input" x-model="address.{{ $field }}" maxlength="{{ $field === 'country' ? 2 : ($field === 'phone' || $field === 'postal_code' ? 30 : ($field === 'name' ? 150 : ($field === 'city' || $field === 'state' ? 120 : 255))) }}" @if(in_array($field, ['name', 'phone', 'line1', 'city', 'country'])) required @endif @if($field === 'country') placeholder="US" @endif></div>
                                    @endforeach
                                </div>
                                <template x-if="quote && quote.shipping_options.length"><div><label for="pos-shipping" class="form-label">{{ __('Shipping method') }}</label><select id="pos-shipping" class="form-input" x-model="shippingMethod"><option value="">{{ __('Best available shipping') }}</option><template x-for="option in quote.shipping_options" :key="option.id"><option :value="option.id" x-text="`${option.name} · ${option.currency} ${option.price}`"></option></template></select></div></template>
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
    </div>
</x-layouts.user>
