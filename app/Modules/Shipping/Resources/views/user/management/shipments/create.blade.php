<x-layouts.user :title="__('New Shipment')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        @include('shipping::user.management.partials.page-header', ['heading' => __('New Shipment'), 'intro' => __('Pick the order to ship. One order can have several shipments, each with its own tracking number.'), 'back' => ['href' => route('user.shipping.shipments.index'), 'label' => __('Back to shipments')]])

        <form method="GET" action="{{ route('user.shipping.shipments.create') }}" class="section-card mb-6 flex flex-wrap items-end gap-3">
            <div class="grow">
                <label class="form-label" for="order">{{ __('Order number') }}</label>
                <input id="order" class="form-input" type="search" name="order" value="{{ $search }}" placeholder="ORD-00042">
            </div>
            <x-forms.submit :label="__('Find order')" />
        </form>

        <div class="section-card">
            @error('order_id')<p class="form-hint text-error">{{ $message }}</p>@enderror
            @if($orders->isEmpty())
                <p class="py-8 text-center text-body">{{ __('No orders found.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Destination') }}</th><th>{{ __('Pieces') }}</th><th>{{ __('Status') }}</th><th>{{ __('Shipments') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($orders as $order)
                                @php $address = $order->shipping_address ?? []; @endphp
                                <tr>
                                    <td class="font-semibold text-title">{{ $order->number }}</td>
                                    <td>{{ $address['name'] ?? ($order->customer_snapshot['name'] ?? '—') }}</td>
                                    <td>{{ collect([$address['city'] ?? null, $address['postal_code'] ?? null, $address['country'] ?? null])->filter()->implode(', ') ?: '—' }}</td>
                                    <td>{{ (int) $order->pieces }}</td>
                                    <td>{{ str($order->status)->replace('_', ' ')->title() }}</td>
                                    <td>{{ $shipmentCounts[$order->id] ?? 0 }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('user.shipping.shipments.store') }}">
                                            @csrf
                                            <input type="hidden" name="order_id" value="{{ $order->id }}">
                                            <x-forms.submit class="btn-sm" :label="($shipmentCounts[$order->id] ?? 0) > 0 ? __('Add another shipment') : __('Create shipment')" />
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.user>
