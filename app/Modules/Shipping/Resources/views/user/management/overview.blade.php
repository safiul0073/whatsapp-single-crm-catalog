<x-layouts.user :title="__('Shipping Overview')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['heading' => __('Shipping Overview'), 'intro' => __('Where every shipment stands, and what needs attention.')])

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-7">
            @foreach([
                ['label' => __('Awaiting quotes'), 'value' => $counts['pending'], 'href' => route('user.shipping.shipments.index', ['status' => 'pending'])],
                ['label' => __('Approved, not booked'), 'value' => $counts['approved'], 'href' => route('user.shipping.shipments.index', ['status' => 'approved'])],
                ['label' => __('Booked / in transit'), 'value' => $counts['moving'], 'href' => route('user.shipping.shipments.index', ['status' => 'in_transit'])],
                ['label' => __('Delivered'), 'value' => $counts['delivered'], 'href' => route('user.shipping.shipments.index', ['status' => 'delivered'])],
            ] as $card)
                <a href="{{ $card['href'] }}" class="section-card block"><span class="text-sm text-body">{{ $card['label'] }}</span><span class="heading-4 block text-title">{{ $card['value'] }}</span></a>
            @endforeach
            <a href="{{ route('user.shipping.shipments.index', ['status' => 'exception']) }}" class="section-card block"><span class="text-sm text-body">{{ __('Delivery exceptions') }}</span><span @class(['heading-4 block', 'text-error' => $counts['exceptions'] > 0, 'text-title' => $counts['exceptions'] === 0])>{{ $counts['exceptions'] }}</span></a>
            @can('shipping.billing.view')
                <a href="{{ route('user.shipping.billing.index', ['filter' => 'review']) }}" class="section-card block"><span class="text-sm text-body">{{ __('Charges to review') }}</span><span @class(['heading-4 block', 'text-error' => $counts['review'] > 0, 'text-title' => $counts['review'] === 0])>{{ $counts['review'] }}</span></a>
                <a href="{{ route('user.shipping.billing.index', ['filter' => 'unpaid']) }}" class="section-card block"><span class="text-sm text-body">{{ __('Bills unpaid') }}</span><span class="heading-4 block text-title">{{ $counts['unpaid'] }}</span></a>
            @endcan
        </div>

        @can('shipping.billing.view')
            @if($needsReview->isNotEmpty())
                <section class="section-card border border-error">
                    <h2 class="heading-5 text-error"><i class="ph ph-warning"></i> {{ __('Unexpected charges') }}</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach($needsReview as $shipment)
                            <li class="flex flex-wrap justify-between gap-2"><a class="font-semibold text-primary" href="{{ route('user.shipping.shipments.show', $shipment) }}#billing">{{ $shipment->reference }}</a><span class="text-body">{{ $shipment->review_reason }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endcan

        <section class="section-card">
            <div class="flex items-center justify-between">
                <h2 class="heading-5 text-title">{{ __('Latest shipments') }}</h2>
                <a class="text-sm text-primary" href="{{ route('user.shipping.shipments.index') }}">{{ __('View all') }}</a>
            </div>
            @if($recent->isEmpty())
                <p class="py-8 text-center text-body">{{ __('No shipments yet. A shipping record is opened automatically when an order is placed.') }}</p>
            @else
                <div class="table-responsive mt-3">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Shipment') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Destination') }}</th><th>{{ __('Courier / agent') }}</th><th>{{ __('Tracking') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach($recent as $shipment)
                                <tr>
                                    <td><a class="font-semibold text-primary" href="{{ route('user.shipping.shipments.show', $shipment) }}">{{ $shipment->reference }}</a></td>
                                    <td>{{ $shipment->recipient_name ?: '—' }}</td>
                                    <td>{{ collect([$shipment->city, $shipment->country_code])->filter()->implode(', ') ?: '—' }}</td>
                                    <td>{{ ($canViewCosts ? $shipment->providerLabel() : $shipment->carrierLabel()) ?: '—' }}</td>
                                    <td>{{ $shipment->tracking_number ?: '—' }}</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $shipment->status, 'label' => __($shipment->statusLabel())])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts.user>
