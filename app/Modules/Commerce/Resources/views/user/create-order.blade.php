<x-layouts.user :title="__('Create Order')">
    <div class="space-y-6" x-data="orderBuilder()" data-products="{{ json_encode($products) }}" data-old-groups="{{ json_encode(old('groups', [])) }}" data-has-old="{{ old('_token') ? '1' : '0' }}" data-currency="{{ $settings->currency }}" data-draft-key="order-draft:{{ $workspaceId }}:{{ $completingOrder?->id ?? 'new' }}">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="heading-3">{{ __('Create Order') }}</h1>
                <p class="text-body">{{ $settings->currency }} · {{ ! ($retailEnabled ?? true) ? __('Owner-configured wholesale boxes') : (($wholesaleEnabled ?? true) ? __('Retail pieces and owner-configured wholesale boxes') : __('Retail pieces')) }}</p>
            </div>
            <p class="text-sm text-body" x-show="savedAt" x-cloak x-text="`{{ __('Progress saved') }} ${savedAt}`"></p>
        </header>

        <div x-show="restoredAt" x-cloak class="section-card flex flex-wrap items-center justify-between gap-3 border-l-4 border-primary" data-draft-restored>
            <p class="text-sm font-medium" x-text="`{{ __('Restored your unfinished order from') }} ${restoredAt}`"></p>
            <button type="button" class="btn-sm btn-outline" @click="clearForm()" data-draft-clear>{{ __('Clear form') }}</button>
        </div>

        @if ($errors->any())
            <div class="section-card border-l-4 border-danger" role="alert">
                <p class="font-semibold text-danger">{{ __('Please fix the following:') }}</p>
                <ul class="mt-2 list-disc ps-5 text-sm text-danger">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="manualOrderForm" method="POST" action="{{ $completingOrder ? route('user.commerce.orders.complete', $completingOrder) : route('user.commerce.orders.store') }}" class="grid gap-6 lg:grid-cols-3" @input.debounce.500ms="saveDraft()" @change="saveDraft()" @submit="submitting($event)">
            @csrf
            <input type="hidden" name="submission_reference" value="{{ old('submission_reference', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="space-y-6 lg:col-span-2">
                <section class="section-card space-y-4">
                    <h2 class="heading-5">{{ __('1. Customer') }}</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="contact_id" class="form-label">{{ __('Existing contact (optional)') }}</label>
                            <select id="contact_id" name="contact_id" @change="selectContact($event)" class="form-input">
                                <option value="">{{ __('New customer') }}</option>
                                @foreach ($contacts as $contact)
                                    <option value="{{ $contact->id }}" data-name="{{ $contact->name }}" data-phone="{{ $contact->phone }}" data-email="{{ $contact->email }}" data-country="{{ $contact->country }}" data-city="{{ $contact->city }}" @selected(old('contact_id', $completingOrder?->contact_id) == $contact->id)>{{ $contact->name }} · {{ $contact->phone }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="customer_name" class="form-label">{{ __('Customer name') }}</label><input id="customer_name" name="customer[name]" class="form-input" maxlength="150" value="{{ old('customer.name') }}" @input="mirror('name')"></div>
                        <div><label for="customer_phone" class="form-label">{{ __('Customer phone') }}</label><input id="customer_phone" name="customer[phone]" class="form-input" maxlength="30" value="{{ old('customer.phone') }}" @input="mirror('phone')"></div>
                        <div class="md:col-span-2"><label for="customer_email" class="form-label">{{ __('Customer email') }}</label><input id="customer_email" name="customer[email]" type="email" class="form-input" value="{{ old('customer.email') }}"></div>
                    </div>
                </section>

                <section class="section-card space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="heading-5">{{ __('2. Delivery address') }}</h2>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="sameAsCustomer" @change="mirror('name'); mirror('phone')"> {{ __('Recipient same as customer') }}</label>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach (['name' => 'Recipient name', 'phone' => 'Delivery phone', 'line1' => 'Address', 'line2' => 'Address line 2', 'city' => 'City', 'state' => 'State / region', 'postal_code' => 'Postal code', 'country' => 'Country code'] as $field => $label)
                            <div><label for="shipping_{{ $field }}" class="form-label">{{ __($label) }}</label><input id="shipping_{{ $field }}" name="shipping_address[{{ $field }}]" class="form-input @if ($field === 'country') uppercase @endif" value="{{ old('shipping_address.'.$field, $completingOrder?->shipping_address[$field] ?? '') }}" @if (in_array($field, ['name', 'phone', 'line1', 'city', 'country'])) required @endif @if ($field === 'country') maxlength="2" placeholder="US" @input="$el.value = $el.value.toUpperCase()" @endif></div>
                        @endforeach
                    </div>
                </section>

                <section class="section-card space-y-4">
                    <h2 class="heading-5">{{ __('3. Products') }}</h2>
                    <template x-for="(group, index) in groups" :key="group.key">
                        <div class="space-y-3 rounded-md border border-border p-4">
                            <div class="grid gap-3 md:grid-cols-4">
                                <div class="md:col-span-2">
                                    <label class="form-label">{{ __('Product') }}</label>
                                    <select class="form-input" :name="`groups[${index}][product_id]`" x-model="group.product_id" @change="reset(group)" required>
                                        <option value="">{{ __('Select product') }}</option>
                                        <template x-for="product in products" :key="product.id"><option :value="product.id" x-text="product.price ? `${product.name} — from ${currency} ${product.price}` : product.name" :selected="Number(product.id) === Number(group.product_id)"></option></template>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">{{ __('Purchase type') }}</label>
                                    <select class="form-input" :name="`groups[${index}][mode]`" x-model="group.mode" @change="schedulePreview()">
                                        @if ($retailEnabled ?? true)<option value="retail">{{ __('Retail pieces') }}</option>@endif
                                        @if ($wholesaleEnabled ?? true)<option value="wholesale">{{ __('Wholesale boxes') }}</option>@endif
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" x-text="group.mode === 'retail' ? '{{ __('Pieces') }}' : '{{ __('Boxes') }}'"></label>
                                    <div class="flex items-stretch">
                                        <button type="button" class="btn btn-outline px-3" @click="step(group, -1)" aria-label="{{ __('Decrease') }}">−</button>
                                        <input class="form-input mx-1 text-center" type="number" min="1" :max="group.mode === 'retail' ? 10000 : 100" :name="`groups[${index}][${group.mode === 'retail' ? 'quantity' : 'box_count'}]`" x-model.number="group.count" @input.debounce.400ms="schedulePreview()" required>
                                        <button type="button" class="btn btn-outline px-3" @click="step(group, 1)" aria-label="{{ __('Increase') }}">+</button>
                                    </div>
                                </div>
                                <template x-if="group.mode === 'retail'">
                                    <div class="space-y-3 md:col-span-4" x-init="syncRetailColor(group)">
                                        <input type="hidden" :name="`groups[${index}][variant_id]`" :value="group.variant_id">
                                        <div x-show="(product(group).colors || []).length">
                                            <p class="form-label">{{ __('Colour') }}</p>
                                            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Colour') }}">
                                                <template x-for="color in product(group).colors || []" :key="color.id">
                                                    <button type="button" class="order-chip" :class="{ 'order-chip-active': Number(color.id) === Number(group.color_id), 'order-chip-disabled': color.stock < 1 }" :disabled="color.stock < 1" :title="color.stock < 1 ? '{{ __('Sold out') }}' : color.name" :aria-pressed="Number(color.id) === Number(group.color_id)" @click="pickColor(group, color)">
                                                        <span class="order-chip-swatch" :style="swatchStyle(color)"></span>
                                                        <span x-text="color.name"></span>
                                                        <i class="ph-bold ph-check text-xs text-primary" x-show="Number(color.id) === Number(group.color_id)"></i>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                        <div>
                                            <p class="form-label">{{ __('Size') }}</p>
                                            <p class="text-xs text-body" x-show="(product(group).colors || []).length && ! group.color_id">{{ __('Choose a colour first') }}</p>
                                            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Size') }}" x-show="! (product(group).colors || []).length || group.color_id">
                                                <template x-for="variant in retailSizes(group)" :key="variant.id">
                                                    <button type="button" class="order-size-chip" :class="{ 'order-chip-active': Number(variant.id) === Number(group.variant_id), 'order-chip-disabled': variant.stock < 1 }" :disabled="variant.stock < 1" :title="variant.stock < 1 ? '{{ __('Sold out') }}' : variant.size" :aria-pressed="Number(variant.id) === Number(group.variant_id)" @click="pickSize(group, variant)" x-text="variant.size"></button>
                                                </template>
                                            </div>
                                        </div>
                                        <p class="text-xs text-error" x-show="showMissing && ! group.variant_id" x-cloak>{{ __('Choose a colour and size.') }}</p>
                                    </div>
                                </template>
                                <template x-if="group.mode === 'wholesale'">
                                    <div class="space-y-2 md:col-span-4">
                                        <input type="hidden" :name="`groups[${index}][color_id]`" :value="group.color_id">
                                        <p class="form-label">{{ __('Colour') }}</p>
                                        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Colour') }}">
                                            <template x-for="color in product(group).colors || []" :key="color.id">
                                                <button type="button" class="order-chip" :class="{ 'order-chip-active': Number(color.id) === Number(group.color_id), 'order-chip-disabled': color.stock < 1 }" :disabled="color.stock < 1" :title="color.stock < 1 ? '{{ __('Sold out') }}' : color.name" :aria-pressed="Number(color.id) === Number(group.color_id)" @click="group.color_id = String(color.id); afterRowChange()">
                                                    <span class="order-chip-swatch" :style="swatchStyle(color)"></span>
                                                    <span x-text="color.name"></span>
                                                    <i class="ph-bold ph-check text-xs text-primary" x-show="Number(color.id) === Number(group.color_id)"></i>
                                                </button>
                                            </template>
                                        </div>
                                        <p class="text-xs text-error" x-show="showMissing && ! group.color_id" x-cloak>{{ __('Choose a colour.') }}</p>
                                    </div>
                                </template>
                            </div>
                            <p class="text-sm text-body" x-show="group.mode === 'wholesale' && group.color_id" x-text="ratio(group)"></p>
                            <div class="flex gap-4 text-sm">
                                <button type="button" class="text-primary" @click="duplicate(group)">{{ __('Duplicate') }}</button>
                                <button type="button" class="text-danger" x-show="groups.length > 1" @click="remove(group)">{{ __('Remove') }}</button>
                            </div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-outline" @click="add()">+ {{ __('Add product') }}</button>
                </section>
            </div>

            <aside class="lg:col-span-1">
                <section class="section-card space-y-3 lg:sticky lg:top-4">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5">{{ __('4. Summary') }}</h2>
                        <button type="button" class="text-sm text-primary" @click="preview()" :disabled="busy" x-text="busy ? '{{ __('Updating…') }}' : '{{ __('Refresh') }}'"></button>
                    </div>
                    <p class="text-danger" role="alert" x-show="error" x-text="error"></p>
                    <p class="text-sm text-body" x-show="!quote && !error">{{ __('Pick products to see the total.') }}</p>
                    <template x-if="quote">
                        <div class="space-y-3">
                            <div class="flex justify-between"><span>{{ __('Merchandise') }}</span><span class="font-semibold" x-text="`${quote.currency} ${quote.subtotal}`"></span></div>
                            <div>
                                <label class="form-label">{{ __('Shipping') }}</label>
                                <select class="form-input" name="shipping_method_id" x-model="shippingMethodId">
                                    <option value="">{{ __('Best available shipping / request quote') }}</option>
                                    <template x-for="option in quote.shipping_options" :key="option.id"><option :value="option.id" :selected="String(option.id) === String(shippingMethodId)" x-text="`${option.name}: ${option.currency} ${option.price}`"></option></template>
                                </select>
                            </div>
                            <div class="flex justify-between border-t border-border pt-3 text-lg font-bold">
                                <span>{{ __('Total') }}</span>
                                <span x-text="quote.shipping_quote_required ? '{{ __('Shipping quote required') }}' : `${quote.currency} ${quote.total}`"></span>
                            </div>
                            <ul class="space-y-1 text-sm">
                                <template x-for="stock in quote.availability" :key="stock.variant_id">
                                    <li :class="stock.required > stock.available ? 'font-semibold text-danger' : 'text-body'" x-text="`${stock.sku}: ${stock.required} {{ __('needed') }} / ${stock.available} {{ __('available') }}`"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                    <div class="flex flex-col gap-2 pt-2">
                        <button type="submit" name="draft" value="0" class="btn btn-primary" :disabled="!hasProduct()">{{ __('Create order and reserve stock') }}</button>
                        @unless ($completingOrder)
                            <button type="submit" name="draft" value="1" class="btn btn-outline" :disabled="!hasProduct()">{{ __('Save draft') }}</button>
                        @endunless
                    </div>
                </section>
            </aside>
        </form>
    </div>
    @push('scripts')
    <script>
        function orderBuilder() {
            'use strict';
            const draftMaxAgeMs = 7 * 24 * 60 * 60 * 1000;
            return {
                products: [], groups: [], quote: null, error: '', busy: false, currency: '', showMissing: false,
                sameAsCustomer: true, shippingMethodId: '', draftKey: '', restoredAt: '', restoring: false, savedAt: '', previewTimer: null,
                init() {
                    this.draftKey = this.$el.dataset.draftKey;
                    this.currency = this.$el.dataset.currency;
                    this.products = JSON.parse(this.$el.dataset.products);
                    this.groups = JSON.parse(this.$el.dataset.oldGroups).map(group => ({ ...group, count: Number(group.quantity || group.box_count || 1), key: crypto.randomUUID() }));
                    if (!this.groups.length) { this.add(); }
                    const draft = this.readDraft();
                    if (draft && this.$el.dataset.hasOld !== '1') { this.restoreDraft(draft); } else if (this.hasProduct()) { this.schedulePreview(); }
                    window.addEventListener('pagehide', () => { 'use strict'; this.saveDraft(); });
                    document.addEventListener('visibilitychange', () => { 'use strict'; if (document.visibilityState === 'hidden') { this.saveDraft(); } });
                    setTimeout(() => { 'use strict'; this.saveDraft(); }, 1500);
                },
                form() { return document.getElementById('manualOrderForm'); },
                field(id) { return document.getElementById(id); },
                selectContact(event) {
                    const contact = event.target.selectedOptions[0];
                    const fill = (id, value) => { const input = this.field(id); if (input && !input.value && value) { input.value = value; } };
                    fill('customer_name', contact.dataset.name);
                    fill('customer_phone', contact.dataset.phone);
                    fill('customer_email', contact.dataset.email);
                    fill('shipping_name', contact.dataset.name);
                    fill('shipping_phone', contact.dataset.phone);
                    fill('shipping_city', contact.dataset.city);
                    fill('shipping_country', (contact.dataset.country || '').toUpperCase().slice(0, 2));
                    this.saveDraft();
                },
                mirror(field) { if (this.sameAsCustomer) { this.field(`shipping_${field}`).value = this.field(`customer_${field}`).value; } },
                add() { this.groups.push({ key: crypto.randomUUID(), product_id: '', mode: '{{ ($retailEnabled ?? true) ? 'retail' : 'wholesale' }}', variant_id: '', color_id: '', count: 1 }); },
                duplicate(group) { const index = this.groups.indexOf(group); this.groups.splice(index + 1, 0, { ...group, key: crypto.randomUUID() }); this.afterRowChange(); },
                remove(group) { this.groups = this.groups.filter(entry => entry.key !== group.key); this.afterRowChange(); },
                step(group, delta) { const max = group.mode === 'retail' ? 10000 : 100; group.count = Math.min(max, Math.max(1, Number(group.count || 1) + delta)); this.afterRowChange(); },
                afterRowChange() { this.$nextTick(() => { this.saveDraft(); this.schedulePreview(); }); },
                product(group) { return this.products.find(product => Number(product.id) === Number(group.product_id)) || {}; },
                reset(group) { group.variant_id = ''; group.color_id = ''; group.mode = this.product(group).mode === 'wholesale' ? 'wholesale' : 'retail'; this.quote = null; },
                retailSizes(group) {
                    const variants = this.product(group).variants || [];
                    const hasColors = (this.product(group).colors || []).length > 0;

                    return hasColors ? variants.filter(variant => Number(variant.color_id) === Number(group.color_id)) : variants;
                },
                colorHex(color) { return /^#[\da-f]{3,8}$/i.test(color?.hex_code || '') ? color.hex_code : '#e5e7eb'; },
                swatchStyle(color) { return { backgroundColor: color.swatch_image_url ? 'transparent' : this.colorHex(color), backgroundImage: color.swatch_image_url ? `url('${color.swatch_image_url}')` : 'none' }; },
                pickColor(group, color) { if (Number(group.color_id) !== Number(color.id)) { group.color_id = String(color.id); group.variant_id = ''; } this.afterRowChange(); },
                pickSize(group, variant) { group.variant_id = String(variant.id); this.afterRowChange(); },
                syncRetailColor(group) {
                    if (group.color_id || !group.variant_id) {
                        return;
                    }

                    const variant = (this.product(group).variants || []).find(item => Number(item.id) === Number(group.variant_id));
                    group.color_id = variant?.color_id ? String(variant.color_id) : '';
                },
                hasProduct() { return this.groups.some(group => group.product_id); },
                isComplete(group) { return group.product_id && (group.mode === 'retail' ? group.variant_id : group.color_id) && Number(group.count) > 0; },
                ratio(group) {
                    const product = this.product(group);
                    const ratio = product.ratios?.[group.color_id] || {};
                    const multiplier = product.multiplier || 1;
                    const perBox = Object.values(ratio).reduce((sum, qty) => sum + Number(qty) * multiplier, 0);
                    const sizes = Object.entries(ratio).map(([size, qty]) => `${size}: ${qty * multiplier}`).join(', ');
                    return `${sizes} per box (${perBox} pcs) · ${perBox * Number(group.count || 0)} pcs total · Product minimum: ${product.minimum || 1} pieces`;
                },
                schedulePreview() {
                    clearTimeout(this.previewTimer);
                    this.previewTimer = setTimeout(() => { if (this.groups.length && this.groups.every(group => this.isComplete(group))) { this.preview(); } }, 600);
                },
                async preview() {
                    this.busy = true; this.error = '';
                    try {
                        const response = await fetch('{{ route('user.commerce.orders.preview') }}', { method: 'POST', body: new FormData(this.form()), headers: { Accept: 'application/json' } });
                        const result = await response.json();
                        if (!response.ok) { throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message); }
                        this.quote = result.data;
                    } catch (error) { this.error = error.message; } finally { this.busy = false; }
                },
                readDraft() {
                    try {
                        const draft = JSON.parse(localStorage.getItem(this.draftKey) || 'null');
                        if (!draft || Date.now() - new Date(draft.saved_at).getTime() > draftMaxAgeMs) { localStorage.removeItem(this.draftKey); return null; }
                        return draft;
                    } catch (error) { return null; }
                },
                saveDraft() {
                    if (this.restoring) { return; }
                    const fields = {};
                    this.form().querySelectorAll('input[id], select[id], textarea[id]').forEach(input => { if (input.type !== 'checkbox') { fields[input.id] = input.value; } });
                    const hasContent = this.hasProduct() || Object.values(fields).some(value => value);
                    if (!hasContent) { return; }
                    const groups = this.groups.map(({ key, ...group }) => group);
                    localStorage.setItem(this.draftKey, JSON.stringify({ saved_at: new Date().toISOString(), fields, groups, same: this.sameAsCustomer, shipping: this.shippingMethodId }));
                    this.savedAt = new Date().toLocaleTimeString();
                },
                restoreDraft(draft) {
                    this.restoring = true;
                    this.restoredAt = new Date(draft.saved_at).toLocaleString();
                    this.sameAsCustomer = draft.same ?? true;
                    this.shippingMethodId = draft.shipping || '';
                    this.groups = (draft.groups || []).map(group => ({ ...group, count: Number(group.count || 1), key: crypto.randomUUID() }));
                    if (!this.groups.length) { this.add(); }
                    this.$nextTick(() => {
                        requestAnimationFrame(() => {
                            'use strict';
                            Object.entries(draft.fields || {}).forEach(([id, value]) => { const input = this.field(id); if (input && !(input.name || '').startsWith('groups')) { input.value = value; } });
                            this.restoring = false;
                            this.schedulePreview();
                        });
                    });
                },
                discardDraft() { localStorage.removeItem(this.draftKey); this.restoredAt = ''; this.savedAt = ''; },
                clearForm() {
                    this.restoring = true;
                    this.discardDraft();
                    this.form().querySelectorAll('input[id], select[id], textarea[id]').forEach(input => { if (input.type !== 'checkbox' && input.type !== 'hidden') { input.value = ''; } });
                    this.sameAsCustomer = true;
                    this.shippingMethodId = '';
                    this.quote = null;
                    this.groups = [];
                    this.add();
                    this.$nextTick(() => { this.restoring = false; });
                },
                submitting(event) {
                    const finalOrder = event?.submitter?.value === '0';
                    if (finalOrder && this.groups.some(group => group.product_id && ! this.isComplete(group))) {
                        event.preventDefault();
                        this.showMissing = true;
                        return;
                    }
                    localStorage.removeItem(this.draftKey);
                },
            };
        }
    </script>
    @endpush
</x-layouts.user>
