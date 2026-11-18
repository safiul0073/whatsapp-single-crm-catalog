<x-layouts.user :title="__('Courier Rate Comparison')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['tabs' => 'rates', 'heading' => __('Courier Rate Comparison'), 'intro' => __('Shipments waiting for a carrier or agent decision. Open one to compare every quotation line by line and approve.')])

        <section class="section-card">
            @if($shipments->isEmpty())
                <p class="py-8 text-center text-body">{{ __('Nothing is waiting for approval.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Shipment') }}</th><th>{{ __('Destination') }}</th><th>{{ __('Quotations') }}</th><th>{{ __('Lowest usable') }}</th><th>{{ __('Approved') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($shipments as $shipment)
                                @php $lowest = $shipment->quotes->where('status', '!=', 'rejected')->reject->isExpired()->sortBy(fn ($quote) => (float) $quote->total_store)->first(); @endphp
                                <tr>
                                    <td><a class="font-semibold text-primary" href="{{ route('user.shipping.shipments.show', $shipment) }}#quotes">{{ $shipment->reference }}</a></td>
                                    <td>{{ collect([$shipment->city, $shipment->postal_code, $shipment->country_code])->filter()->implode(', ') ?: '—' }}</td>
                                    <td>{{ $shipment->quotes_count }}</td>
                                    <td>@if($lowest){{ $lowest->providerLabel() }} — {{ $shipment->currency }} {{ number_format((float) $lowest->total_store, 2) }} @unless($lowest->isConfirmedPrice())<span class="badge badge-warning">{{ __('Unverified') }}</span>@endunless @else — @endif</td>
                                    <td>@if($shipment->approvedQuote){{ $shipment->approvedQuote->providerLabel() }} — {{ $shipment->currency }} {{ number_format((float) $shipment->approved_cost, 2) }}@else — @endif</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $shipment->status, 'label' => __($shipment->statusLabel())])</td>
                                    <td class="text-right"><x-ui.button :href="route('user.shipping.shipments.show', $shipment).'#quotes'" variant="outline" size="sm">{{ __('Compare') }}</x-ui.button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $shipments->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.user>
