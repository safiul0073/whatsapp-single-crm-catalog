<x-layouts.user :title="__('Commerce orders')">
    <div class="space-y-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div><h1 class="heading-3 text-title">{{ __('Orders') }}</h1><p class="text-sm text-body">{{ __('Review cart requests, prepare shipping quotes, and track fulfillment.') }}</p></div>
            <div class="flex flex-wrap gap-3">@can('commerce.manage')<x-ui.button href="{{ route('user.commerce.pos.index') }}">{{ __('POS') }}</x-ui.button><x-ui.button variant="outline" href="{{ route('user.commerce.payment-services.index') }}">{{ __('Payment Services') }}</x-ui.button><x-ui.button variant="outline" href="{{ route('user.commerce.orders.settings') }}">{{ __('Order settings') }}</x-ui.button>@endcan<x-ui.button variant="outline" href="{{ route('user.commerce.products.index') }}">{{ __('Products') }}</x-ui.button></div>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => 'orders'])

        <form method="GET" action="{{ route('user.commerce.orders.index') }}" class="section-card flex flex-wrap items-end gap-4">
            @if(request()->filled('contact_id'))<input type="hidden" name="contact_id" value="{{ request('contact_id') }}">@endif
            <div><label for="order-source" class="form-label">{{ __('Source') }}</label><select id="order-source" name="source" class="form-input"><option value="">{{ __('All sources') }}</option>@foreach($sources as $source)<option value="{{ $source }}" @selected(request('source') === $source)>{{ str($source)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
            <div><label for="order-payment-state" class="form-label">{{ __('Payment') }}</label><select id="order-payment-state" name="payment_state" class="form-input"><option value="">{{ __('All payment states') }}</option>@foreach(['unpaid', 'partially_paid', 'paid', 'submitted'] as $state)<option value="{{ $state }}" @selected(request('payment_state') === $state)>{{ str($state)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-outline">{{ __('Filter') }}</button><a href="{{ route('user.commerce.orders.index') }}" class="text-sm text-primary">{{ __('Reset') }}</a>
        </form>
        <section class="section-card overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead><tr class="border-b border-border text-body"><th class="p-3">{{ __('Order') }}</th><th class="p-3">{{ __('Buyer') }}</th><th class="p-3">{{ __('Status') }}</th><th class="p-3">{{ __('Source / payment') }}</th><th class="p-3">{{ __('Total') }}</th><th class="p-3">{{ __('Balance due') }}</th><th class="p-3">{{ __('Received') }}</th></tr></thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr class="border-b border-border-soft"><td class="p-3"><a class="font-semibold text-primary" href="{{ route('user.commerce.orders.show', $order) }}">{{ $order->number }}</a></td><td class="p-3 text-title">{{ $order->contact?->name ?: ($order->customer_snapshot['name'] ?? $order->contact?->phone) }}</td><td class="p-3"><span class="badge badge-soft">{{ str($order->status)->replace('_', ' ')->title() }}</span></td><td class="p-3 text-body">{{ str($order->source)->replace('_', ' ')->title() }}<span class="block text-xs">{{ str($order->payment_state)->replace('_', ' ')->title() }}</span></td><td class="p-3 text-title">{{ $order->currency }} {{ $order->total ?? $order->subtotal }}</td><td class="p-3 text-title">{{ $order->source === 'pos' && $order->balanceDue() !== null ? $order->currency.' '.$order->balanceDue() : '—' }}</td><td class="p-3 text-body">{{ $order->created_at->diffForHumans() }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="p-10 text-center text-body">{{ __('No orders have arrived yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $orders->links() }}</div>
        </section>
    </div>
</x-layouts.user>
