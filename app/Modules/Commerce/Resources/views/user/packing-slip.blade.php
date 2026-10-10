<x-layouts.user :title="__('Packing slip')">
    <main class="section-card space-y-5">
        <h1 class="heading-3">{{ __('Packing slip') }} · {{ $order->number }}</h1>
        <p>{{ $order->shipping_address['name'] ?? '' }} · {{ $order->shipping_address['line1'] ?? '' }} · {{ $order->shipping_address['city'] ?? '' }} · {{ $order->shipping_address['country'] ?? '' }}</p>
        @foreach($order->boxes as $box)
            <section class="rounded-md border border-border p-4"><h2 class="heading-5">{{ $box->label }} · {{ $box->packed_at ? __('Packed') : __('Awaiting packing') }}</h2><ul class="mt-3 space-y-2">@foreach($box->contents as $content)<li>{{ $content->item->product_name }} · {{ $content->item->sku }} · {{ $content->quantity }} {{ __('pieces') }}</li>@endforeach</ul></section>
        @endforeach
        <button type="button" id="printPackingSlip" class="btn btn-primary print:hidden">{{ __('Print') }}</button>
    </main>
    @push('scripts')<script>document.getElementById('printPackingSlip').addEventListener('click', function () { 'use strict'; window.print(); });</script>@endpush
</x-layouts.user>
