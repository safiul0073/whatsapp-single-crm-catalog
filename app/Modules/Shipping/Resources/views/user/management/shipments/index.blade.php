<x-layouts.user :title="__('Shipment Tracking')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        @include('shipping::user.management.partials.page-header', ['heading' => __('Shipment Tracking'), 'intro' => __('Every shipment, searchable by order, customer, courier, agent or tracking number.')])

        <form method="GET" action="{{ route('user.shipping.shipments.index') }}" class="section-card mb-6 grid gap-3 sm:grid-cols-[1fr_12rem_auto]">
            <div>
                <label class="form-label" for="search">{{ __('Search') }}</label>
                <input id="search" class="form-input" type="search" name="search" value="{{ $search }}" placeholder="{{ __('Order, customer, courier, agent or tracking number') }}">
            </div>
            <div>
                <label class="form-label" for="status">{{ __('Status') }}</label>
                <select id="status" class="form-input" name="status">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach(\App\Modules\Shipping\Models\Shipment::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <x-forms.submit :label="__('Search')" />
                @can('shipping.shipments.manage')<x-ui.button :href="route('user.shipping.shipments.create')" variant="outline"><i class="ph ph-plus"></i> {{ __('New shipment') }}</x-ui.button>@endcan
            </div>
        </form>

        <div class="section-card">
            @if($shipments->isEmpty())
                <p class="py-8 text-center text-body">{{ __('No shipments match this search.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead>
                            <tr>
                                <th>{{ __('Shipment') }}</th>
                                <th>{{ __('Order') }}</th>
                                <th>{{ __('Customer') }}</th>
                                <th>{{ __('Destination') }}</th>
                                <th>{{ __('Courier / agent') }}</th>
                                <th>{{ __('Tracking') }}</th>
                                <th>{{ __('Status') }}</th>
                                @if($canViewCosts)<th class="text-right">{{ __('Approved') }}</th><th class="text-right">{{ __('Actual') }}</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($shipments as $shipment)
                                <tr>
                                    <td><a class="font-semibold text-primary" href="{{ route('user.shipping.shipments.show', $shipment) }}">{{ $shipment->reference }}</a>@if($shipment->needs_review) <span class="badge badge-error">{{ __('Review') }}</span>@endif</td>
                                    <td>{{ $shipment->order?->number }}</td>
                                    <td>{{ $shipment->recipient_name ?: '—' }}</td>
                                    <td>{{ collect([$shipment->city, $shipment->postal_code, $shipment->country_code])->filter()->implode(', ') ?: '—' }}</td>
                                    <td>{{ ($canViewCosts ? $shipment->providerLabel() : $shipment->carrierLabel()) ?: '—' }}</td>
                                    <td>@if($shipment->tracking_url)<a class="text-primary" href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener">{{ $shipment->tracking_number }}</a>@else{{ $shipment->tracking_number ?: '—' }}@endif</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $shipment->status, 'label' => __($shipment->statusLabel())])</td>
                                    @if($canViewCosts)
                                        <td class="text-right">{{ $shipment->approved_cost !== null ? $shipment->currency.' '.number_format((float) $shipment->approved_cost, 2) : '—' }}</td>
                                        <td class="text-right">{{ $shipment->final_cost !== null ? $shipment->currency.' '.number_format((float) $shipment->final_cost, 2) : '—' }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $shipments->links() }}</div>
            @endif
        </div>
    </div>
</x-layouts.user>
