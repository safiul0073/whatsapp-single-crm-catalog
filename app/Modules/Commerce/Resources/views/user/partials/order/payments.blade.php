<section id="payments" class="scroll-mt-24 space-y-6">
    @if($order->source === 'pos')
        @include('commerce::user.partials.pos-order')
    @endif
    @if($canViewPayments && $order->source !== 'pos')
    <section class="section-card space-y-3 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="heading-5 text-title">{{ __('Payment') }}</h2>
            <span class="badge badge-soft">{{ str($order->payment_state)->replace('_', ' ')->title() }}</span>
        </div>
        <dl class="grid gap-3 sm:grid-cols-3">
            <div><dt class="text-body">{{ __('Total') }}</dt><dd class="font-semibold text-title">{{ $order->total === null ? __('Awaiting quote') : $money($order->total) }}</dd></div>
            <div><dt class="text-body">{{ __('Paid') }}</dt><dd class="font-semibold text-title">{{ $money($order->paidAmount()) }}</dd></div>
            <div><dt class="text-body">{{ __('Balance due') }}</dt><dd class="font-semibold text-title">{{ $order->balanceDue() === null ? __('Awaiting quote') : $money($order->balanceDue()) }}</dd></div>
        </dl>
        @if($order->payment_evidence)
            <div class="space-y-1 border-t border-border-soft pt-3">
                <p class="text-title">{{ $order->payment_evidence['method']['name'] ?? '' }}</p>
                <p class="text-body">{{ __('Transaction ID') }}: {{ $order->payment_evidence['transaction_id'] ?? '—' }}</p>
                @foreach($order->payment_evidence['fields'] ?? [] as $name => $value)<p class="text-body">{{ str($name)->replace('_', ' ')->title() }}: {{ $value }}</p>@endforeach
                @if(filled($order->payment_evidence['note'] ?? null))<p class="text-body">{{ __('Note') }}: {{ $order->payment_evidence['note'] }}</p>@endif
                @include('commerce::user.partials.payment-gallery')
            </div>
        @else
            <p class="border-t border-border-soft pt-3 text-body">{{ __('No payment proof submitted yet.') }}</p>
        @endif
    </section>
    @endif
</section>
