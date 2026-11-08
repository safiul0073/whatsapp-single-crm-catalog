<x-layouts.document :title="$order->invoice_number.' · '.__('Invoice')" :back="route('user.commerce.orders.show', $order)" format="a4">
    <x-slot:actions>
        <a class="btn btn-outline btn-sm" href="{{ route('user.commerce.orders.invoice', ['order' => $order, 'download' => 1]) }}"><i class="ph ph-download-simple"></i> {{ __('PDF') }}</a>
    </x-slot:actions>

    <header class="grid grid-cols-[1.2fr_1.4fr_1fr] items-start gap-3 border-b border-border-default pb-2 text-xs">
        <div class="flex min-w-0 items-start gap-2">
            @if($brand['logo'])<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" class="h-9 w-auto max-w-24 object-contain">@endif
            <div class="min-w-0 leading-snug">
                <h1 class="text-base font-semibold leading-tight">{{ $brand['name'] }}</h1>
                @if($brand['address'])<p class="text-body">{{ $brand['address'] }}</p>@endif
                @if($brand['email'])<p class="break-all text-body">{{ $brand['email'] }}</p>@endif
                @if($brand['tax_id'])<p class="text-body">{{ __('Tax ID') }}: {{ $brand['tax_id'] }}</p>@endif
            </div>
        </div>
        <div class="min-w-0 space-y-1 leading-snug">
            <p><span class="document-label">{{ __('Bill to') }}</span> <span class="font-medium">{{ $customer['name'] ?? __('Customer') }}</span><span class="block text-body">{{ collect([$customer['phone'], $customer['email']])->filter()->implode(' · ') }}</span></p>
            <p><span class="document-label">{{ __('Ship to') }}</span> <span class="text-body">{{ collect($shipTo)->filter()->implode(', ') ?: __('Counter pickup') }}</span></p>
        </div>
        <div class="text-right leading-snug">
            <p class="text-lg font-semibold leading-tight tracking-wide">{{ __('INVOICE') }}</p>
            <p class="font-medium">{{ $order->invoice_number }}</p>
            <p class="text-body">{{ $order->created_at->format('M j, Y') }}</p>
            <p class="text-body">{{ __('Order') }} {{ $order->number }}</p>
        </div>
    </header>

    <table class="document-table document-table-compact">
        <thead><tr><th>{{ __('Item') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Unit price') }}</th><th class="text-right">{{ __('Total') }}</th></tr></thead>
        @foreach(collect($groups)->groupBy(fn ($group) => $group['first']->product_name) as $productName => $productGroups)
            <tbody class="break-inside-avoid-page">
                @if($productGroups->count() > 1)
                    <tr><td colspan="4" class="!bg-section font-semibold">{{ $productName }}</td></tr>
                @endif
                @foreach($productGroups as $group)
                    @php $first = $group['first']; @endphp
                    <tr class="break-inside-avoid">
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-sm border border-border-soft bg-section print:size-[18mm]">
                                    @if($group['image'])
                                        <img src="{{ $group['image']['url'] }}" alt="{{ $group['image']['label'] }}" class="h-full w-full object-contain">
                                    @else
                                        <i class="ph ph-image text-lg text-body" aria-hidden="true"></i>
                                    @endif
                                </div>
                                <div class="min-w-0 space-y-0.5 leading-tight">
                                    @if($productGroups->count() === 1)<p class="font-medium">{{ $first->product_name }}</p>@endif
                                    @if(filled($first->attributes['color'] ?? null))<p class="font-medium">{{ $first->attributes['color'] }}</p>@endif
                                    @if($group['has_sizes'])<p class="text-[11px] text-body">{{ $group['items']->map(fn ($item) => ($item->attributes['size'] ?? '—').' × '.$item->quantity)->implode(' · ') }}</p>@endif
                                </div>
                            </div>
                        </td>
                        <td class="text-right tabular-nums">{{ $group['pieces'] }}</td>
                        <td class="text-right tabular-nums">{{ $money($group['pieces'] ? $group['total'] / $group['pieces'] : 0) }}</td>
                        <td class="text-right font-medium tabular-nums">{{ $money($group['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        @endforeach
    </table>

    <div class="grid items-start gap-3 text-xs sm:grid-cols-2">
        <div class="space-y-1.5">
            @if($order->total !== null && (float) $order->balanceDue() <= 0)<span class="document-stamp !px-2 !py-0.5 !text-xs">{{ __('Paid') }}</span>@endif
            @if(filled($order->payment_instructions))
                <div>
                    <p class="document-label">{{ __('Payment instructions') }}</p>
                    <p class="whitespace-pre-line text-body">{{ $order->payment_instructions }}</p>
                </div>
            @endif
            @if($order->payments->isNotEmpty())
                <div class="space-y-0.5">
                    <p class="document-label">{{ __('Payments') }}</p>
                    @foreach($order->payments as $payment)
                        <p class="flex justify-between gap-3 text-body"><span>{{ $payment->recorded_at->format('M j, Y') }} · {{ str($payment->method)->replace('_', ' ')->title() }}@if($payment->reference) · {{ $payment->reference }}@endif</span><span class="tabular-nums">{{ $money($payment->amount) }}</span></p>
                    @endforeach
                </div>
            @endif
        </div>
        <dl class="w-full space-y-0.5 sm:ml-auto sm:max-w-xs">
            <div class="flex justify-between text-body"><dt>{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ $money($order->subtotal) }}</dd></div>
            @if((float) $order->discount_amount > 0)<div class="flex justify-between text-body"><dt>{{ __('Discount') }}</dt><dd class="tabular-nums">− {{ $money($order->discount_amount) }}</dd></div>@endif
            @if($order->fulfillment_type === 'delivery' || (float) $order->shipping_amount > 0)<div class="flex justify-between text-body"><dt>{{ __('Shipping') }}</dt><dd class="tabular-nums">{{ $order->shipping_quote_required ? __('Awaiting quote') : $money($order->shipping_amount) }}</dd></div>@endif
            <div class="flex justify-between border-t border-border-default pt-1 text-sm font-semibold"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ $order->total === null ? __('Awaiting quote') : $money($order->total) }}</dd></div>
            <div class="flex justify-between text-body"><dt>{{ __('Paid') }}</dt><dd class="tabular-nums">{{ $money($order->paidAmount()) }}</dd></div>
            <div class="flex justify-between font-medium"><dt>{{ __('Balance due') }}</dt><dd class="tabular-nums">{{ $order->balanceDue() === null ? __('Awaiting quote') : $money($order->balanceDue()) }}</dd></div>
        </dl>
    </div>

    <div class="border-t border-border-default pt-1.5 text-center text-[11px] text-body">
        @if($brand['footer'])<p class="whitespace-pre-line">{{ $brand['footer'] }}</p>@endif
        <p>{{ __('Thank you for your business.') }}</p>
    </div>
</x-layouts.document>
