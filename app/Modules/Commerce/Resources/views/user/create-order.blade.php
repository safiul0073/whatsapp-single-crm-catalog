<x-layouts.user :title="__('Create Order')">
    <div class="space-y-6" x-data="orderBuilder()" data-products="{{ json_encode($products) }}" data-old-groups="{{ json_encode(old('groups', [])) }}">
        <header><h1 class="heading-3">{{ __('Create Order') }}</h1><p class="text-body">{{ $settings->currency }} · {{ __('Retail pieces and owner-configured wholesale boxes') }}</p></header>
        <form id="manualOrderForm" method="POST" action="{{ $completingOrder ? route('user.commerce.orders.complete', $completingOrder) : route('user.commerce.orders.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="submission_reference" value="{{ old('submission_reference', (string) \Illuminate\Support\Str::uuid()) }}">
            <section class="section-card grid gap-4 md:grid-cols-2">
                <div><label for="contact_id" class="form-label">{{ __('Existing contact (optional)') }}</label><select id="contact_id" name="contact_id" @change="selectContact($event)" class="form-input"><option value="">{{ __('New customer') }}</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}" data-name="{{ $contact->name }}" data-phone="{{ $contact->phone }}" data-country="{{ $contact->country }}" @selected(old('contact_id', $completingOrder?->contact_id) == $contact->id)>{{ $contact->name }} · {{ $contact->phone }}</option>@endforeach</select></div>
                <div><label for="customer_name" class="form-label">{{ __('Customer name') }}</label><input id="customer_name" name="customer[name]" class="form-input" maxlength="150" value="{{ old('customer.name') }}"></div>
                <div><label for="customer_phone" class="form-label">{{ __('Customer phone') }}</label><input id="customer_phone" name="customer[phone]" class="form-input" maxlength="30" value="{{ old('customer.phone') }}"></div>
                <div><label for="customer_email" class="form-label">{{ __('Customer email') }}</label><input id="customer_email" name="customer[email]" type="email" class="form-input" value="{{ old('customer.email') }}"></div>
                @foreach(['name'=>'Recipient name','phone'=>'Delivery phone','line1'=>'Address','line2'=>'Address line 2','city'=>'City','state'=>'State / region','postal_code'=>'Postal code','country'=>'Country code'] as $field=>$label)
                    <div><label for="shipping_{{ $field }}" class="form-label">{{ __($label) }}</label><input id="shipping_{{ $field }}" name="shipping_address[{{ $field }}]" class="form-input" value="{{ old('shipping_address.'.$field, $completingOrder?->shipping_address[$field] ?? '') }}" @if(in_array($field,['name','phone','line1','city','country'])) required @endif @if($field === 'country') maxlength="2" placeholder="US" @endif></div>
                @endforeach
            </section>
            <section class="section-card space-y-4">
                <h2 class="heading-5">{{ __('Products and boxes') }}</h2>
                <template x-for="(group, index) in groups" :key="group.key">
                    <div class="rounded-md border border-border p-4 space-y-3">
                        <div class="grid gap-3 md:grid-cols-4">
                            <div><label class="form-label">{{ __('Product') }}</label><select class="form-input" :name="`groups[${index}][product_id]`" x-model="group.product_id" @change="reset(group)" required><option value="">{{ __('Select product') }}</option><template x-for="product in products" :key="product.id"><option :value="product.id" x-text="product.name"></option></template></select></div>
                            <div><label class="form-label">{{ __('Purchase type') }}</label><select class="form-input" :name="`groups[${index}][mode]`" x-model="group.mode"><option value="retail">{{ __('Retail pieces') }}</option><option value="wholesale">{{ __('Wholesale boxes') }}</option></select></div>
                            <template x-if="group.mode === 'retail'"><div><label class="form-label">{{ __('Variant') }}</label><select class="form-input" :name="`groups[${index}][variant_id]`" x-model="group.variant_id" required><option value="">{{ __('Select variant') }}</option><template x-for="variant in product(group).variants || []" :key="variant.id"><option :value="variant.id" x-text="`${variant.name} (${variant.stock} in stock)`"></option></template></select></div></template>
                            <template x-if="group.mode === 'wholesale'"><div><label class="form-label">{{ __('Color') }}</label><select class="form-input" :name="`groups[${index}][color_id]`" x-model="group.color_id" required><option value="">{{ __('Select color') }}</option><template x-for="color in product(group).colors || []" :key="color.id"><option :value="color.id" x-text="color.name"></option></template></select></div></template>
                            <div><label class="form-label" x-text="group.mode === 'retail' ? 'Pieces' : 'Boxes'"></label><input class="form-input" type="number" min="1" :max="group.mode === 'retail' ? 10000 : 100" :name="`groups[${index}][${group.mode === 'retail' ? 'quantity' : 'box_count'}]`" x-model="group.count" required></div>
                        </div>
                        <p class="text-sm text-body" x-show="group.mode === 'wholesale'" x-text="ratio(group)"></p>
                        <button type="button" class="text-danger" @click="groups = groups.filter(entry => entry.key !== group.key)">{{ __('Remove') }}</button>
                    </div>
                </template>
                <button type="button" class="btn btn-outline" @click="add()">{{ __('Add product') }}</button>
            </section>
            <section class="section-card space-y-3">
                <p class="text-danger" role="alert" x-text="error"></p>
                <button type="button" class="btn btn-outline" @click="preview()" :disabled="busy">{{ __('Preview prices and shipping') }}</button>
                <template x-if="quote"><div class="space-y-3"><p x-text="`Merchandise: ${quote.currency} ${quote.subtotal}`"></p><select class="form-input" name="shipping_method_id"><option value="">{{ __('Best available shipping / request quote') }}</option><template x-for="option in quote.shipping_options" :key="option.id"><option :value="option.id" x-text="`${option.name}: ${option.currency} ${option.price}`"></option></template></select><p x-text="quote.shipping_quote_required ? 'Shipping quote required' : `Total: ${quote.currency} ${quote.total}`"></p></div></template>
                <template x-if="quote"><ul><template x-for="stock in quote.availability" :key="stock.variant_id"><li x-text="`${stock.sku}: ${stock.required} required / ${stock.available} available`"></li></template></ul></template>
                <div class="flex flex-wrap gap-3"><button type="submit" name="draft" value="0" class="btn btn-primary">{{ __('Create order and reserve stock') }}</button>@unless($completingOrder)<button type="submit" name="draft" value="1" class="btn btn-outline">{{ __('Save draft') }}</button>@endunless</div>
            </section>
        </form>
    </div>
    @push('scripts')
    <script>
        function orderBuilder() {
            'use strict';
            return {
                products: [], groups: [], quote: null, error: '', busy: false,
                init() { this.products = JSON.parse(this.$el.dataset.products); this.groups = JSON.parse(this.$el.dataset.oldGroups).map(group => ({ ...group, count: group.quantity || group.box_count || 1, key: crypto.randomUUID() })); if (!this.groups.length) this.add(); },
                selectContact(event) { const contact = event.target.selectedOptions[0]; for (const field of ['name','phone','country']) { const input = document.getElementById(`shipping_${field}`); if (!input.value) input.value = contact.dataset[field] || ''; } },
                add() { this.groups.push({ key: crypto.randomUUID(), product_id: '', mode: 'retail', variant_id: '', color_id: '', count: 1 }); },
                product(group) { return this.products.find(product => Number(product.id) === Number(group.product_id)) || {}; },
                reset(group) { group.variant_id = ''; group.color_id = ''; group.mode = this.product(group).mode === 'wholesale' ? 'wholesale' : 'retail'; },
                ratio(group) { const product = this.product(group); const ratio = product.ratios?.[group.color_id] || {}; return Object.entries(ratio).map(([size, qty]) => `${size}: ${qty * (product.multiplier || 1)}`).join(', ') + ` per box · Product minimum: ${product.minimum || 1} pieces`; },
                async preview() { this.busy = true; this.error = ''; try { const response = await fetch('{{ route('user.commerce.orders.preview') }}', { method: 'POST', body: new FormData(document.getElementById('manualOrderForm')), headers: { Accept: 'application/json' } }); const result = await response.json(); if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message); this.quote = result.data; } catch(error) { this.error = error.message; } finally { this.busy = false; } }
            };
        }
    </script>
    @endpush
</x-layouts.user>
