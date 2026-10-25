<x-layouts.user :title="$order->number">
    @php
        $address = $order->shipping_address ?? [];
        $isAddressLocked = $order->hasCompleteShippingAddress();
        $precision = (new \App\Modules\Commerce\Models\StoreOrderSetting(['currency' => $order->currency]))->precision();
        $money = fn ($amount) => $order->currency.' '.number_format((float) $amount, $precision);
        $canViewSummary = auth()->user()->can('commerce.orders.summary.view');
        $canViewCustomer = auth()->user()->can('commerce.orders.customer.view');
        $canViewPayments = auth()->user()->can('commerce.orders.payments.view');
        $progressSteps = $order->source === 'pos' ? ($order->fulfillment_type === 'pickup' ? ['processing', 'completed'] : ['requested', 'processing', 'packed', 'shipped', 'completed']) : ['requested', 'quoted', 'awaiting_payment', 'paid', 'processing', 'packed', 'shipped', 'completed'];
        $currentStepIndex = array_search($order->status, $progressSteps, true);
        $packedPieces = $order->boxes->flatMap->contents->sum('quantity');
        $orderedPieces = $order->items->sum('quantity');
        $packedQuantities = $order->boxes->flatMap->contents->groupBy('order_item_id')->map(fn ($contents) => $contents->sum('quantity'));
        $unpackedItems = $order->items->filter(fn ($item) => $item->quantity > ($packedQuantities[$item->id] ?? 0));
        $productKey = fn ($item) => ($item->variant?->product_id ?? 'snapshot').'|'.$item->product_name;
        $packProductGroups = $order->boxes->groupBy(fn ($box) => $box->contents->map(fn ($content) => $productKey($content->item))->unique()->sort()->implode('||'));
        $individualProductGroups = $unpackedItems->groupBy($productKey);
        $sizeOrder = array_flip(['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', 'XXXL', '4XL', '5XL']);
    @endphp

    <div class="space-y-6" x-data="commerceProductGallery">
        <header class="section-card flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="space-y-1">
                <p class="text-sm font-semibold text-primary">{{ $order->number }}</p>
                <h1 class="heading-3 text-title">{{ $canViewCustomer ? ($order->contact?->name ?? ($order->customer_snapshot['name'] ?? $address['name'] ?? __('Customer'))) : __('Order') }}</h1>
                <p class="text-sm text-body">{{ __('Placed') }} {{ $order->created_at?->format('M j, Y · g:i A') }} · {{ __('Source') }}: {{ str($order->source ?? 'manual')->replace('_', ' ')->title() }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge badge-soft">{{ str($order->status)->replace('_', ' ')->title() }}</span>
                @if($canViewPayments)<span class="badge badge-soft">{{ __('Payment') }}: {{ str($order->payment_state)->replace('_', ' ')->title() }}</span>@endif
                <x-ui.button href="{{ route('user.commerce.orders.packing-slip', $order) }}" variant="outline" class="rounded-sm text-xs py-1.5">
                    <i class="ph ph-printer mr-1"></i>{{ __('Packing slip') }}
                </x-ui.button>
            </div>
        </header>

        @if($order->status === 'cancelled')
            <p role="status" class="rounded-md border border-danger/30 bg-danger/10 p-4 text-sm text-title">{{ __('This order was cancelled.') }}</p>
        @elseif($currentStepIndex !== false)
            <ol class="section-card grid grid-cols-2 gap-2 {{ $order->source === 'pos' && $order->fulfillment_type === 'pickup' ? '' : 'sm:grid-cols-4 lg:grid-cols-8' }}">
                @foreach($progressSteps as $stepIndex => $step)
                    <li class="flex items-center gap-2 text-xs {{ $stepIndex <= $currentStepIndex ? 'font-semibold text-primary' : 'text-body' }}">
                        <i class="ph {{ $stepIndex < $currentStepIndex ? 'ph-check-circle' : ($stepIndex === $currentStepIndex ? 'ph-dot-outline' : 'ph-circle') }} text-base"></i>
                        {{ str($step)->replace('_', ' ')->title() }}
                    </li>
                @endforeach
            </ol>
        @endif

        @if($order->status === 'needs_details' && ! $order->groups()->exists())
            @can('commerce.orders.create')
                <a href="{{ route('user.commerce.orders.complete.form', $order) }}" class="block rounded-md border border-warning/30 bg-warning/10 p-4 text-sm font-semibold text-title">{{ __('Confirm retail selections or wholesale box counts') }} →</a>
            @endcan
        @endif

        @if($order->shipping_quote_required)
            <p role="status" class="rounded-md border border-warning/30 bg-warning/10 p-4 text-sm text-title">{{ __('Shipping quote required before the customer can pay.') }}</p>
        @endif

        @if($order->issues)
            <section class="rounded-md border border-warning/30 bg-warning/10 p-4"><h2 class="font-semibold text-title">{{ __('Catalog issues') }}</h2><ul class="mt-2 list-disc pl-5 text-sm text-body">@foreach($order->issues as $issue)<li>{{ $issue }}</li>@endforeach</ul></section>
        @endif

        @if($order->source === 'pos')
            @include('commerce::user.partials.pos-order')
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @if($order->boxes->isNotEmpty())
                    <section class="section-card space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="heading-5 text-title">{{ __('Packs') }}</h2>
                            <span class="text-sm text-body">{{ $order->boxes->count() }} {{ __('packs') }} · {{ $packedPieces }} {{ __('pieces') }}</span>
                        </div>
                        @foreach($packProductGroups as $productBoxes)
                            @php $packProducts = $productBoxes->flatMap->contents->map->item->unique($productKey); @endphp
                            <div class="space-y-4" data-pack-product-group>
                                @php
                                    $customPackSnapshot = $productBoxes->first()?->group?->pack_snapshot;
                                    $customPackName = $customPackSnapshot['name'] ?? null;
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border-soft pb-3">
                                    <h3 class="text-sm font-semibold leading-snug text-title flex items-center gap-1.5">
                                        @if($customPackName)
                                            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-950 dark:text-indigo-300">{{ $customPackName }}</span>
                                        @endif
                                        <span>{{ $packProducts->count() === 1 ? $packProducts->first()->product_name : __('Mixed products') }}</span>
                                    </h3>
                                    @if($packProductGroups->count() > 1)
                                        <span class="text-xs text-body">{{ $productBoxes->count() }} {{ __('packs') }} · {{ $productBoxes->flatMap->contents->sum('quantity') }} {{ __('pieces') }}</span>
                                    @endif
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    @foreach($productBoxes as $box)
                                        <article class="min-w-0 space-y-3 rounded-md border border-border-soft bg-bg-elevated p-4" data-pack-card data-pack-id="{{ $box->id }}">
                                            <div class="divide-y divide-border-soft">
                                                @foreach($box->contents->groupBy(fn ($content) => $productKey($content->item).'|'.($content->item->attributes['color'] ?? ''))->map(fn ($contents) => $contents->sortBy(fn ($content) => $sizeOrder[strtoupper($content->item->attributes['size'] ?? '')] ?? 99)) as $productColorContents)
                                                    @php $packItem = $productColorContents->first()->item; @endphp
                                                    <div class="space-y-3 py-3 first:pt-0 last:pb-0">
                                                        <div class="flex items-center gap-3">
                                                            @include('commerce::user.partials.order-product-image', ['item' => $packItem])
                                                            <div class="min-w-0 flex-1 space-y-1.5">
                                                                @if($packProducts->count() > 1)
                                                                    <p class="text-sm font-semibold leading-snug text-title">{{ $packItem->product_name }}</p>
                                                                @endif
                                                                <h4 class="text-sm font-semibold text-title">{{ __('Color') }}: {{ $packItem->attributes['color'] ?? '—' }}</h4>
                                                                <p class="text-xs text-body">{{ $productColorContents->sum('quantity') }} {{ __('pieces') }}</p>
                                                            </div>
                                                        </div>
                                                        <div class="overflow-x-auto rounded-sm border border-border-soft">
                                                            <table class="w-full text-center text-xs" aria-label="{{ $packItem->product_name.' · '.($packItem->attributes['color'] ?? __('Sizes')) }}">
                                                                <tbody>
                                                                    <tr class="bg-section"><th scope="row" class="px-2 py-2 text-start text-body">{{ __('Size') }}</th>@foreach($productColorContents as $content)<td class="px-2 py-2 font-semibold text-title">{{ $content->item->attributes['size'] ?? '—' }}</td>@endforeach</tr>
                                                                    <tr class="border-t border-border-soft"><th scope="row" class="px-2 py-2 text-start text-body">{{ __('Pieces') }}</th>@foreach($productColorContents as $content)<td class="px-2 py-2 tabular-nums text-title">{{ $content->quantity }}</td>@endforeach</tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @if($box->packed_at)
                                                <p class="border-t border-border-soft pt-3 text-xs font-semibold text-primary"><i class="ph ph-check-circle" aria-hidden="true"></i> {{ __('Packed') }} · {{ $box->packed_at->format('M j, Y') }}</p>
                                            @elseif(in_array($order->status, ['paid', 'processing']) && ($order->source !== 'pos' || $order->fulfillment_type === 'delivery'))
                                                @can('commerce.orders.update')
                                                    <form method="POST" action="{{ route('user.commerce.orders.boxes.packed', [$order, $box]) }}" class="border-t border-border-soft pt-3">
                                                        @csrf @method('PUT')
                                                        <x-forms.submit :label="__('Mark box packed')" />
                                                    </form>
                                                @endcan
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endif

                @if($unpackedItems->isNotEmpty())
                    <section class="section-card space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="heading-5 text-title">{{ __('Ordered items') }}</h2>
                            <span class="text-sm text-body">{{ $unpackedItems->sum(fn ($item) => $item->quantity - ($packedQuantities[$item->id] ?? 0)) }} {{ __('pieces') }}</span>
                        </div>
                        @foreach($individualProductGroups as $productItems)
                            <div class="space-y-3" data-individual-product-group>
                                <h3 class="border-b border-border-soft pb-3 text-sm font-semibold leading-snug text-title">{{ $productItems->first()->product_name }}</h3>
                                <div class="grid gap-3 md:grid-cols-2">
                                    @foreach($productItems as $item)
                                        @php $remainingQuantity = $item->quantity - ($packedQuantities[$item->id] ?? 0); @endphp
                                        <article class="min-w-0 space-y-4 rounded-md border border-border-soft bg-bg-elevated p-4" data-order-item="{{ $item->id }}">
                                            <div class="flex items-center gap-3">
                                                @include('commerce::user.partials.order-product-image', ['item' => $item])
                                                <div class="min-w-0 flex-1 space-y-1.5">
                                                    <h4 class="text-sm font-semibold text-title">{{ __('Color') }}: {{ $item->attributes['color'] ?? '—' }}</h4>
                                                    <p class="text-sm text-body">{{ __('Size') }}: <span class="font-medium text-title">{{ $item->attributes['size'] ?? '—' }}</span></p>
                                                    <p class="break-all text-xs text-body">{{ __('SKU') }}: {{ $item->sku ?: '—' }}</p>
                                                </div>
                                            </div>
                                            <dl class="grid grid-cols-3 gap-3 border-t border-border-soft pt-3 text-xs">
                                                <div><dt class="text-body">{{ __('Quantity') }}</dt><dd class="mt-1 text-sm font-semibold tabular-nums text-title" data-item-quantity>{{ $remainingQuantity }}</dd></div>
                                                <div><dt class="text-body">{{ __('Unit price') }}</dt><dd class="mt-1 break-words font-medium tabular-nums text-title">{{ $money($item->unit_price) }}</dd></div>
                                                <div><dt class="text-body">{{ __('Total') }}</dt><dd class="mt-1 break-words font-semibold tabular-nums text-title" data-item-total>{{ $money($remainingQuantity === $item->quantity ? $item->line_total : $remainingQuantity * (float) $item->unit_price) }}</dd></div>
                                            </dl>
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endif

                @if($order->source !== 'pos' || ($order->fulfillment_type === 'delivery' && ! in_array($order->status, ['completed', 'cancelled'])))
                <section class="section-card">
                    <h2 class="heading-5 text-title">{{ __('Update status and tracking') }}</h2>
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
                </section>
                @endif

                @if(in_array($order->status, ['shipped', 'completed'], true))
                <section class="section-card">
                    <h2 class="heading-5 text-title">{{ __('Shipment details') }}</h2>
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
                </section>
                @endif
            </div>

            <aside class="space-y-6">
                @if($canViewSummary)
                <section class="section-card space-y-2">
                    <h2 class="heading-5 text-title">{{ __('Order summary') }}</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-body">{{ __('Subtotal') }}</dt><dd>{{ $money($order->subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-body">{{ __('Discount') }}</dt><dd>− {{ $money($order->discount_amount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-body">{{ __('Shipping') }}</dt><dd>{{ $order->shipping_quote_required ? __('Not quoted') : $money($order->shipping_amount) }}</dd></div>
                        <div class="flex justify-between border-t border-border pt-2 font-semibold text-title"><dt>{{ __('Total') }}</dt><dd>{{ $order->total !== null ? $money($order->total) : '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-body">{{ __('Pieces') }}</dt><dd>{{ $orderedPieces }}</dd></div>
                    </dl>
                </section>
                @endif

                @php
                    $contactPerson = $order->contact;
                    $contactAddr = ($customerAddresses ?? collect())->firstWhere('is_default', true)?->address 
                        ?? ($contactPerson?->custom_fields['address'] ?? null) 
                        ?? ($order->customer_snapshot['address'] ?? null)
                        ?? $address 
                        ?? [];
                    $customerName = $contactPerson?->name ?? ($order->customer_snapshot['name'] ?? ($address['name'] ?? ''));
                    $customerPhone = $contactPerson?->phone ?? ($order->customer_snapshot['phone'] ?? ($address['phone'] ?? ''));
                    $customerEmail = $contactPerson?->email ?? ($order->customer_snapshot['email'] ?? ($address['email'] ?? ''));
                @endphp
                @if($canViewCustomer)
                <section class="section-card space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5 text-title">{{ __('Customer') }}</h2>
                        @can('commerce.orders.customer.edit')
                        <button type="button" class="btn btn-outline btn-sm rounded-sm text-xs py-1 px-2.5" data-modal-open="editCustomerModal">
                            <i class="ph ph-pencil-simple"></i> {{ __('Edit Contact') }}
                        </button>
                        @endcan
                    </div>
                    <div class="space-y-1">
                        <p class="font-medium text-title">{{ $customerName ?: '—' }}</p>
                        @if($customerPhone)<p class="text-body flex items-center gap-1.5"><i class="ph ph-phone text-muted"></i>{{ $customerPhone }}</p>@endif
                        @if($customerEmail)<p class="text-body flex items-center gap-1.5"><i class="ph ph-envelope-simple text-muted"></i>{{ $customerEmail }}</p>@endif
                    </div>
                    <div class="border-t border-border pt-2 space-y-0.5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted mb-1">{{ __('Contact Address') }}</p>
                        @if(!empty($contactAddr) && (filled($contactAddr['line1'] ?? null) || filled($contactAddr['city'] ?? null)))
                            @if(filled($contactAddr['line1'] ?? null))<p class="text-body">{{ $contactAddr['line1'] }}@if(filled($contactAddr['line2'] ?? null)), {{ $contactAddr['line2'] }}@endif</p>@endif
                            @php
                                $cityStateZip = collect([$contactAddr['city'] ?? null, $contactAddr['state'] ?? null, $contactAddr['postal_code'] ?? null])->filter()->implode(', ');
                            @endphp
                            @if(filled($cityStateZip))<p class="text-body">{{ $cityStateZip }}</p>@endif
                            @if(filled($contactAddr['country'] ?? null))<p class="text-body">{{ $contactAddr['country'] }}</p>@endif
                        @else
                            <p class="text-xs text-muted">{{ __('No contact address saved.') }}</p>
                        @endif
                    </div>
                    @if($order->conversation)
                        <div class="pt-1">
                            <a href="{{ route('user.inbox.index', ['conversation' => $order->conversation->id]) }}" class="text-primary hover:underline inline-flex items-center gap-1"><i class="ph ph-chat-circle"></i> {{ __('Open conversation') }}</a>
                        </div>
                    @endif
                </section>
                @endif

                @if($order->source !== 'pos' || $order->fulfillment_type === 'delivery')
                <section class="section-card space-y-1 text-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5 text-title">{{ __('Shipping address') }}</h2>
                        @if($isAddressLocked)<span class="text-xs text-body"><i class="ph ph-lock-simple"></i> {{ __('From customer') }}</span>@endif
                    </div>
                    @if($address)
                        @if($canViewCustomer)
                            <p class="text-title">{{ $address['name'] ?? '' }}</p>
                            <p class="text-body">{{ $address['phone'] ?? '' }}</p>
                        @endif
                        <p class="text-body">{{ $address['line1'] ?? '' }}@if(filled($address['line2'] ?? null)), {{ $address['line2'] }}@endif</p>
                        <p class="text-body">{{ collect([$address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null])->filter()->implode(', ') }}</p>
                        <p class="text-body">{{ $address['country'] ?? '' }}</p>
                    @else
                        <p class="text-body">{{ __('No address yet.') }}</p>
                    @endif
                    @if($order->delivery_method)<p class="pt-2 text-body">{{ __('Delivery') }}: {{ $order->delivery_method }}</p>@endif
                    @if(filled($order->delivery_notes))<p class="whitespace-pre-line pt-2 text-body">{{ __('Delivery notes') }}: {{ $order->delivery_notes }}</p>@endif
                    @if(filled($order->duties_disclosure))<p class="whitespace-pre-line pt-2 text-body">{{ __('Duties disclosure') }}: {{ $order->duties_disclosure }}</p>@endif
                </section>
                @endif

                @if($canViewPayments && $order->source !== 'pos')
                <section class="section-card space-y-2 text-sm">
                    <h2 class="heading-5 text-title">{{ __('Payment') }}</h2>
                    <p><span class="badge badge-soft">{{ str($order->payment_state)->replace('_', ' ')->title() }}</span></p>
                    @if($order->payment_evidence)
                        <p class="text-title">{{ $order->payment_evidence['method']['name'] ?? '' }}</p>
                        <p class="text-body">{{ __('Transaction ID') }}: {{ $order->payment_evidence['transaction_id'] ?? '—' }}</p>
                        @foreach($order->payment_evidence['fields'] ?? [] as $name => $value)<p class="text-body">{{ str($name)->replace('_', ' ')->title() }}: {{ $value }}</p>@endforeach
                        @if(filled($order->payment_evidence['note'] ?? null))<p class="text-body">{{ __('Note') }}: {{ $order->payment_evidence['note'] }}</p>@endif
                        @include('commerce::user.partials.payment-gallery')
                    @else
                        <p class="text-body">{{ __('No payment proof submitted yet.') }}</p>
                    @endif
                </section>
                @endif

                <section class="section-card space-y-2 text-sm">
                    <h2 class="heading-5 text-title">{{ __('Tracking') }}</h2>
                    <p class="text-body">{{ __('Tracking code') }}: <span class="text-title">{{ $order->tracking_code ?? '—' }}</span></p>
                    @if($order->tracking_number)
                        <p class="text-body">{{ __('Tracking number') }}: <span class="text-title">{{ $order->tracking_number }}</span></p>
                        @if($order->tracking_url)<p class="break-all text-body">{{ __('Carrier URL') }}: {{ $order->tracking_url }}</p>@endif
                        <ol class="space-y-1 pt-1">
                            @foreach($order->trackingTimeline() as $step)
                                <li class="text-body">{{ $step['label'] }} · {{ ucfirst($step['state']) }}</li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </aside>
        </div>
        @include('commerce::user.partials.order-product-gallery')

        @if($canViewCustomer)
        @can('commerce.orders.customer.edit')
        <x-ui.modal id="editCustomerModal" :title="__('Edit Customer Contact & Address')">
            <form method="POST" action="{{ route('user.commerce.orders.customer.update', $order) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label for="edit_customer_name" class="form-label">{{ __('Name') }} <span class="text-error">*</span></label>
                        <input id="edit_customer_name" type="text" name="name" class="form-input" value="{{ old('name', $customerName) }}" required>
                    </div>
                    <div>
                        <label for="edit_customer_phone" class="form-label">{{ __('Phone') }}</label>
                        <input id="edit_customer_phone" type="text" name="phone" class="form-input" value="{{ old('phone', $customerPhone) }}">
                    </div>
                    <div class="md:col-span-2">
                        <label for="edit_customer_email" class="form-label">{{ __('Email') }}</label>
                        <input id="edit_customer_email" type="email" name="email" class="form-input" value="{{ old('email', $customerEmail) }}">
                    </div>
                </div>
                <div class="border-t border-border pt-3">
                    <h4 class="font-medium text-title text-sm mb-3">{{ __('Contact Address') }}</h4>
                    <div class="space-y-3">
                        <div>
                            <label for="edit_customer_line1" class="form-label">{{ __('Address Line 1') }}</label>
                            <input id="edit_customer_line1" type="text" name="address_line1" class="form-input" value="{{ old('address_line1', $contactAddr['line1'] ?? '') }}" placeholder="{{ __('Street address, P.O. Box') }}">
                        </div>
                        <div>
                            <label for="edit_customer_line2" class="form-label">{{ __('Address Line 2') }}</label>
                            <input id="edit_customer_line2" type="text" name="address_line2" class="form-input" value="{{ old('address_line2', $contactAddr['line2'] ?? '') }}" placeholder="{{ __('Apartment, suite, unit, building, floor') }}">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="edit_customer_city" class="form-label">{{ __('City') }}</label>
                                <input id="edit_customer_city" type="text" name="city" class="form-input" value="{{ old('city', $contactAddr['city'] ?? '') }}">
                            </div>
                            <div>
                                <label for="edit_customer_state" class="form-label">{{ __('State / Province') }}</label>
                                <input id="edit_customer_state" type="text" name="state" class="form-input" value="{{ old('state', $contactAddr['state'] ?? '') }}">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="edit_customer_postal_code" class="form-label">{{ __('Postal / ZIP Code') }}</label>
                                <input id="edit_customer_postal_code" type="text" name="postal_code" class="form-input" value="{{ old('postal_code', $contactAddr['postal_code'] ?? '') }}">
                            </div>
                            <div>
                                <label for="edit_customer_country" class="form-label">{{ __('Country') }}</label>
                                <input id="edit_customer_country" type="text" name="country" class="form-input" value="{{ old('country', $contactAddr['country'] ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn btn-outline btn-sm" data-modal-close="editCustomerModal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Save changes') }}</button>
                </div>
            </form>
        </x-ui.modal>
        @endcan
        @endif
    </div>
</x-layouts.user>
