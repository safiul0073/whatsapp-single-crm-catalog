<x-layouts.user :title="__('Invoice & Billing')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['tabs' => 'billing', 'heading' => __('Invoice & Billing'), 'intro' => __('Compare the estimate, the approved cost and what was actually billed. Open a shipment to enter its bill.')])

        <div class="flex flex-wrap gap-2">
            @foreach(['' => __('All approved'), 'unbilled' => __('Not billed yet'), 'review' => __('Needs review'), 'unpaid' => __('Unpaid')] as $value => $label)
                <a href="{{ route('user.shipping.billing.index', array_filter(['filter' => $value])) }}" @class(['btn btn-sm', 'btn-primary' => $filter === $value, 'btn-outline' => $filter !== $value])>{{ $label }}</a>
            @endforeach
        </div>

        <section class="section-card">
            @if($shipments->isEmpty())
                <p class="py-8 text-center text-body">{{ __('No shipments here.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Shipment') }}</th><th>{{ __('Courier / agent') }}</th><th class="text-right">{{ __('Estimate') }}</th><th class="text-right">{{ __('Approved') }}</th><th class="text-right">{{ __('Billed') }}</th><th class="text-right">{{ __('Variance') }}</th><th>{{ __('Surcharges') }}</th><th>{{ __('Payment') }}</th></tr></thead>
                        <tbody>
                            @foreach($shipments as $shipment)
                                @php $variance = $variances[$shipment->id] ?? null; $fmt = fn ($amount) => $amount === null ? '—' : number_format((float) $amount, 2); @endphp
                                <tr>
                                    <td><a class="font-semibold text-primary" href="{{ route('user.shipping.shipments.show', $shipment) }}#billing">{{ $shipment->reference }}</a>@if($shipment->needs_review) <span class="badge badge-error">{{ __('Review') }}</span>@endif<span class="block text-xs text-body">{{ $shipment->currency }}</span></td>
                                    <td>{{ $shipment->providerLabel() ?: '—' }}</td>
                                    <td class="text-right">{{ $fmt($shipment->estimated_cost) }}</td>
                                    <td class="text-right">{{ $fmt($shipment->approved_cost) }}</td>
                                    <td class="text-right">{{ $fmt($shipment->final_cost) }}</td>
                                    <td @class(['text-right', 'text-error font-semibold' => $variance !== null && (float) $variance > 0])>{{ $variance ?? '—' }}</td>
                                    <td>{{ $shipment->charges->map(fn ($charge) => __($charge->reasonLabel()))->implode(', ') ?: '—' }}</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $shipment->payment_status, 'label' => __(\App\Modules\Shipping\Models\Shipment::PAYMENT_STATUSES[$shipment->payment_status] ?? $shipment->payment_status)])</td>
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
