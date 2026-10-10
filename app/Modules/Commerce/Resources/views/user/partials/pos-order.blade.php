<section class="section-card space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="heading-5 text-title">{{ __('POS payments and balance') }}</h2><x-ui.button variant="outline" href="{{ route('user.commerce.pos.receipt', $order) }}">{{ __('Print sales receipt') }}</x-ui.button></div>
    <dl class="grid gap-4 text-sm sm:grid-cols-3"><div><dt class="text-body">{{ __('Total') }}</dt><dd class="font-semibold text-title">{{ $order->total === null ? __('Awaiting quote') : $money($order->total) }}</dd></div><div><dt class="text-body">{{ __('Paid') }}</dt><dd class="font-semibold text-title">{{ $money($order->paidAmount()) }}</dd></div><div><dt class="text-body">{{ __('Remaining balance') }}</dt><dd class="font-semibold text-title">{{ $order->balanceDue() === null ? __('Awaiting quote') : $money($order->balanceDue()) }}</dd></div></dl>
    @if($customerBalances)
        <div class="space-y-1 text-sm text-body">@foreach($customerBalances as $balance)<p>{{ __('Customer outstanding across POS orders') }}: {{ $balance['currency'] }} {{ $balance['amount'] }}</p>@endforeach<a href="{{ route('user.commerce.orders.index', ['source' => 'pos', 'contact_id' => $order->contact_id]) }}" class="text-primary">{{ __('View customer orders') }}</a></div>
    @endif
    @if($order->payments->isNotEmpty())
        <div class="overflow-x-auto"><table class="w-full min-w-[540px] text-left text-sm"><thead><tr class="border-b border-border text-body"><th class="p-2">{{ __('Recorded') }}</th><th class="p-2">{{ __('Method / reference') }}</th><th class="p-2">{{ __('Amount') }}</th><th class="p-2">{{ __('Staff') }}</th></tr></thead><tbody>@foreach($order->payments as $payment)<tr class="border-b border-border-soft text-title"><td class="p-2">{{ $payment->recorded_at->format('M j, Y · g:i A') }}</td><td class="p-2">{{ $payment->method }}<span class="block text-xs text-body">{{ $payment->reference }}</span>@if($payment->tendered_amount !== null)<span class="block text-xs text-body">{{ __('Cash received') }}: {{ $money($payment->tendered_amount) }} · {{ __('Change') }}: {{ $money($payment->change_amount) }}</span>@endif</td><td class="p-2">{{ $money($payment->amount) }}</td><td class="p-2">{{ $payment->staff?->name ?? __('Staff') }}</td></tr>@endforeach</tbody></table></div>
    @endif
    @can('commerce.manage')
        @if($order->total !== null && (float) $order->balanceDue() > 0 && $order->status !== 'cancelled')
            <form method="POST" action="{{ route('user.commerce.pos.payment', $order) }}" class="grid gap-4 rounded-md border border-border p-4 sm:grid-cols-2">
                @csrf
                <input type="hidden" name="submission_reference" value="{{ old('submission_reference', (string) \Illuminate\Support\Str::uuid()) }}">
                <input type="hidden" name="currency" value="{{ $order->currency }}">
                <div><label for="pos-order-amount" class="form-label">{{ __('Collect payment') }}</label><input id="pos-order-amount" class="form-input" type="number" step="{{ 1 / (10 ** $precision) }}" min="{{ 1 / (10 ** $precision) }}" max="{{ $order->balanceDue() }}" name="amount" value="{{ old('amount', $order->balanceDue()) }}" required></div>
                <div><label for="pos-order-method" class="form-label">{{ __('Payment method') }}</label><select id="pos-order-method" name="method" class="form-input">@foreach($paymentMethods as $method)<option value="{{ $method['id'] }}" @selected(old('method') === $method['id'])>{{ $method['name'] }}</option>@endforeach</select></div>
                <div><label for="pos-order-reference" class="form-label">{{ __('Reference (optional)') }}</label><input id="pos-order-reference" name="reference" class="form-input" maxlength="150" value="{{ old('reference') }}"></div>
                <div><label for="pos-order-tendered" class="form-label">{{ __('Cash received (cash only, optional)') }}</label><input id="pos-order-tendered" name="tendered_amount" type="number" step="{{ 1 / (10 ** $precision) }}" min="0" class="form-input" value="{{ old('tendered_amount') }}"></div>
                <div class="sm:col-span-2"><x-forms.submit :label="__('Record payment')" /></div>
            </form>
        @endif
        @if($order->fulfillment_type === 'pickup' && ! in_array($order->status, ['completed', 'cancelled']))
            <form method="POST" action="{{ route('user.commerce.pos.pickup', $order) }}" class="space-y-2">@csrf<p class="text-sm text-body">{{ __('Completing pickup hands over the goods and deducts stock. Any unpaid amount stays on the customer balance.') }}</p><x-forms.submit :label="__('Complete pickup')" /></form>
        @endif
        @if($order->fulfillment_type === 'pickup' && ! $order->inventory_adjusted_at && $order->payments->isEmpty() && ! in_array($order->status, ['completed', 'cancelled']))
            <form method="POST" action="{{ route('user.commerce.orders.transition', $order) }}">@csrf @method('PUT')<input type="hidden" name="status" value="cancelled"><button type="submit" class="btn btn-outline">{{ __('Cancel unpaid pickup') }}</button></form>
        @endif
    @endcan
</section>
