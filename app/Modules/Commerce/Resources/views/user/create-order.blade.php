@php
    $editingOrder ??= null;
    $sourceOrder = $editingOrder ?? $completingOrder;
    $editCustomer = $editingOrder ? ($editingOrder->customer_snapshot ?? []) : [];
    $draftKey = $editingOrder ? 'order-edit:'.$workspaceId.':'.$editingOrder->id.':'.$editingOrder->updated_at?->timestamp : 'order-draft:'.$workspaceId.':'.($completingOrder?->id ?? 'new');
@endphp
<x-layouts.user :title="$editingOrder ? __('Edit order :number', ['number' => $editingOrder->number]) : __('Create Order')">
    <div class="space-y-6" x-data="orderBuilder()" data-products="{{ json_encode($products) }}" data-old-groups="{{ json_encode(old('groups', $orderGroups ?? [])) }}" data-has-old="{{ old('_token') ? '1' : '0' }}" data-currency="{{ $settings->currency }}" data-draft-key="{{ $draftKey }}" data-shipping-method="{{ old('shipping_method_id', $editingOrder?->shipping_method_id) }}" data-same-as-customer="{{ $editingOrder ? '0' : '1' }}">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                @if ($editingOrder)
                    <a href="{{ route('user.commerce.orders.show', $editingOrder) }}" class="inline-flex items-center gap-1 text-sm text-body hover:text-title"><i class="ph ph-arrow-left"></i> {{ $editingOrder->number }}</a>
                @endif
                <h1 class="heading-3">{{ $editingOrder ? __('Edit order :number', ['number' => $editingOrder->number]) : __('Create Order') }}</h1>
                <p class="text-body">{{ $settings->currency }} · {{ ! ($retailEnabled ?? true) ? __('Owner-configured wholesale boxes') : (($wholesaleEnabled ?? true) ? __('Retail pieces and owner-configured wholesale boxes') : __('Retail pieces')) }}</p>
            </div>
            <p class="text-sm text-body" x-show="savedAt" x-cloak x-text="`{{ __('Progress saved') }} ${savedAt}`"></p>
        </header>

        <div x-show="restoredAt" x-cloak class="section-card flex flex-wrap items-center justify-between gap-3 border-l-4 border-primary" data-draft-restored>
            <p class="text-sm font-medium" x-text="`{{ __('Restored your unfinished order from') }} ${restoredAt}`"></p>
            <button type="button" class="btn-sm btn-outline" @click="clearForm()" data-draft-clear>{{ __('Clear form') }}</button>
        </div>

        @if ($editingOrder)
            <div class="order-edit-notice" data-order-edit-notice>
                <i class="ph ph-info shrink-0 text-base text-primary" aria-hidden="true"></i>
                <p>{{ __('Change the products in this order') }} · {{ __('Customer, delivery address and shipping method stay as they are.') }} {{ __('Prices are recalculated with today\'s prices and the stock held for this order follows the new quantities.') }}@if ($editingOrder->boxes()->exists()) {{ __('Packing progress is reset and the boxes are rebuilt.') }}@endif
                    @if ($editingOrder->payment_state === 'paid' || $editingOrder->inventory_adjusted_at)<strong class="text-title">{{ __('This order is already paid. If the total changes, collect or refund the difference with the customer.') }}</strong>@endif
                    @if (! empty($unavailableLines))<strong class="text-danger" data-unavailable-lines>{{ __('No longer available and will be removed:') }} {{ implode(', ', $unavailableLines) }}</strong>@endif
                </p>
            </div>
        @endif

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

        <form id="manualOrderForm" method="POST" action="{{ $editingOrder ? route('user.commerce.orders.update', $editingOrder) : ($completingOrder ? route('user.commerce.orders.complete', $completingOrder) : route('user.commerce.orders.store')) }}" class="grid gap-6 lg:grid-cols-3" @input.debounce.500ms="saveDraft()" @change="saveDraft()" @submit="submitting($event)">
            @csrf
            @if ($editingOrder)
                @method('PUT')
            @endif
            <input type="hidden" name="submission_reference" value="{{ old('submission_reference', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="space-y-6 lg:col-span-2">
                @if ($editingOrder)
                    @if ($editingOrder->contact_id)<input type="hidden" name="contact_id" value="{{ $editingOrder->contact_id }}">@endif
                    @foreach (['name', 'phone', 'email'] as $field)
                        <input type="hidden" name="customer[{{ $field }}]" value="{{ $editCustomer[$field] ?? '' }}">
                    @endforeach
                    @foreach (['name', 'phone', 'line1', 'line2', 'city', 'state', 'postal_code', 'country'] as $field)
                        <input type="hidden" name="shipping_address[{{ $field }}]" value="{{ $editingOrder->shipping_address[$field] ?? '' }}">
                    @endforeach
                    <input type="hidden" name="shipping_method_id" value="{{ $editingOrder->shipping_method_id }}">
                @else
                <section class="section-card space-y-4">
                    <h2 class="heading-5">{{ __('1. Customer') }}</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="contact_id" class="form-label">{{ __('Existing contact (optional)') }}</label>
                            <select id="contact_id" name="contact_id" @change="selectContact($event)" class="form-input">
                                <option value="">{{ __('New customer') }}</option>
                                @foreach ($contacts as $contact)
                                    <option value="{{ $contact->id }}" data-name="{{ $contact->name }}" data-phone="{{ $contact->phone }}" data-email="{{ $contact->email }}" data-country="{{ $contact->country }}" data-city="{{ $contact->city }}" @selected(old('contact_id', $sourceOrder?->contact_id) == $contact->id)>{{ $contact->name }} · {{ $contact->phone }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="customer_name" class="form-label">{{ __('Customer name') }}</label><input id="customer_name" name="customer[name]" class="form-input" maxlength="150" value="{{ old('customer.name', $editCustomer['name'] ?? '') }}" @input="mirror('name')"></div>
                        <div><label for="customer_phone" class="form-label">{{ __('Customer phone') }}</label><input id="customer_phone" name="customer[phone]" class="form-input" maxlength="30" value="{{ old('customer.phone', $editCustomer['phone'] ?? '') }}" @input="mirror('phone')"></div>
                        <div class="md:col-span-2"><label for="customer_email" class="form-label">{{ __('Customer email') }}</label><input id="customer_email" name="customer[email]" type="email" class="form-input" value="{{ old('customer.email', $editCustomer['email'] ?? '') }}"></div>
                    </div>
                </section>

                <section class="section-card space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="heading-5">{{ __('2. Delivery address') }}</h2>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="sameAsCustomer" @change="mirror('name'); mirror('phone')"> {{ __('Recipient same as customer') }}</label>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach (['name' => 'Recipient name', 'phone' => 'Delivery phone', 'line1' => 'Address', 'line2' => 'Address line 2', 'city' => 'City', 'state' => 'State / region', 'postal_code' => 'Postal code', 'country' => 'Country code'] as $field => $label)
                            <div><label for="shipping_{{ $field }}" class="form-label">{{ __($label) }}</label><input id="shipping_{{ $field }}" name="shipping_address[{{ $field }}]" class="form-input @if ($field === 'country') uppercase @endif" value="{{ old('shipping_address.'.$field, $sourceOrder?->shipping_address[$field] ?? '') }}" @if (in_array($field, ['name', 'phone', 'line1', 'city', 'country'])) required @endif @if ($field === 'country') maxlength="2" placeholder="US" @input="$el.value = $el.value.toUpperCase()" @endif></div>
                        @endforeach
                    </div>
                </section>
                @endif

                @if ($editingOrder)
                <section class="section-card space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="heading-5">{{ __('Products') }}</h2>
                        <p class="text-xs text-body" x-text="`${groups.length} {{ __('lines') }} · ${totalPieces()} {{ __('pcs') }}`"></p>
                    </div>
                    <div class="order-edit-list">
                        <template x-for="(group, index) in groups" :key="group.key">
                            <div class="order-edit-line">
                                <template x-if="group.locked">
                                    <div class="order-edit-top" data-locked-group>
                                        <input type="hidden" :name="`groups[${index}][keep_group_id]`" :value="group.keep_group_id">
                                        <input type="hidden" :name="`groups[${index}][product_id]`" :value="group.product_id">
                                        <input type="hidden" :name="`groups[${index}][mode]`" value="wholesale">
                                        <input type="hidden" :name="`groups[${index}][color_id]`" :value="group.color_id">
                                        <input type="hidden" :name="`groups[${index}][box_count]`" :value="group.box_count">
                                        <i class="ph ph-lock-simple text-body" aria-hidden="true"></i>
                                        <p class="min-w-0 flex-1 truncate text-sm font-medium text-title" x-text="group.label"></p>
                                        <span class="order-edit-meta">{{ __('Customer-built pack · kept as ordered') }}</span>
                                        <button type="button" class="order-edit-icon-btn text-danger" @click="remove(group)" x-show="groups.length > 1" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><i class="ph ph-trash"></i></button>
                                    </div>
                                </template>
                                <template x-if="! group.locked">
                                    <div class="space-y-2">
                                        <div class="order-edit-top">
                                            <select class="order-edit-select min-w-0 flex-1" :name="`groups[${index}][product_id]`" x-model="group.product_id" @change="reset(group); afterRowChange()" required aria-label="{{ __('Product') }}">
                                                <option value="">{{ __('Select product') }}</option>
                                                <template x-for="product in products" :key="product.id"><option :value="product.id" x-text="product.name" :selected="Number(product.id) === Number(group.product_id)"></option></template>
                                            </select>
                                            <input type="hidden" :name="`groups[${index}][mode]`" :value="group.mode">
                                            @if (($retailEnabled ?? true) && ($wholesaleEnabled ?? true))
                                                <div class="order-edit-toggle" role="group" aria-label="{{ __('Purchase type') }}">
                                                    <button type="button" :class="{ 'order-edit-toggle-active': group.mode === 'retail' }" :aria-pressed="group.mode === 'retail'" @click="setMode(group, 'retail')">{{ __('Retail') }}</button>
                                                    <button type="button" :class="{ 'order-edit-toggle-active': group.mode === 'wholesale' }" :aria-pressed="group.mode === 'wholesale'" @click="setMode(group, 'wholesale')">{{ __('Boxes') }}</button>
                                                </div>
                                            @endif
                                            <div class="order-edit-stepper">
                                                <button type="button" @click="step(group, -1)" aria-label="{{ __('Decrease') }}"><i class="ph ph-minus"></i></button>
                                                <input type="number" min="1" :max="group.mode === 'retail' ? 10000 : 100" :name="`groups[${index}][${group.mode === 'retail' ? 'quantity' : 'box_count'}]`" x-model.number="group.count" @input.debounce.400ms="schedulePreview()" required :aria-label="group.mode === 'retail' ? '{{ __('Pieces') }}' : '{{ __('Boxes') }}'">
                                                <button type="button" @click="step(group, 1)" aria-label="{{ __('Increase') }}"><i class="ph ph-plus"></i></button>
                                            </div>
                                            <span class="order-edit-meta w-10" x-text="group.mode === 'retail' ? '{{ __('pcs') }}' : '{{ __('boxes') }}'"></span>
                                            <button type="button" class="order-edit-icon-btn" @click="duplicate(group)" aria-label="{{ __('Duplicate') }}" title="{{ __('Duplicate') }}"><i class="ph ph-copy"></i></button>
                                            <button type="button" class="order-edit-icon-btn text-danger" x-show="groups.length > 1" @click="remove(group)" aria-label="{{ __('Remove') }}" title="{{ __('Remove') }}"><i class="ph ph-trash"></i></button>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                            <template x-if="group.mode === 'retail'"><input type="hidden" :name="`groups[${index}][variant_id]`" :value="group.variant_id"></template>
                                            <template x-if="group.mode === 'wholesale'"><input type="hidden" :name="`groups[${index}][color_id]`" :value="group.color_id"></template>
                                            <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="{{ __('Colour') }}" x-show="(product(group).colors || []).length">
                                                <template x-for="color in product(group).colors || []" :key="color.id">
                                                    <button type="button" class="order-edit-swatch" :class="{ 'order-edit-swatch-active': Number(color.id) === Number(group.color_id), 'order-chip-disabled': color.stock < 1 }" :style="swatchStyle(color)" :disabled="color.stock < 1" :title="color.stock < 1 ? `${color.name} · {{ __('Sold out') }}` : color.name" :aria-label="color.name" :aria-pressed="Number(color.id) === Number(group.color_id)" @click="pickColor(group, color)"></button>
                                                </template>
                                                <span class="text-xs font-semibold text-title" x-text="colorName(group)"></span>
                                            </div>
                                            <template x-if="group.mode === 'retail'">
                                                <div class="flex flex-wrap items-center gap-1" role="group" aria-label="{{ __('Size') }}" x-init="syncRetailColor(group)" x-show="! (product(group).colors || []).length || group.color_id">
                                                    <template x-for="variant in retailSizes(group)" :key="variant.id">
                                                        <button type="button" class="order-edit-size" :class="{ 'order-edit-size-active': Number(variant.id) === Number(group.variant_id), 'order-chip-disabled': variant.stock < 1 }" :disabled="variant.stock < 1" :title="variant.stock < 1 ? '{{ __('Sold out') }}' : variant.size" :aria-pressed="Number(variant.id) === Number(group.variant_id)" @click="pickSize(group, variant)" x-text="variant.size"></button>
                                                    </template>
                                                </div>
                                            </template>
                                            <span class="order-edit-meta" x-show="group.mode === 'wholesale' && group.color_id" x-text="ratioShort(group)"></span>
                                        </div>
                                        <p class="text-xs text-error" x-show="showMissing && ! isComplete(group)" x-cloak x-text="group.mode === 'retail' ? '{{ __('Choose a colour and size.') }}' : '{{ __('Choose a colour.') }}'"></p>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" @click="add(); afterRowChange()"><i class="ph ph-plus"></i> {{ __('Add line') }}</button>
                </section>
                @else
                <section class="section-card space-y-4">
                    <h2 class="heading-5">{{ __('3. Products') }}</h2>
                    <template x-for="(group, index) in groups" :key="group.key">
                        <div class="space-y-3 rounded-md border border-border p-4">
                            <template x-if="group.locked">
                                <div class="flex flex-wrap items-center justify-between gap-2" data-locked-group>
                                    <input type="hidden" :name="`groups[${index}][keep_group_id]`" :value="group.keep_group_id">
                                    <input type="hidden" :name="`groups[${index}][product_id]`" :value="group.product_id">
                                    <input type="hidden" :name="`groups[${index}][mode]`" value="wholesale">
                                    <input type="hidden" :name="`groups[${index}][color_id]`" :value="group.color_id">
                                    <input type="hidden" :name="`groups[${index}][box_count]`" :value="group.box_count">
                                    <div>
                                        <p class="font-medium text-title" x-text="group.label"></p>
                                        <p class="text-xs text-body">{{ __('Customer-built pack · kept as ordered') }}</p>
                                    </div>
                                    <i class="ph ph-lock-simple text-body" aria-hidden="true"></i>
                                </div>
                            </template>
                            <template x-if="! group.locked">
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
                            </template>
                            <p class="text-sm text-body" x-show="group.mode === 'wholesale' && group.color_id && ! group.locked" x-text="ratio(group)"></p>
                            <div class="flex gap-4 text-sm">
                                <button type="button" class="text-primary" x-show="! group.locked" @click="duplicate(group)">{{ __('Duplicate') }}</button>
                                <button type="button" class="text-danger" x-show="groups.length > 1" @click="remove(group)">{{ __('Remove') }}</button>
                            </div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-outline" @click="add()">+ {{ __('Add product') }}</button>
                </section>
                @endif
            </div>

            <aside class="lg:col-span-1">
                <section class="section-card space-y-3 lg:sticky lg:top-4">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5">{{ $editingOrder ? __('Summary') : __('4. Summary') }}</h2>
                        <button type="button" class="text-sm text-primary" @click="preview()" :disabled="busy" x-text="busy ? '{{ __('Updating…') }}' : '{{ __('Refresh') }}'"></button>
                    </div>
                    <p class="text-danger" role="alert" x-show="error" x-text="error"></p>
                    <p class="text-sm text-body" x-show="!quote && !error">{{ __('Pick products to see the total.') }}</p>
                    <template x-if="quote">
                        <div class="space-y-3">
                            <div class="flex justify-between"><span>{{ __('Merchandise') }}</span><span class="font-semibold" x-text="`${quote.currency} ${quote.subtotal}`"></span></div>
                            @if ($editingOrder)
                                <div class="flex justify-between text-sm"><span>{{ __('Shipping') }}</span><span x-text="quote.shipping_required ? (quote.shipping_amount !== null ? `${quote.currency} ${quote.shipping_amount}` : '{{ __('Quote required') }}') : '{{ __('Included') }}'"></span></div>
                            @else
                            <div>
                                <label class="form-label">{{ __('Shipping') }}</label>
                                <select class="form-input" name="shipping_method_id" x-model="shippingMethodId">
                                    <option value="">{{ __('Best available shipping / request quote') }}</option>
                                    <template x-for="option in quote.shipping_options" :key="option.id"><option :value="option.id" :selected="String(option.id) === String(shippingMethodId)" x-text="`${option.name}: ${option.currency} ${option.price}`"></option></template>
                                </select>
                            </div>
                            @endif
                            <div class="flex justify-between border-t border-border pt-3 text-lg font-bold">
                                <span>{{ __('Total') }}</span>
                                <span x-text="quote.shipping_quote_required ? '{{ __('Shipping quote required') }}' : `${quote.currency} ${quote.total}`"></span>
                            </div>
                            <p class="text-xs font-semibold text-danger" role="alert" x-show="shortStock().length" x-cloak data-short-stock x-text="`{{ __('Not enough stock:') }} ${shortStock().map(stock => `${stock.sku} (${stock.available} {{ __('left') }})`).join(', ')}`"></p>
                        </div>
                    </template>
                    <div class="flex flex-col gap-2 pt-2">
                        @if ($editingOrder)
                            <button type="submit" value="0" class="btn btn-primary" :disabled="!hasProduct()" data-save-order-changes>{{ __('Save changes') }}</button>
                            <a href="{{ route('user.commerce.orders.show', $editingOrder) }}" class="btn btn-outline">{{ __('Cancel') }}</a>
                        @else
                            <button type="submit" name="draft" value="0" class="btn btn-primary" :disabled="!hasProduct()">{{ __('Create order and reserve stock') }}</button>
                        @endif
                        @unless ($completingOrder || $editingOrder)
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
                    this.shippingMethodId = this.$el.dataset.shippingMethod || '';
                    this.sameAsCustomer = this.$el.dataset.sameAsCustomer !== '0';
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
                setMode(group, mode) { if (group.mode !== mode) { group.mode = mode; group.variant_id = ''; this.afterRowChange(); } },
                colorName(group) { return (this.product(group).colors || []).find(color => Number(color.id) === Number(group.color_id))?.name || ''; },
                packSize(group) {
                    const product = this.product(group);
                    const ratio = product.ratios?.[group.color_id] || {};
                    return Object.values(ratio).reduce((sum, qty) => sum + Number(qty), 0) * (product.multiplier || 1);
                },
                linePieces(group) { return group.mode === 'retail' ? Number(group.count || 0) : this.packSize(group) * Number(group.count || 0); },
                totalPieces() { return this.groups.reduce((sum, group) => sum + (group.locked ? 0 : this.linePieces(group)), 0); },
                ratioShort(group) {
                    const product = this.product(group);
                    const ratio = product.ratios?.[group.color_id] || {};
                    const sizes = Object.entries(ratio).map(([size, qty]) => `${size}${qty * (product.multiplier || 1)}`).join(' ');
                    return `${sizes} · ${this.packSize(group)} pcs/box · ${this.linePieces(group)} pcs`;
                },
                shortStock() { return (this.quote?.availability || []).filter(stock => stock.required > stock.available); },
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
                        const body = new FormData(this.form());
                        body.delete('_method');
                        const response = await fetch('{{ route('user.commerce.orders.preview') }}', { method: 'POST', body, headers: { Accept: 'application/json' } });
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
