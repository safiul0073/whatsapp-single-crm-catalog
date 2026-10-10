<x-layouts.user :title="$order->number">
    @php
        $address = $order->shipping_address ?? [];
        $isAddressLocked = $order->hasCompleteShippingAddress();
        $precision = (new \App\Modules\Commerce\Models\StoreOrderSetting(['currency' => $order->currency]))->precision();
        $money = fn ($amount) => $order->currency.' '.number_format((float) $amount, $precision);
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
                <h1 class="heading-3 text-title">{{ $order->contact?->name ?? ($order->customer_snapshot['name'] ?? $address['name'] ?? __('Customer')) }}</h1>
                <p class="text-sm text-body">{{ __('Placed') }} {{ $order->created_at?->format('M j, Y · g:i A') }} · {{ __('Source') }}: {{ str($order->source ?? 'manual')->replace('_', ' ')->title() }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge badge-soft">{{ str($order->status)->replace('_', ' ')->title() }}</span>
                <span class="badge badge-soft">{{ __('Payment') }}: {{ str($order->payment_state)->replace('_', ' ')->title() }}</span>
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
            @can('commerce.manage')
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
                            @php($packProducts = $productBoxes->flatMap->contents->map->item->unique($productKey))
                            <div class="space-y-3" data-pack-product-group>
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border-soft pb-3">
                                    <h3 class="text-sm font-semibold leading-snug text-title">{{ $packProducts->count() === 1 ? $packProducts->first()->product_name : __('Mixed products') }}</h3>
                                    @if($packProductGroups->count() > 1)
                                        <span class="text-xs text-body">{{ $productBoxes->count() }} {{ __('packs') }} · {{ $productBoxes->flatMap->contents->sum('quantity') }} {{ __('pieces') }}</span>
                                    @endif
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    @foreach($productBoxes as $box)
                                        <article class="min-w-0 space-y-3 rounded-md border border-border-soft bg-bg-elevated p-4" data-pack-card data-pack-id="{{ $box->id }}">
                                            <div class="divide-y divide-border-soft">
                                                @foreach($box->contents->groupBy(fn ($content) => $productKey($content->item).'|'.($content->item->attributes['color'] ?? ''))->map(fn ($contents) => $contents->sortBy(fn ($content) => $sizeOrder[strtoupper($content->item->attributes['size'] ?? '')] ?? 99)) as $productColorContents)
                                                    @php($packItem = $productColorContents->first()->item)
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
                                                @can('commerce.manage')
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
                                        @php($remainingQuantity = $item->quantity - ($packedQuantities[$item->id] ?? 0))
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
                    <form method="POST" action="{{ route('user.commerce.orders.transition', $order) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf @method('PUT')
                        <div><label class="form-label" for="status">{{ __('Next status') }}</label><select id="status" class="form-input" name="status" required>@foreach($order->source === 'pos' ? array_merge([$order->status], ($order->fulfillment_type === 'delivery' ? match ($order->status) { 'processing' => ['packed'], 'packed' => ['shipped'], 'shipped' => ['completed'], default => [] } : []), (! $order->inventory_adjusted_at && $order->payments->isEmpty() && ! in_array($order->status, ['completed', 'cancelled']) ? ['cancelled'] : [])) : ['requested','needs_details','quoted','awaiting_payment','paid','processing','packed','shipped','completed','cancelled'] as $status)<option value="{{ $status }}" @selected($status === $order->status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></div>
                        <div><label class="form-label" for="carrier">{{ __('Carrier') }}</label><input id="carrier" class="form-input" name="carrier" maxlength="150"></div>
                        <div><label class="form-label" for="tracking_number">{{ __('Tracking number') }}</label><input id="tracking_number" class="form-input" name="tracking_number" maxlength="150" value="{{ old('tracking_number', $order->tracking_number) }}"></div>
                        <div><label class="form-label" for="tracking_url">{{ __('Carrier tracking URL') }}</label><input id="tracking_url" class="form-input" type="url" name="tracking_url" maxlength="2048" value="{{ old('tracking_url', $order->tracking_url) }}" placeholder="https://..."></div>
                        <div class="md:col-span-2"><x-forms.submit :label="__('Update status')" /></div>
                    </form>
                </section>
                @endif
            </div>

            <aside class="space-y-6">
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

                <section class="section-card space-y-2 text-sm">
                    <h2 class="heading-5 text-title">{{ __('Customer') }}</h2>
                    <p class="text-title">{{ $order->contact?->name ?? $order->customer_snapshot['name'] ?? $address['name'] ?? '—' }}</p>
                    <p class="text-body">{{ $order->contact?->phone ?? ($address['phone'] ?? '') }}</p>
                    @if($order->conversation)
                        <a href="{{ route('user.inbox.index', ['conversation' => $order->conversation->id]) }}" class="text-primary"><i class="ph ph-chat-circle"></i> {{ __('Open conversation') }}</a>
                    @endif
                </section>

                @if($order->source !== 'pos' || $order->fulfillment_type === 'delivery')
                <section class="section-card space-y-1 text-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5 text-title">{{ __('Shipping address') }}</h2>
                        @if($isAddressLocked)<span class="text-xs text-body"><i class="ph ph-lock-simple"></i> {{ __('From customer') }}</span>@endif
                    </div>
                    @if($address)
                        <p class="text-title">{{ $address['name'] ?? '' }}</p>
                        <p class="text-body">{{ $address['phone'] ?? '' }}</p>
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

                @if($order->source !== 'pos')
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
    </div>
</x-layouts.user>
