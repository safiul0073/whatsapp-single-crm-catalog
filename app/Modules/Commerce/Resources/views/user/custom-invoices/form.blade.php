@php
    $action = $invoice ? route('user.commerce.custom-invoices.update', $invoice) : route('user.commerce.custom-invoices.store');
    $lines = old('items', $draft['items'] ?? []);
    $value = fn (string $key) => old($key, $draft[$key] ?? null);
@endphp
<x-layouts.user :title="$invoice ? __('Edit :number', ['number' => $invoice->number]) : __('New custom invoice')">
    <form method="POST" action="{{ $action }}" class="space-y-6" x-data="customInvoiceBuilder()"
        data-products="{{ json_encode($products) }}"
        data-lines="{{ json_encode(array_values($lines)) }}"
        data-customer="{{ json_encode($customer) }}"
        data-customer-url="{{ route('user.commerce.custom-invoices.customers') }}"
        data-currency="{{ $currency }}"
        data-amounts="{{ json_encode(['discount' => $value('discount_amount'), 'tax' => $value('tax_amount'), 'shipping' => $value('shipping_amount')]) }}">
        @csrf
        @if ($invoice) @method('PUT') @endif
        @if ($invoiceRequest)
            <input type="hidden" name="invoice_request_id" value="{{ $invoiceRequest->id }}">
        @endif

        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ $invoice ? route('user.commerce.custom-invoices.show', $invoice) : route('user.commerce.custom-invoices.index') }}" class="text-sm text-primary hover:underline">← {{ __('Back') }}</a>
                <h1 class="heading-3 text-title font-bold">{{ $invoice ? __('Edit :number', ['number' => $invoice->number]) : __('New custom invoice') }}</h1>
            </div>
            <div class="flex gap-2">
                <x-ui.button type="submit" size="sm" variant="outline">{{ __('Save draft') }}</x-ui.button>
                <x-ui.button type="submit" size="sm" name="issue" value="1">{{ __('Save & issue') }}</x-ui.button>
            </div>
        </header>

        @if ($errors->any())
            <x-ui.alert type="error">
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </x-ui.alert>
        @endif

        @if ($invoiceRequest)
            <section class="card p-5 space-y-1 text-sm">
                <h2 class="heading-5">{{ __('Customer request') }}</h2>
                <p class="text-body whitespace-pre-line">{{ $invoiceRequest->message }}</p>
                <p class="text-xs text-body">{{ __('Requested products were added below. Adjust anything before issuing.') }}</p>
            </section>
        @endif

        <section class="section-card space-y-4">
            <h2 class="heading-5">{{ __('1. Bill to') }}</h2>
            <div class="space-y-2">
                <label class="form-label" for="customer-search">{{ __('Store customer (optional)') }}</label>
                <input type="hidden" name="customer_id" :value="customer ? customer.id : ''">
                <div class="flex items-center gap-2" x-show="customer" x-cloak>
                    <x-ui.badge variant="primary"><span x-text="customer ? customer.name + (customer.phone ? ' · ' + customer.phone : '') : ''"></span></x-ui.badge>
                    <button type="button" class="text-sm text-danger" @click="clearCustomer()">{{ __('Remove') }}</button>
                </div>
                <div class="relative" x-show="! customer">
                    <input id="customer-search" type="search" class="form-input" placeholder="{{ __('Search by name, phone or email') }}" x-model="customerQuery" @input.debounce.300ms="searchCustomers()" autocomplete="off">
                    <ul class="absolute z-10 mt-1 w-full rounded-md border border-border bg-white shadow-lg dark:bg-neutral-900" x-show="customerResults.length" x-cloak>
                        <template x-for="result in customerResults" :key="result.id">
                            <li><button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-neutral-50" @click="pickCustomer(result)" x-text="result.name + (result.phone ? ' · ' + result.phone : '')"></button></li>
                        </template>
                    </ul>
                </div>
                <p class="text-xs text-body">{{ __('Linking a customer lets them see and download the invoice from their account.') }}</p>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="form-label" for="bill_to_name">{{ __('Name') }}</label>
                    <input id="bill_to_name" name="bill_to_name" class="form-input" maxlength="255" value="{{ $value('bill_to_name') }}" x-ref="billName">
                </div>
                <div>
                    <label class="form-label" for="bill_to_phone">{{ __('Phone') }}</label>
                    <input id="bill_to_phone" name="bill_to_phone" class="form-input" maxlength="40" value="{{ $value('bill_to_phone') }}" x-ref="billPhone">
                </div>
                <div>
                    <label class="form-label" for="bill_to_email">{{ __('Email') }}</label>
                    <input id="bill_to_email" type="email" name="bill_to_email" class="form-input" maxlength="255" value="{{ $value('bill_to_email') }}" x-ref="billEmail">
                </div>
                <div class="md:col-span-3">
                    <label class="form-label" for="bill_to_address">{{ __('Address') }}</label>
                    <textarea id="bill_to_address" name="bill_to_address" rows="2" class="form-input" maxlength="1000">{{ $value('bill_to_address') }}</textarea>
                </div>
            </div>
        </section>

        <section class="section-card space-y-4">
            <h2 class="heading-5">{{ __('2. Lines') }}</h2>
            <template x-for="(line, index) in lines" :key="line.key">
                <div class="grid gap-3 rounded-md border border-border p-4 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <label class="form-label">{{ __('Product') }}</label>
                        <select class="form-input" :name="`items[${index}][product_id]`" x-model="line.product_id" @change="pickProduct(line)">
                            <option value="">{{ __('— Custom line (no product) —') }}</option>
                            <template x-for="product in products" :key="product.id">
                                <option :value="product.id" x-text="product.name" :selected="Number(product.id) === Number(line.product_id)"></option>
                            </template>
                        </select>
                        <template x-if="variantsOf(line).length">
                            <select class="form-input mt-2" :name="`items[${index}][variant_id]`" x-model="line.variant_id" @change="pickVariant(line)">
                                <option value="">{{ __('Any option') }}</option>
                                <template x-for="variant in variantsOf(line)" :key="variant.id">
                                    <option :value="variant.id" x-text="variant.label" :selected="Number(variant.id) === Number(line.variant_id)"></option>
                                </template>
                            </select>
                        </template>
                    </div>
                    <div class="md:col-span-4">
                        <label class="form-label">{{ __('Description') }}</label>
                        <input class="form-input" maxlength="250" :name="`items[${index}][name]`" x-model="line.name" :required="! line.product_id" placeholder="{{ __('e.g. Custom embroidery') }}">
                        <input class="form-input mt-2 text-sm" maxlength="500" :name="`items[${index}][description]`" x-model="line.description" placeholder="{{ __('Details (optional)') }}">
                    </div>
                    <div class="md:col-span-1">
                        <label class="form-label">{{ __('Qty') }}</label>
                        <input class="form-input" type="number" min="1" max="100000" :name="`items[${index}][quantity]`" x-model.number="line.quantity" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label">{{ __('Unit price') }}</label>
                        <input class="form-input" type="number" min="0" step="any" :name="`items[${index}][unit_price]`" x-model="line.unit_price" :required="! line.product_id">
                        <p class="mt-1 text-xs text-body" x-text="currency + ' ' + lineTotal(line)"></p>
                    </div>
                    <div class="flex items-end md:col-span-1">
                        <button type="button" class="text-sm text-danger" x-show="lines.length > 1" @click="removeLine(line)" aria-label="{{ __('Remove line') }}"><i class="ph ph-trash text-lg"></i></button>
                    </div>
                </div>
            </template>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline btn-sm" @click="addLine(true)">+ {{ __('Add product') }}</button>
                <button type="button" class="btn btn-outline btn-sm" @click="addLine(false)">+ {{ __('Add custom line') }}</button>
            </div>
        </section>

        <section class="section-card space-y-4">
            <h2 class="heading-5">{{ __('3. Totals & notes') }}</h2>
            <div class="grid gap-3 md:grid-cols-4">
                <div>
                    <label class="form-label" for="discount_amount">{{ __('Discount') }}</label>
                    <input id="discount_amount" type="number" min="0" step="any" name="discount_amount" class="form-input" value="{{ $value('discount_amount') }}" x-model="discount">
                </div>
                <div>
                    <label class="form-label" for="tax_amount">{{ __('Tax') }}</label>
                    <input id="tax_amount" type="number" min="0" step="any" name="tax_amount" class="form-input" value="{{ $value('tax_amount') }}" x-model="tax">
                </div>
                <div>
                    <label class="form-label" for="shipping_amount">{{ __('Shipping') }}</label>
                    <input id="shipping_amount" type="number" min="0" step="any" name="shipping_amount" class="form-input" value="{{ $value('shipping_amount') }}" x-model="shipping">
                </div>
                <div>
                    <label class="form-label" for="due_date">{{ __('Due date') }}</label>
                    <input id="due_date" type="date" name="due_date" class="form-input" value="{{ $value('due_date') }}">
                </div>
                <div class="md:col-span-4">
                    <label class="form-label" for="notes">{{ __('Notes / payment instructions') }}</label>
                    <textarea id="notes" name="notes" rows="3" class="form-input" maxlength="2000">{{ $value('notes') }}</textarea>
                </div>
            </div>
            <p class="text-right text-lg font-bold text-title">{{ __('Estimated total') }}: <span x-text="currency + ' ' + grandTotal()"></span></p>
            <p class="text-right text-xs text-body">{{ __('The final total is calculated by the server when you save.') }}</p>
        </section>
    </form>
</x-layouts.user>
