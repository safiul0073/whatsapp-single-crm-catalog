<x-layouts.document :title="$order->number.' · '.__('Receipt')" format="receipt">
    @php
        $lineGroups = $groups->map(fn ($group) => $group['items']);
        $customerPhone = $customer['phone'];
    @endphp
    <x-slot:actions>
        <a class="btn btn-outline btn-sm" href="{{ route('user.commerce.orders.show', $order) }}">{{ __('View order') }}</a>
        @if($order->source === 'pos')<a class="btn btn-outline btn-sm" href="{{ route('user.commerce.pos.index') }}">{{ __('New sale') }}</a>@endif
    </x-slot:actions>

    <header class="document-brand">
        <div class="flex min-w-0 items-start gap-3">
            @if($brand['logo'])<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" class="h-10 w-auto max-w-28 object-contain">@endif
            <div class="min-w-0">
                <h1 class="text-xl font-semibold">{{ $brand['name'] }}</h1>
                @if($brand['address'])<p class="text-xs text-body">{{ $brand['address'] }}</p>@endif
                @if($brand['phone'])<p class="text-xs text-body">{{ $brand['phone'] }}</p>@endif
                @if($brand['tax_id'])<p class="text-xs text-body">{{ __('Tax ID') }}: {{ $brand['tax_id'] }}</p>@endif
                <p class="mt-1 text-sm text-body">{{ __('Sales receipt') }}</p>
            </div>
        </div>
        <div class="text-right text-sm">
            <p class="font-medium">{{ $order->number }}</p>
            <p class="text-xs text-body">{{ $order->invoice_number }}</p>
            <p class="text-body">{{ $order->created_at->format('M j, Y · g:i A') }}</p>
        </div>
    </header>

        <section class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-xs uppercase tracking-wide text-body">{{ __('Customer') }}</p>
                <p class="mt-1 font-medium">{{ ($customer['name'] ?? __('Walk-in customer')) }}</p>
                @if($customerPhone)<p class="text-body">{{ $customerPhone }}</p>@endif
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-body">{{ __('Fulfillment') }}</p>
                <p class="mt-1 font-medium">{{ str($order->fulfillment_type)->title() }}</p>
                <p class="text-body">{{ str($order->status)->replace('_', ' ')->title() }}</p>
            </div>
        </section>

        <section class="space-y-3">
            @foreach($lineGroups as $items)
                @php
                    $first = $items->first();
                    $pieces = $items->sum('quantity');
                    $lineTotal = $items->sum(fn ($item) => (float) $item->line_total);
                    $hasSizes = $items->contains(fn ($item) => filled($item->attributes['size'] ?? null));
                    $image = $first->productImages()[0] ?? null;
                @endphp
                <article class="flex gap-3 rounded-md border border-border-default p-3 print:rounded-none print:border-x-0 print:border-t-0 print:px-0">
                    <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-sm bg-section print:size-10">
                        @if($image)
                            <img src="{{ $image['url'] }}" alt="{{ $image['label'] }}" class="h-full w-full object-contain p-1">
                        @else
                            <i class="ph ph-image text-xl text-body" aria-hidden="true"></i>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium leading-snug">{{ $first->product_name }}</p>
                            <p class="text-xs text-body">
                                @if(filled($first->attributes['color'] ?? null)){{ __('Color') }}: {{ $first->attributes['color'] }} · @endif{{ $pieces }} {{ __('pcs') }} × {{ $money($pieces ? $lineTotal / $pieces : 0) }}
                            </p>
                        </div>
                        <p class="shrink-0 text-sm font-medium tabular-nums">{{ $money($lineTotal) }}</p>
                    </div>
                    @if($hasSizes)
                        <table class="mt-2 w-full text-center text-xs" aria-label="{{ $first->product_name }} {{ __('sizes') }}">
                            <tbody>
                                <tr class="bg-section"><th scope="row" class="px-2 py-1 text-left font-normal text-body">{{ __('Size') }}</th>@foreach($items as $item)<td class="px-2 py-1 font-medium">{{ $item->attributes['size'] ?? '—' }}</td>@endforeach</tr>
                                <tr><th scope="row" class="px-2 py-1 text-left font-normal text-body">{{ __('Qty') }}</th>@foreach($items as $item)<td class="px-2 py-1 tabular-nums">{{ $item->quantity }}</td>@endforeach</tr>
                            </tbody>
                        </table>
                    @endif
                    </div>
                </article>
            @endforeach
        </section>

        <dl class="space-y-1.5 border-t border-border-default pt-3 text-sm">
            <div class="flex justify-between text-body"><dt>{{ __('Subtotal') }}</dt><dd class="tabular-nums">{{ $money($order->subtotal) }}</dd></div>
            @if((float) $order->discount_amount > 0)
                <div class="flex justify-between text-body"><dt>{{ __('Discount') }}</dt><dd class="tabular-nums text-primary">− {{ $money($order->discount_amount) }}</dd></div>
            @endif
            @if($order->fulfillment_type === 'delivery')
                <div class="flex justify-between text-body"><dt>{{ __('Shipping') }}</dt><dd class="tabular-nums">{{ $order->shipping_quote_required ? __('Awaiting quote') : $money($order->shipping_amount) }}</dd></div>
            @endif
            <div class="flex justify-between border-t border-border-default pt-2 text-base font-semibold"><dt>{{ __('Total') }}</dt><dd class="tabular-nums">{{ $order->total === null ? __('Awaiting quote') : $money($order->total) }}</dd></div>
            <div class="flex justify-between text-body"><dt>{{ __('Paid') }}</dt><dd class="tabular-nums">{{ $money($order->paidAmount()) }}</dd></div>
            <div class="flex justify-between font-medium"><dt>{{ __('Balance due') }}</dt><dd class="tabular-nums">{{ $order->balanceDue() === null ? __('Awaiting quote') : $money($order->balanceDue()) }}</dd></div>
        </dl>

        <section class="space-y-2 border-t border-border-default pt-3">
            <h2 class="text-xs uppercase tracking-wide text-body">{{ __('Payments') }}</h2>
            @forelse($order->payments as $payment)
                <div class="flex items-start justify-between gap-3 text-sm">
                    <div>
                        <p class="font-medium">{{ str($payment->method)->replace('_', ' ')->title() }}</p>
                        <p class="text-xs text-body">{{ $payment->recorded_at->format('M j, g:i A') }} · {{ $payment->staff?->name ?? __('Staff') }}@if($payment->reference) · {{ $payment->reference }}@endif</p>
                        @if($payment->tendered_amount !== null)<p class="text-xs text-body">{{ __('Cash received') }} {{ $money($payment->tendered_amount) }} · {{ __('Change') }} {{ $money($payment->change_amount) }}</p>@endif
                    </div>
                    <p class="shrink-0 tabular-nums">{{ $money($payment->amount) }}</p>
                </div>
            @empty
                <p class="text-sm text-body">{{ __('No payments recorded.') }}</p>
            @endforelse
        </section>

        <p class="text-center text-xs text-body">{{ __('Thank you for your purchase!') }}</p>


    @if($brand['footer'])<p class="whitespace-pre-line text-center text-xs text-body">{{ $brand['footer'] }}</p>@endif
</x-layouts.document>
