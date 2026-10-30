<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->number }} · {{ __('Packing slip') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-section text-title print:bg-white">
    @php
        $address = $order->shipping_address ?? [];
        $customerName = $order->contact?->name ?? ($order->customer_snapshot['name'] ?? ($address['name'] ?? __('Customer')));
        $customerPhone = $order->contact?->phone ?? ($order->customer_snapshot['phone'] ?? ($address['phone'] ?? null));
        $addressLines = collect([
            $address['name'] ?? null,
            $address['line1'] ?? null,
            $address['line2'] ?? null,
            collect([$address['city'] ?? null, $address['state'] ?? null, $address['postal_code'] ?? null])->filter()->implode(', '),
            $address['country'] ?? null,
        ])->filter()->values();
        $boxes = $order->boxes->sortBy(fn ($box) => preg_match('/(?:BOX-|#)(\d+)/i', $box->label, $matches) ? (int) $matches[1] : (int) $box->id)->values();
        $totalPieces = $boxes->flatMap->contents->sum('quantity');
        $packedCount = $boxes->whereNotNull('packed_at')->count();
        $sizeOrder = array_flip(['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', 'XXXL', '4XL', '5XL']);
    @endphp
    <main class="mx-auto my-6 max-w-3xl space-y-4 rounded-lg border border-border-default bg-white p-6 print:my-0 print:max-w-none print:rounded-none print:border-0 print:p-0 print:text-xs">
        <div class="flex items-center justify-between gap-3 print:hidden">
            <a href="{{ route('user.commerce.orders.show', $order) }}" class="inline-flex items-center gap-1 text-sm text-body hover:text-title"><i class="ph ph-arrow-left"></i> {{ __('Back to order') }}</a>
            <button type="button" class="btn btn-primary btn-sm" id="printPackingSlip"><i class="ph ph-printer"></i> {{ __('Print') }}</button>
        </div>

        <header class="flex items-start justify-between gap-4 border-b border-border-default pb-3">
            <div>
                <h1 class="text-xl font-semibold">{{ __('Packing slip') }}</h1>
                <p class="text-sm text-body">{{ $order->created_at->format('M j, Y · g:i A') }} · {{ str($order->source)->replace('_', ' ')->title() }}</p>
            </div>
            <div class="text-right text-sm">
                <p class="font-medium">{{ $order->number }}</p>
                <p class="text-body">{{ str($order->status)->replace('_', ' ')->title() }} · {{ __('Payment') }}: {{ str($order->payment_state)->replace('_', ' ')->title() }}</p>
            </div>
        </header>

        <section class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <p class="text-xs uppercase tracking-wide text-body">{{ __('Customer') }}</p>
                <p class="mt-1 font-medium">{{ $customerName }}</p>
                @if($customerPhone)<p class="text-body">{{ $customerPhone }}</p>@endif
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-body">{{ __('Ship to') }}</p>
                @forelse($addressLines as $line)
                    <p class="{{ $loop->first ? 'mt-1' : '' }} text-body">{{ $line }}</p>
                @empty
                    <p class="mt-1 text-body">{{ __('Counter pickup') }}</p>
                @endforelse
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-body">{{ __('Packing') }}</p>
                <p class="mt-1 font-medium">{{ $boxes->count() }} {{ __('boxes') }} · {{ $totalPieces }} {{ __('pcs') }}</p>
                <p class="text-body">{{ $packedCount }} / {{ $boxes->count() }} {{ __('packed') }}</p>
            </div>
        </section>

        <section class="space-y-2.5">
            @forelse($boxes as $index => $box)
                @foreach($box->contents->groupBy(fn ($content) => $content->item->product_name.'|'.($content->item->attributes['color'] ?? '')) as $contents)
                    @php
                        $contents = $contents->sortBy(fn ($content) => $sizeOrder[strtoupper($content->item->attributes['size'] ?? '')] ?? 99)->values();
                        $item = $contents->first()->item;
                        $image = $item->productImages()[0] ?? null;
                    @endphp
                    <article class="flex gap-3 rounded-md border border-border-default p-3 break-inside-avoid print:rounded-none print:border-x-0 print:border-t-0 print:px-0">
                        <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-sm bg-section print:size-12">
                            @if($image)
                                <img src="{{ $image['url'] }}" alt="{{ $image['label'] }}" class="h-full w-full object-contain p-1">
                            @else
                                <i class="ph ph-image text-xl text-body" aria-hidden="true"></i>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium leading-snug">{{ $item->product_name }}</p>
                                    <p class="text-xs text-body">
                                        {{ __('Box') }} {{ $index + 1 }}/{{ $boxes->count() }} · {{ $box->label }}@if(filled($item->attributes['color'] ?? null)) · {{ __('Color') }}: {{ $item->attributes['color'] }}@endif · {{ $contents->sum('quantity') }} {{ __('pcs') }}
                                    </p>
                                </div>
                                <span class="shrink-0 text-xs {{ $box->packed_at ? 'text-primary' : 'text-body' }}">
                                    <i class="ph {{ $box->packed_at ? 'ph-check-square' : 'ph-square' }}" aria-hidden="true"></i>
                                    {{ $box->packed_at ? __('Packed') : __('To pack') }}
                                </span>
                            </div>
                            <table class="mt-2 w-full text-center text-xs" aria-label="{{ $box->label }} {{ __('contents') }}">
                                <tbody>
                                    <tr class="bg-section"><th scope="row" class="px-2 py-1 text-left font-normal text-body">{{ __('Size') }}</th>@foreach($contents as $content)<td class="px-2 py-1 font-medium">{{ $content->item->attributes['size'] ?? '—' }}</td>@endforeach</tr>
                                    <tr><th scope="row" class="px-2 py-1 text-left font-normal text-body">{{ __('Qty') }}</th>@foreach($contents as $content)<td class="px-2 py-1 tabular-nums">{{ $content->quantity }}</td>@endforeach</tr>
                                    <tr class="border-t border-border-default"><th scope="row" class="px-2 py-1 text-left font-normal text-body">{{ __('Check') }}</th>@foreach($contents as $content)<td class="px-2 py-1"><span class="inline-block size-3.5 rounded-sm border border-border-strong" aria-hidden="true"></span></td>@endforeach</tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                @endforeach
            @empty
                <p class="rounded-md bg-section p-4 text-center text-sm text-body">{{ __('No boxes have been created for this order yet.') }}</p>
            @endforelse
        </section>

        <footer class="grid grid-cols-2 gap-6 border-t border-border-default pt-6 text-xs text-body">
            <p class="border-t border-border-strong pt-1">{{ __('Packed by') }}</p>
            <p class="border-t border-border-strong pt-1">{{ __('Checked by') }}</p>
        </footer>
    </main>
    <script>document.getElementById('printPackingSlip').addEventListener('click', function () { 'use strict'; window.print(); });</script>
</body>
</html>
