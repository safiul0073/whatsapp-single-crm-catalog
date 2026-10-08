<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->number }} · {{ __('Receipt') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-gray-900">
    @php($money = fn ($amount) => $order->currency.' '.number_format((float) $amount, $order->moneyPrecision()))
    <main class="mx-auto max-w-3xl space-y-6 p-6">
        <header class="space-y-1 border-b border-gray-300 pb-4"><h1 class="text-2xl font-bold">{{ $workspace->name }}</h1><p>{{ __('Sales receipt') }} · {{ $order->number }}</p><p>{{ $order->created_at->format('M j, Y · g:i A') }}</p></header>
        <section><h2 class="font-semibold">{{ $order->customer_snapshot['name'] ?? $order->contact?->name ?? __('Walk-in customer') }}</h2><p>{{ $order->customer_snapshot['phone'] ?? $order->contact?->phone }}</p><p>{{ __('Fulfillment') }}: {{ str($order->fulfillment_type)->title() }} · {{ str($order->status)->replace('_', ' ')->title() }}</p></section>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-gray-300"><th class="py-2">{{ __('Item') }}</th><th class="px-2 py-2">{{ __('Qty') }}</th><th class="px-2 py-2">{{ __('Unit price') }}</th><th class="py-2 text-right">{{ __('Total') }}</th></tr></thead><tbody>@foreach($order->items as $item)<tr class="border-b border-gray-200"><td class="py-3">{{ $item->product_name }}<span class="block text-xs">{{ $item->sku }}</span></td><td class="px-2 py-3">{{ $item->quantity }}</td><td class="px-2 py-3">{{ $money($item->unit_price) }}</td><td class="py-3 text-right">{{ $money($item->line_total) }}</td></tr>@endforeach</tbody></table></div>
        @foreach($order->groups->where('mode', 'wholesale') as $group)
            <p class="text-sm">{{ $group->product_name }} · {{ $group->color_name }}: {{ $group->box_count }} {{ __('packs') }} × {{ $group->pieces_per_box }} {{ __('pieces') }} ({{ collect($group->ratio)->map(fn ($qty, $size) => $size.': '.($qty * $group->multiplier))->implode(', ') }})</p>
        @endforeach
        <dl class="space-y-2"><div class="flex justify-between"><dt>{{ __('Subtotal') }}</dt><dd>{{ $money($order->subtotal) }}</dd></div><div class="flex justify-between"><dt>{{ __('Shipping') }}</dt><dd>{{ $order->shipping_quote_required ? __('Awaiting quote') : $money($order->shipping_amount) }}</dd></div><div class="flex justify-between font-bold"><dt>{{ __('Total') }}</dt><dd>{{ $order->total === null ? __('Awaiting quote') : $money($order->total) }}</dd></div><div class="flex justify-between"><dt>{{ __('Paid') }}</dt><dd>{{ $money($order->paidAmount()) }}</dd></div><div class="flex justify-between font-bold"><dt>{{ __('Remaining balance') }}</dt><dd>{{ $order->balanceDue() === null ? __('Awaiting quote') : $money($order->balanceDue()) }}</dd></div></dl>
        <section class="space-y-3"><h2 class="font-semibold">{{ __('Payment history') }}</h2>@forelse($order->payments as $payment)<div class="border-b border-gray-200 pb-2 text-sm"><p>{{ $payment->recorded_at->format('M j, Y · g:i A') }} · {{ $payment->method }} · {{ $money($payment->amount) }}</p>@if($payment->reference)<p>{{ __('Reference') }}: {{ $payment->reference }}</p>@endif @if($payment->tendered_amount !== null)<p>{{ __('Cash received') }}: {{ $money($payment->tendered_amount) }} · {{ __('Change') }}: {{ $money($payment->change_amount) }}</p>@endif<p>{{ __('Recorded by') }}: {{ $payment->staff?->name ?? __('Staff') }}</p></div>@empty<p class="text-sm">{{ __('No payments recorded.') }}</p>@endforelse</section>
        <div class="flex flex-wrap gap-3 print:hidden"><button type="button" class="btn btn-primary" id="printPosReceipt">{{ __('Print receipt') }}</button><a class="btn btn-outline" href="{{ route('user.commerce.orders.show', $order) }}">{{ __('Back to order') }}</a></div>
    </main>
    <script>document.getElementById('printPosReceipt').addEventListener('click', function () { 'use strict'; window.print(); });</script>
</body>
</html>
