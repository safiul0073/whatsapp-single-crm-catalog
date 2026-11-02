@php
    $unpaidBalance = $canViewPayments && $order->total !== null && (float) $order->balanceDue() > 0 && ! in_array($order->status, ['cancelled'], true);
    $needsDetails = $order->status === 'needs_details' && ! $order->groups()->exists() && auth()->user()->can('commerce.orders.create');
@endphp
@if($needsDetails || $order->shipping_quote_required || $order->issues || $unpaidBalance)
    <section class="space-y-2 rounded-md border border-warning/30 bg-warning/10 p-4" aria-label="{{ __('Needs attention') }}">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-title"><i class="ph ph-warning"></i> {{ __('Needs attention') }}</h2>
        <ul class="space-y-1 text-sm text-body">
            @if($needsDetails)<li><a href="{{ route('user.commerce.orders.complete.form', $order) }}" class="font-semibold text-title underline">{{ __('Confirm retail selections or wholesale box counts') }} →</a></li>@endif
            @if($order->shipping_quote_required)<li>{{ __('Shipping quote required before the customer can pay.') }}</li>@endif
            @if($unpaidBalance)<li>{{ __('Balance due') }}: <span class="font-semibold text-title">{{ $money($order->balanceDue()) }}</span></li>@endif
            @foreach($order->issues ?? [] as $issue)<li>{{ $issue }}</li>@endforeach
        </ul>
    </section>
@endif
