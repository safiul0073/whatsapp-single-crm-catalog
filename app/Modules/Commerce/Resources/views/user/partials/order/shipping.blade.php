<section id="shipping" class="section-card space-y-5 scroll-mt-24">
    <h2 class="heading-5 text-title">{{ __('Status and shipping') }}</h2>
    @if($showStatusForm)
    <div>
                
                    <h3 class="text-sm font-semibold text-title">{{ __('Update status and tracking') }}</h3>
                    <p class="mt-1 text-sm text-body">{{ __('Customers use the tracking number on the storefront Order Tracking page. Mark the order completed after delivery.') }}</p>
                    <form method="POST" action="{{ route('user.commerce.orders.transition', $order) }}" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf @method('PUT')
                        <div><label class="form-label" for="status">{{ __('Next status') }}</label><select id="status" class="form-input" name="status" required>@foreach($order->source === 'pos' ? array_merge([$order->status], ($order->fulfillment_type === 'delivery' ? match ($order->status) { 'processing' => ['packed'], 'packed' => ['shipped'], 'shipped' => ['completed'], default => [] } : []), (! $order->inventory_adjusted_at && $order->payments->isEmpty() && ! in_array($order->status, ['completed', 'cancelled']) ? ['cancelled'] : [])) : ['requested','needs_details','quoted','awaiting_payment','paid','processing','packed','shipped','completed','cancelled'] as $status)<option value="{{ $status }}" @selected($status === $order->status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                        <div><label class="form-label" for="carrier">{{ __('Carrier') }}</label><input id="carrier" class="form-input" name="carrier" maxlength="150" value="{{ old('carrier', $order->shipment?->carrier !== 'Carrier' ? $order->shipment?->carrier : '') }}" placeholder="{{ __('e.g. DHL, FedEx, Pathao') }}"></div>
                        <div><label class="form-label" for="tracking_number">{{ __('Tracking number') }}</label><input id="tracking_number" class="form-input" name="tracking_number" maxlength="150" value="{{ old('tracking_number', $order->tracking_number) }}"></div>
                        <div><label class="form-label" for="tracking_url">{{ __('Carrier tracking URL') }}</label><input id="tracking_url" class="form-input" type="url" name="tracking_url" maxlength="2048" value="{{ old('tracking_url', $order->tracking_url) }}" placeholder="https://..."></div>
                        <div class="md:col-span-2">
                            <label class="form-label" for="proof_image">{{ __('Courier image (optional)') }}</label>
                            <input id="proof_image" class="form-input" type="file" name="proof_image" accept="image/png,image/jpeg,image/webp">
                            <p class="mt-1 text-xs text-body">{{ __('Courier receipt, parcel label or handover photo. Customers see it on the Shipped step. JPG, PNG or WebP up to 5 MB.') }}</p>
                            @error('proof_image')<p class="form-hint text-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2"><x-forms.submit :label="__('Update status')" /></div>
                    </form>
                
    </div>
    @endif
    @if(in_array($order->status, ['shipped', 'completed'], true))
    <details class="rounded-md border border-border-soft p-4" @if(! $showStatusForm) open @endif>
                
                    <summary class="cursor-pointer text-sm font-semibold text-title">{{ __('Shipment details') }}</summary>
                    <p class="mt-1 text-sm text-body">{{ __('Shown to the customer on the Shipped step of order tracking. Update the courier link or image at any time.') }}</p>
                    <form method="POST" action="{{ route('user.commerce.orders.shipment', $order) }}" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf @method('PUT')
                        <div><label class="form-label" for="shipment_carrier">{{ __('Carrier') }}</label><input id="shipment_carrier" class="form-input" name="carrier" maxlength="150" value="{{ old('carrier', $order->shipment?->carrier !== 'Carrier' ? $order->shipment?->carrier : '') }}" placeholder="{{ __('e.g. DHL, FedEx, Pathao') }}"></div>
                        <div><label class="form-label" for="shipment_tracking_number">{{ __('Tracking number') }}</label><input id="shipment_tracking_number" class="form-input" name="tracking_number" maxlength="150" required value="{{ old('tracking_number', $order->shipment?->tracking_number ?? $order->tracking_number) }}"></div>
                        <div class="md:col-span-2"><label class="form-label" for="shipment_tracking_url">{{ __('Carrier tracking URL') }}</label><input id="shipment_tracking_url" class="form-input" type="url" name="tracking_url" maxlength="2048" value="{{ old('tracking_url', $order->shipment?->tracking_url ?? $order->tracking_url) }}" placeholder="https://..."></div>
                        <div class="md:col-span-2">
                            <span class="form-label">{{ __('Courier image') }}</span>
                            @if($order->shipment?->proofImageUrl())
                                <div class="mb-3 flex items-center gap-4">
                                    <a href="{{ $order->shipment->proofImageUrl() }}" target="_blank" rel="noopener" class="block size-24 shrink-0 overflow-hidden rounded-lg border border-border">
                                        <img src="{{ $order->shipment->proofImageUrl() }}" alt="{{ __('Courier image') }}" class="size-full object-cover">
                                    </a>
                                    <label class="inline-label flex items-center gap-2 text-sm text-body">
                                        <input type="checkbox" name="remove_proof_image" value="1">
                                        {{ __('Remove current image') }}
                                    </label>
                                </div>
                            @endif
                            <input id="shipment_proof_image" class="form-input" type="file" name="proof_image" accept="image/png,image/jpeg,image/webp" aria-label="{{ __('Upload courier image') }}">
                            <p class="mt-1 text-xs text-body">{{ $order->shipment?->proof_image ? __('Uploading a new image replaces the current one.') : __('JPG, PNG or WebP up to 5 MB.') }}</p>
                            @error('proof_image')<p class="form-hint text-error">{{ $message }}</p>@enderror
                            @error('shipment')<p class="form-hint text-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="md:col-span-2"><x-forms.submit :label="__('Save shipment details')" /></div>
                    </form>
                
    </details>
    @endif
</section>
