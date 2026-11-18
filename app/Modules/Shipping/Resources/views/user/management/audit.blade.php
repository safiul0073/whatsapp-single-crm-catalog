<x-layouts.user :title="__('Shipping Audit Log')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        @include('shipping::user.management.partials.page-header', ['heading' => __('Shipping Audit Log'), 'intro' => __('Rate changes, overrides, bookings and billing adjustments.')])
        <section class="section-card">
            @if($entries->isEmpty())
                <p class="py-8 text-center text-body">{{ __('Nothing recorded yet.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm">
                        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th><th>{{ __('Details') }}</th><th>{{ __('Shipment') }}</th></tr></thead>
                        <tbody>
                            @foreach($entries as $entry)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $entry->created_at?->format('d M Y H:i') }}</td>
                                    <td>{{ $entry->user?->name ?? __('System') }}</td>
                                    <td><span class="badge badge-neutral">{{ $entry->action }}</span></td>
                                    <td>{{ $entry->summary }}</td>
                                    <td>@if($entry->shipment)<a class="text-primary" href="{{ route('user.shipping.shipments.show', $entry->shipment) }}">{{ $entry->shipment->reference }}</a>@else — @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $entries->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.user>
