<x-layouts.document :title="$order->invoice_number.' · '.__('Invoice')" :back="route('user.commerce.orders.show', $order)" format="a4">
    <x-slot:actions>
        <a class="btn btn-outline btn-sm" href="{{ route('user.commerce.orders.invoice', ['order' => $order, 'download' => 1]) }}"><i class="ph ph-download-simple"></i> {{ __('PDF') }}</a>
    </x-slot:actions>

    <header class="document-brand">
        <div class="flex min-w-0 items-start gap-3">
            @if($brand['logo'])<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" class="h-12 w-auto max-w-40 object-contain">@endif
            <div class="min-w-0 text-sm">
                <h1 class="text-lg font-semibold">{{ $brand['name'] }}</h1>
                @if($brand['address'])<p class="text-body">{{ $brand['address'] }}</p>@endif
                @if($brand['phone'] || $brand['email'])<p class="text-body">{{ collect([$brand['phone'], $brand['email']])->filter()->implode(' · ') }}</p>@endif
                @if($brand['tax_id'])<p class="text-body">{{ __('Tax ID') }}: {{ $brand['tax_id'] }}</p>@endif
            </div>
        </div>
        <div class="text-right text-sm">
            <p class="text-xl font-semibold tracking-wide">{{ __('INVOICE') }}</p>
            <p class="font-medium">{{ $order->invoice_number }}</p>
            <p class="text-body">{{ $order->created_at->format('M j, Y') }}</p>
            <p class="text-body">{{ __('Order') }} {{ $order->number }}</p>
        </div>
    </header>

    <section class="grid gap-4 text-sm sm:grid-cols-2">
        <div>
            <p class="document-label">{{ __('Bill to') }}</p>
            <p class="mt-1 font-medium">{{ $customer['name'] ?? __('Customer') }}</p>
            @if($customer['phone'])<p class="text-body">{{ $customer['phone'] }}</p>@endif
            @if($customer['email'])<p class="text-body">{{ $customer['email'] }}</p>@endif
        </div>
        <div>
            <p class="document-label">{{ __('Ship to') }}</p>
            @forelse($shipTo as $line)<p class="{{ $loop->first ? 'mt-1' : '' }} text-body">{{ $line }}</p>@empty<p class="mt-1 text-body">{{ __('Counter pickup') }}</p>@endforelse
        </div>
    </section>

    <table class="document-table">
        <thead><tr><th>{{ __('Item') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Unit price') }}</th><th class="text-right">{{ __('Total') }}</th></tr></thead>
        <tbody>
            @foreach($groups as $group)
                @php $first = $group['first']; @endphp
                <tr class="break-inside-avoid">
                    <td>
                        <div class="flex items-start gap-3">
                            <div class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-sm border border-border-soft bg-section print:size-12">
                                @if($group['image'])
                                    <img src="{{ $group['image']['url'] }}" alt="{{ $group['image']['label'] }}" class="h-full w-full object-contain p-0.5">
                                @else
                                    <i class="ph ph-image text-xl text-body" aria-hidden="true"></i>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="font-medium leading-snug">{{ $first->product_name }}</p>
                                @if(filled($first->attributes['color'] ?? null))<p class="text-xs text-body">{{ __('Color') }}: {{ $first->attributes['color'] }}</p>@endif
                                @if($group['has_sizes'])<p class="text-xs text-body">{{ $group['items']->map(fn ($item) => ($item->attributes['size'] ?? '—').' × '.$item->quantity)->implode(' · ') }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="text-right tabular-nums">{{ $group['pieces'] }}</td>
                    <td class="text-right tabular-nums">{{ $money($group['pieces'] ? $group['total'] / $group['pieces'] : 0) }}</td>
                    <td class="text-right font-medium tabular-nums">{{ $money($group['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="flex flex-col-reverse gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-2 text-sm">
            @if(filled($order->payment_instructions))
                <p class="document-label">{{ __('Payment instructions') }}</p>
                <p class="whitespace-pre-line text-body">{{ $order->payment_instructions }}</p>
            @endif
            @if($order->total !== null && (float) $order->balanceDue() <= 0)<span class="document-stamp">{{ __('Paid') }}</span>@endif
        </div>
        <dl class="w-full max-w-xs space-y-1.5 text-sm">
            <div class="flex justify-between text-body"><dt>{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ $money($order->subtotal) }}</dd></div>
            @if((float) $order->discount_amount > 0)<div class="flex justify-between text-body"><dt>{{ __('Discount') }}</dt><dd class="tabular-nums">− {{ $money($order->discount_amount) }}</dd></div>@endif
            @if($order->fulfillment_type === 'delivery' || (float) $order->shipping_amount > 0)<div class="flex justify-between text-body"><dt>{{ __('Shipping') }}</dt><dd class="tabular-nums">{{ $order->shipping_quote_required ? __('Awaiting quote') : $money($order->shipping_amount) }}</dd></div>@endif
            <div class="flex justify-between border-t border-border-default pt-2 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ $order->total === null ? __('Awaiting quote') : $money($order->total) }}</dd></div>
            <div class="flex justify-between text-body"><dt>{{ __('Paid') }}</dt><dd class="tabular-nums">{{ $money($order->paidAmount()) }}</dd></div>
            <div class="flex justify-between font-medium"><dt>{{ __('Balance due') }}</dt><dd class="tabular-nums">{{ $order->balanceDue() === null ? __('Awaiting quote') : $money($order->balanceDue()) }}</dd></div>
        </dl>
    </div>

    @if($order->payments->isNotEmpty())
        <section class="space-y-1 text-sm">
            <p class="document-label">{{ __('Payments') }}</p>
            @foreach($order->payments as $payment)
                <p class="flex justify-between gap-3 text-body"><span>{{ $payment->recorded_at->format('M j, Y') }} · {{ str($payment->method)->replace('_', ' ')->title() }}@if($payment->reference) · {{ $payment->reference }}@endif</span><span class="tabular-nums">{{ $money($payment->amount) }}</span></p>
            @endforeach
        </section>
    @endif

    @if($brand['footer'])<p class="whitespace-pre-line border-t border-border-default pt-3 text-xs text-body">{{ $brand['footer'] }}</p>@endif
    <p class="text-center text-xs text-body">{{ __('Thank you for your business.') }}</p>
</x-layouts.document>
