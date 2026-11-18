@php
    $orderShipments = \App\Modules\Shipping\Models\Shipment::query()->with(['agent', 'carrierAccount'])->where('order_id', $order->id)->where('workspace_id', $order->workspace_id)->orderBy('id')->get();
    $canViewShippingCosts = auth()->user()->can('shipping.costs.view');
@endphp
<section id="shipments" class="section-card space-y-3 scroll-mt-24">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="heading-5 text-title">{{ __('Shipments') }}</h2>
        @can('shipping.shipments.manage')
            <form method="POST" action="{{ route('user.shipping.shipments.store') }}">@csrf<input type="hidden" name="order_id" value="{{ $order->id }}"><button class="btn btn-outline btn-sm" type="submit"><i class="ph ph-plus"></i> {{ $orderShipments->isEmpty() ? __('Create shipment') : __('Add another shipment') }}</button></form>
        @endcan
    </div>
    @if($orderShipments->isEmpty())
        <p class="text-sm text-body">{{ __('No shipping record yet for this order.') }}</p>
    @else
        <div class="table-responsive">
            <table class="table w-full text-sm whitespace-nowrap">
                <thead><tr><th>{{ __('Shipment') }}</th><th>{{ __('Courier') }}</th><th>{{ __('Tracking') }}</th><th>{{ __('Status') }}</th>@if($canViewShippingCosts)<th class="text-right">{{ __('Approved') }}</th><th class="text-right">{{ __('Actual') }}</th>@endif</tr></thead>
                <tbody>
                    @foreach($orderShipments as $orderShipment)
                        <tr>
                            <td><a class="font-semibold text-primary" href="{{ route('user.shipping.shipments.show', $orderShipment) }}">{{ $orderShipment->reference }}</a>@if($orderShipment->needs_review && $canViewShippingCosts) <span class="badge badge-error">{{ __('Review') }}</span>@endif</td>
                            <td>{{ ($canViewShippingCosts ? $orderShipment->providerLabel() : $orderShipment->carrierLabel()) ?: '—' }}</td>
                            <td>@if($orderShipment->tracking_url)<a class="text-primary" href="{{ $orderShipment->tracking_url }}" target="_blank" rel="noopener">{{ $orderShipment->tracking_number }}</a>@else{{ $orderShipment->tracking_number ?: '—' }}@endif</td>
                            <td>@include('shipping::user.management.partials.status-badge', ['status' => $orderShipment->status, 'label' => __($orderShipment->statusLabel())])</td>
                            @if($canViewShippingCosts)
                                <td class="text-right">{{ $orderShipment->approved_cost !== null ? $orderShipment->currency.' '.number_format((float) $orderShipment->approved_cost, 2) : '—' }}</td>
                                <td class="text-right">{{ $orderShipment->final_cost !== null ? $orderShipment->currency.' '.number_format((float) $orderShipment->final_cost, 2) : '—' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
