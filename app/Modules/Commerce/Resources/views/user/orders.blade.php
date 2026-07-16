<x-layouts.user :title="__('Commerce orders')">
    <div class="space-y-6">
        <header class="flex items-center justify-between gap-3">
            <div><h1 class="heading-3 text-title">{{ __('WhatsApp order requests') }}</h1><p class="text-sm text-body">{{ __('Review cart requests, prepare shipping quotes, and track fulfillment.') }}</p></div>
            <x-ui.button variant="outline" href="{{ route('user.commerce.products.index') }}">{{ __('Products') }}</x-ui.button>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => 'orders'])

        <section class="section-card overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead><tr class="border-b border-border text-body"><th class="p-3">{{ __('Order') }}</th><th class="p-3">{{ __('Buyer') }}</th><th class="p-3">{{ __('Status') }}</th><th class="p-3">{{ __('Total') }}</th><th class="p-3">{{ __('Received') }}</th></tr></thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-b border-border-soft"><td class="p-3"><a class="font-semibold text-primary" href="{{ route('user.commerce.orders.show', $order) }}">{{ $order->number }}</a></td><td class="p-3 text-title">{{ $order->contact?->name ?: $order->contact?->phone }}</td><td class="p-3"><span class="badge badge-soft">{{ str($order->status)->replace('_', ' ')->title() }}</span></td><td class="p-3 text-title">${{ number_format((float)($order->total ?? $order->subtotal), 2) }}</td><td class="p-3 text-body">{{ $order->created_at->diffForHumans() }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="p-10 text-center text-body">{{ __('No WhatsApp cart orders have arrived yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $orders->links() }}</div>
        </section>
    </div>
</x-layouts.user>
