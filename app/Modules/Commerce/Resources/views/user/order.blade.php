<x-layouts.user :title="$order->number">
    <div class="space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-semibold text-primary">{{ $order->number }}</p><h1 class="heading-3 text-title">{{ __('Order request') }}</h1><p class="text-sm text-body">{{ $order->contact?->name }} · {{ $order->contact?->phone }}</p></div>
            <span class="badge badge-soft w-fit">{{ str($order->status)->replace('_', ' ')->title() }}</span>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => 'order'])

        @if($order->issues)
            <section class="rounded-xl border border-warning/30 bg-warning/10 p-4"><h2 class="font-semibold text-title">{{ __('Catalog issues') }}</h2><ul class="mt-2 list-disc pl-5 text-sm text-body">@foreach($order->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></section>
        @endif

        <section class="section-card overflow-x-auto">
            <h2 class="heading-5 text-title">{{ __('Immutable item snapshot') }}</h2>
            <table class="mt-4 w-full min-w-[620px] text-left text-sm">
                <thead><tr class="border-b border-border text-body"><th class="p-3">{{ __('Product') }}</th><th class="p-3">{{ __('Variant') }}</th><th class="p-3">{{ __('Qty') }}</th><th class="p-3">{{ __('Price') }}</th><th class="p-3">{{ __('Total') }}</th></tr></thead>
                <tbody>@foreach($order->items as $item)<tr class="border-b border-border-soft"><td class="p-3 text-title">{{ $item->product_name }}<span class="block text-xs text-body">{{ $item->sku }}</span></td><td class="p-3 text-body">{{ collect($item->attributes)->map(fn($value,$key) => str($key)->title().': '.$value)->implode(', ') }}</td><td class="p-3">{{ $item->quantity }}</td><td class="p-3">${{ number_format((float)$item->unit_price, 2) }}</td><td class="p-3 font-semibold">${{ number_format((float)$item->line_total, 2) }}</td></tr>@endforeach</tbody>
            </table>
            <div class="mt-4 text-right font-semibold text-title">{{ __('Subtotal') }}: ${{ number_format((float)$order->subtotal, 2) }}</div>
        </section>

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('Shipping quote and payment link') }}</h2>
            <form method="POST" action="{{ route('user.commerce.orders.quote', $order) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf @method('PUT') @php($address=$order->shipping_address ?? [])
                <input class="form-input" name="shipping_name" required placeholder="{{ __('Recipient name') }}" value="{{ old('shipping_name',$address['name']??'') }}">
                <input class="form-input" name="shipping_phone" required placeholder="{{ __('US phone') }}" value="{{ old('shipping_phone',$address['phone']??'') }}">
                <input class="form-input md:col-span-2" name="shipping_line1" required placeholder="{{ __('Address line 1') }}" value="{{ old('shipping_line1',$address['line1']??'') }}">
                <input class="form-input md:col-span-2" name="shipping_line2" placeholder="{{ __('Address line 2') }}" value="{{ old('shipping_line2',$address['line2']??'') }}">
                <input class="form-input" name="shipping_city" required placeholder="{{ __('City') }}" value="{{ old('shipping_city',$address['city']??'') }}">
                <div class="grid grid-cols-2 gap-3"><input class="form-input uppercase" maxlength="2" name="shipping_state" required placeholder="{{ __('State') }}" value="{{ old('shipping_state',$address['state']??'') }}"><input class="form-input" name="shipping_postal_code" required placeholder="{{ __('ZIP code') }}" value="{{ old('shipping_postal_code',$address['postal_code']??'') }}"></div>
                <input class="form-input" type="number" step="0.01" min="0" name="shipping_amount" required placeholder="{{ __('Shipping USD') }}" value="{{ old('shipping_amount',$order->shipping_amount) }}">
                <input class="form-input" name="delivery_method" placeholder="{{ __('Delivery method') }}" value="{{ old('delivery_method',$order->delivery_method) }}">
                <input class="form-input md:col-span-2" type="url" name="payment_url" placeholder="https://secure-payment.example/..." value="{{ old('payment_url',$order->payment_url) }}">
                <textarea class="form-input md:col-span-2" name="delivery_notes" placeholder="{{ __('Delivery notes') }}">{{ old('delivery_notes',$order->delivery_notes) }}</textarea>
                <textarea class="form-input md:col-span-2" name="duties_disclosure">{{ old('duties_disclosure',$order->duties_disclosure ?: 'Import duties and taxes, if any, are the buyer’s responsibility unless stated otherwise.') }}</textarea>
                <div class="md:col-span-2"><x-forms.submit :label="__('Save quote')" /></div>
            </form>
        </section>

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('Order workflow') }}</h2>
            <form method="POST" action="{{ route('user.commerce.orders.transition',$order) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf @method('PUT')
                <div><label class="form-label" for="status">{{ __('Next status') }}</label><select id="status" class="form-input" name="status" required>@foreach(['requested','needs_details','quoted','awaiting_payment','paid','processing','shipped','completed','cancelled'] as $status)<option value="{{ $status }}">{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                <input class="form-input" name="tracking_number" placeholder="{{ __('Tracking number') }}">
                <input class="form-input" type="url" name="tracking_url" placeholder="https://...">
                <x-forms.submit :label="__('Update status')" />
            </form>
        </section>
    </div>
</x-layouts.user>
