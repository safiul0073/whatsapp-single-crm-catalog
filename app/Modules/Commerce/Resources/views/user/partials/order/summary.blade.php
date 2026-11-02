@if($canViewSummary)
<section class="section-card space-y-3">
    <h2 class="heading-5 text-title">{{ __('Order summary') }}</h2>
    <dl class="space-y-2 text-sm">
        <div class="flex justify-between"><dt class="text-body">{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ $money($order->subtotal) }}</dd></div>
        <div class="flex justify-between"><dt class="text-body">{{ __('Discount') }}</dt><dd class="tabular-nums">− {{ $money($order->discount_amount) }}</dd></div>
        <div class="flex justify-between"><dt class="text-body">{{ __('Shipping') }}</dt><dd class="tabular-nums">{{ $order->shipping_quote_required ? __('Not quoted') : $money($order->shipping_amount) }}</dd></div>
        <div class="flex justify-between border-t border-border pt-2 text-base font-semibold text-title"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ $order->total !== null ? $money($order->total) : '—' }}</dd></div>
        @if($canViewPayments)
            <div class="flex justify-between"><dt class="text-body">{{ __('Paid') }}</dt><dd class="tabular-nums">{{ $money($order->paidAmount()) }}</dd></div>
            <div class="flex justify-between font-medium {{ $order->balanceDue() !== null && (float) $order->balanceDue() > 0 ? 'text-warning' : 'text-title' }}"><dt>{{ __('Balance due') }}</dt><dd class="tabular-nums">{{ $order->balanceDue() === null ? '—' : $money($order->balanceDue()) }}</dd></div>
        @endif
        <div class="flex justify-between"><dt class="text-body">{{ __('Pieces') }}</dt><dd>{{ $orderedPieces }}</dd></div>
    </dl>
</section>
@endif
