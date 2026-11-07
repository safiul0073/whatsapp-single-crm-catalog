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
        $packedBoxCount = $order->boxes->whereNotNull('packed_at')->count();
        $canPack = in_array($order->status, ['paid', 'processing', 'packed'], true) && ($order->source !== 'pos' || $order->fulfillment_type === 'delivery') && auth()->user()->can('commerce.orders.update');
        $sizeOrder = array_flip(['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', 'XXXL', '4XL', '5XL']);
        $contactPerson = $order->contact;
        $contactAddr = ($customerAddresses ?? collect())->firstWhere('is_default', true)?->address
            ?? ($contactPerson?->custom_fields['address'] ?? null)
            ?? ($order->customer_snapshot['address'] ?? null)
            ?? $address
            ?? [];
        $customerName = $contactPerson?->name ?? ($order->customer_snapshot['name'] ?? ($address['name'] ?? ''));
        $customerPhone = $contactPerson?->phone ?? ($order->customer_snapshot['phone'] ?? ($address['phone'] ?? ''));
        $customerEmail = $contactPerson?->email ?? ($order->customer_snapshot['email'] ?? ($address['email'] ?? ''));
        $canUpdate = auth()->user()->can('commerce.orders.update');
        $holdExpiresAt = $order->status === 'awaiting_payment' && $order->payment_state !== 'paid' ? app(\App\Modules\Commerce\Services\OrderInventoryService::class)->holdExpiresAt($order) : null;
        $holdLapsed = $order->status === 'cancelled' && ! $order->paid_at && $order->events->contains('key', 'hold_expired');
        $showStatusForm = $order->source !== 'pos' || ($order->fulfillment_type === 'delivery' && ! in_array($order->status, ['completed', 'cancelled']));
        $canCancel = $canUpdate && ! in_array($order->status, ['completed', 'cancelled'], true) && ($order->source !== 'pos' || (! $order->inventory_adjusted_at && $order->payments->isEmpty()));
        $primaryAction = match (true) {
            $order->status === 'needs_details' && ! $order->groups()->exists() && auth()->user()->can('commerce.orders.create') => ['href' => route('user.commerce.orders.complete.form', $order), 'label' => __('Confirm details'), 'icon' => 'ph-check-square'],
            $canPack && $order->boxes->isNotEmpty() && $packedBoxCount < $order->boxes->count() => ['href' => '#packing', 'label' => __('Pack order'), 'icon' => 'ph-package'],
            $canUpdate && $showStatusForm && in_array($order->status, ['processing', 'packed', 'shipped'], true) => ['href' => '#shipping', 'label' => $order->status === 'shipped' ? __('Mark delivered') : __('Ship order'), 'icon' => 'ph-truck'],
            $order->source === 'pos' && $canViewPayments && $order->total !== null && (float) $order->balanceDue() > 0 && $order->status !== 'cancelled' => ['href' => '#payments', 'label' => __('Collect payment'), 'icon' => 'ph-money'],
            default => null,
        };
    @endphp

    <div class="order-compact" x-data="commerceProductGallery">
        @include('commerce::user.partials.order.header')
        @include('commerce::user.partials.order.attention')

        <div class="order-layout grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @if($order->boxes->isNotEmpty())@include('commerce::user.partials.order.packing')@endif
                @if($unpackedItems->isNotEmpty())@include('commerce::user.partials.order.items')@endif
                @if($showStatusForm || in_array($order->status, ['shipped', 'completed'], true))@include('commerce::user.partials.order.shipping')@endif
                @include('commerce::user.partials.order.payments')
                @include('commerce::user.partials.order.activity')
            </div>

            <aside class="space-y-6">
                @include('commerce::user.partials.order.summary')
                @if($canViewCustomer)@include('commerce::user.partials.order.customer')@endif
                @if($order->source !== 'pos' || $order->fulfillment_type === 'delivery')@include('commerce::user.partials.order.shipping-address')@endif
            </aside>
        </div>
        @include('commerce::user.partials.order-product-gallery')
        @include('commerce::user.partials.order.modal-customer')
        @include('commerce::user.partials.order.modal-hold')
    </div>
</x-layouts.user>
