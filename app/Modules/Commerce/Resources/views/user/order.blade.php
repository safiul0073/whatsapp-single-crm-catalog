<x-layouts.user :title="$order->number">
    @if($order->status === 'needs_details' && ! $order->groups()->exists())
        @can('commerce.manage')<a href="{{ route('user.commerce.orders.complete.form', $order) }}" class="text-primary underline">Confirm retail selections or wholesale box counts</a>@endcan
    @endif
    <div class="space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div><p class="text-sm font-semibold text-primary">{{ $order->number }}</p><h1 class="heading-3 text-title">{{ __('Order request') }}</h1><p class="text-sm text-body">{{ $order->contact?->name }} · {{ $order->contact?->phone }}</p></div>
            <span class="badge badge-soft w-fit">{{ str($order->status)->replace('_', ' ')->title() }}</span>
        </header>

        <section class="section-card space-y-4">
            <h2 class="heading-5">{{ __('Boxes and packing') }}</h2>
            <p>{{ __('Payment') }}: {{ $order->payment_state }} · {{ __('Tracking code') }}: {{ $order->tracking_code }}</p>
            @if($order->shipping_quote_required)<p class="text-warning">{{ __('Shipping quote required before payment confirmation.') }}</p>@endif
            @if($order->payment_evidence)<p>{{ $order->payment_evidence['note'] ?? '' }}</p>@if(!empty($order->payment_evidence['receipt_path']))<a href="{{ route('user.commerce.orders.receipt', $order) }}" class="text-primary">{{ __('Download payment receipt') }}</a>@endif @endif
            <a href="{{ route('user.commerce.orders.packing-slip', $order) }}" class="text-primary">{{ __('Print packing slip') }}</a>
            @foreach($order->boxes as $box)
                <div class="rounded-xl border border-border p-4"><h3 class="font-semibold">{{ $box->label }} · {{ $box->kind }}</h3><ul>@foreach($box->contents as $content)<li>{{ $content->item->product_name }} · {{ $content->item->sku }} · {{ $content->quantity }} {{ __('pieces') }}</li>@endforeach</ul>
                @if($box->packed_at)<p>{{ __('Packed') }} · {{ $box->packed_at }}</p>@elseif(in_array($order->status,['paid','processing']))@can('commerce.manage')<form method="POST" action="{{ route('user.commerce.orders.boxes.packed',[$order,$box]) }}">@csrf @method('PUT')<x-forms.submit :label="__('Mark box packed')" /></form>@endcan @endif</div>
            @endforeach
            @if(in_array($order->status,['paid','processing']))@can('commerce.manage')
                <form method="POST" action="{{ route('user.commerce.orders.boxes.store',$order) }}" class="space-y-3">@csrf<h3 class="font-semibold">{{ __('Add retail shipping box') }}</h3>
                @foreach($order->items as $item) @if(!$order->groups->firstWhere('id',$item->group_id) || $order->groups->firstWhere('id',$item->group_id)->mode === 'retail')<div><label class="form-label" for="pack_{{ $item->id }}">{{ $item->product_name }} · {{ $item->sku }} ({{ $item->quantity }} {{ __('ordered') }})</label><input class="form-input" id="pack_{{ $item->id }}" type="number" min="0" max="{{ $item->quantity }}" value="0" name="quantities[{{ $item->id }}]"></div>@endif @endforeach
                <x-forms.submit :label="__('Create retail box')" /></form>
            @endcan @endif
        </section>

        @include('commerce::user.partials.help', ['helpKey' => 'order'])

        @if($order->issues)
            <section class="rounded-xl border border-warning/30 bg-warning/10 p-4"><h2 class="font-semibold text-title">{{ __('Catalog issues') }}</h2><ul class="mt-2 list-disc pl-5 text-sm text-body">@foreach($order->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></section>
        @endif

        <section class="section-card overflow-x-auto">
            <h2 class="heading-5 text-title">{{ __('Immutable item snapshot') }}</h2>
            <table class="mt-4 w-full min-w-[620px] text-left text-sm">
                <thead><tr class="border-b border-border text-body"><th class="p-3">{{ __('Product') }}</th><th class="p-3">{{ __('Variant') }}</th><th class="p-3">{{ __('Qty') }}</th><th class="p-3">{{ __('Price') }}</th><th class="p-3">{{ __('Total') }}</th></tr></thead>
                <tbody>@foreach($order->items as $item)<tr class="border-b border-border-soft"><td class="p-3 text-title">{{ $item->product_name }}<span class="block text-xs text-body">{{ $item->sku }}</span></td><td class="p-3 text-body">{{ collect($item->attributes)->map(fn($value,$key) => str($key)->title().': '.$value)->implode(', ') }}</td><td class="p-3">{{ $item->quantity }}</td><td class="p-3">{{ $order->currency }} {{ $item->unit_price }}</td><td class="p-3 font-semibold">{{ $order->currency }} {{ $item->line_total }}</td></tr>@endforeach</tbody>
            </table>
            <div class="mt-4 text-right font-semibold text-title">{{ __('Subtotal') }}: {{ $order->currency }} {{ $order->subtotal }}</div>
        </section>

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('Shipping quote and payment link') }}</h2>
            <form method="POST" action="{{ route('user.commerce.orders.quote', $order) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf @method('PUT') @php($address=$order->shipping_address ?? [])
                <input class="form-input" name="shipping_name" required placeholder="{{ __('Recipient name') }}" value="{{ old('shipping_name',$address['name']??'') }}">
                <input class="form-input" name="shipping_phone" required placeholder="{{ __('Delivery phone') }}" value="{{ old('shipping_phone',$address['phone']??'') }}">
                <input class="form-input md:col-span-2" name="shipping_line1" required placeholder="{{ __('Address line 1') }}" value="{{ old('shipping_line1',$address['line1']??'') }}">
                <input class="form-input md:col-span-2" name="shipping_line2" placeholder="{{ __('Address line 2') }}" value="{{ old('shipping_line2',$address['line2']??'') }}">
                <input class="form-input" name="shipping_city" required placeholder="{{ __('City') }}" value="{{ old('shipping_city',$address['city']??'') }}">
                <div class="grid grid-cols-2 gap-3"><input class="form-input" maxlength="120" name="shipping_state" placeholder="{{ __('State') }}" value="{{ old('shipping_state',$address['state']??'') }}"><input class="form-input" name="shipping_postal_code" placeholder="{{ __('ZIP code') }}" value="{{ old('shipping_postal_code',$address['postal_code']??'') }}"></div>
                <input class="form-input uppercase" name="shipping_country" required maxlength="2" value="{{ old('shipping_country',$address['country']??'') }}" placeholder="{{ __('Country code') }}">
                <input class="form-input" type="number" step="0.001" min="0" name="shipping_amount" required placeholder="{{ __('Shipping').' '.$order->currency }}" value="{{ old('shipping_amount',$order->shipping_amount) }}">
                <input class="form-input" name="delivery_method" placeholder="{{ __('Delivery method') }}" value="{{ old('delivery_method',$order->delivery_method) }}">
                <input class="form-input md:col-span-2" type="url" name="payment_url" placeholder="https://secure-payment.example/..." value="{{ old('payment_url',$order->payment_url) }}">
                <textarea class="form-input md:col-span-2" name="delivery_notes" placeholder="{{ __('Delivery notes') }}">{{ old('delivery_notes',$order->delivery_notes) }}</textarea>
                <textarea class="form-input md:col-span-2" name="duties_disclosure">{{ old('duties_disclosure',$order->duties_disclosure ?: 'Import duties and taxes, if any, are the buyer’s responsibility unless stated otherwise.') }}</textarea>
                <div class="md:col-span-2"><x-forms.submit :label="__('Save quote')" /></div>
            </form>
        </section>

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('Order workflow') }}</h2>
            <p class="mt-2 text-sm text-body">{{ __('When shipping this order, enter the tracking number and optional carrier URL below. Customers can use that number on the storefront Order Tracking page. Mark the order completed after delivery.') }}</p>
            @if($order->tracking_number)
                <div class="mt-4 rounded-xl border border-border p-4">
                    <p class="font-semibold text-title">{{ __('Tracking number') }}: {{ $order->tracking_number }}</p>
                    @if($order->tracking_url)
                        <p class="mt-1 break-all text-sm text-body">{{ __('Carrier URL') }}: {{ $order->tracking_url }}</p>
                    @endif
                    <ol class="mt-3 flex flex-wrap gap-3 text-sm text-body">
                        @foreach($order->trackingTimeline() as $step)
                            <li>{{ $step['label'] }} · {{ ucfirst($step['state']) }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif
            <form method="POST" action="{{ route('user.commerce.orders.transition',$order) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf @method('PUT')
                <div><label class="form-label" for="status">{{ __('Next status') }}</label><select id="status" class="form-input" name="status" required>@foreach(['requested','needs_details','quoted','awaiting_payment','paid','processing','packed','shipped','completed','cancelled'] as $status)<option value="{{ $status }}">{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                <div><label class="form-label" for="carrier">{{ __('Carrier') }}</label><input id="carrier" class="form-input" name="carrier" maxlength="150"></div>
                <div><label class="form-label" for="tracking_number">{{ __('Tracking number') }}</label><input id="tracking_number" class="form-input" name="tracking_number" maxlength="150" value="{{ old('tracking_number', $order->tracking_number) }}" placeholder="{{ __('Tracking number') }}"></div>
                <div><label class="form-label" for="tracking_url">{{ __('Carrier tracking URL') }}</label><input id="tracking_url" class="form-input" type="url" name="tracking_url" maxlength="2048" value="{{ old('tracking_url', $order->tracking_url) }}" placeholder="https://..."></div>
                <x-forms.submit :label="__('Update status')" />
            </form>
        </section>
    </div>
</x-layouts.user>
