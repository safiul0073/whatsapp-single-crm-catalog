<x-layouts.user :title="__('Courier Invoices')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['tabs' => 'billing', 'heading' => __('Courier Invoices'), 'intro' => __('Upload courier and agent invoices. Each line is matched to its shipment by tracking number and checked against the approved cost.')])

        <form method="GET" action="{{ route('user.shipping.invoices.index') }}" class="section-card grid gap-3 sm:grid-cols-[1fr_12rem_auto]">
            <div>
                <label class="form-label" for="search">{{ __('Search') }}</label>
                <input id="search" class="form-input" type="search" name="search" value="{{ $search }}" placeholder="{{ __('Invoice number, agent or tracking number') }}">
            </div>
            <div>
                <label class="form-label" for="filter">{{ __('Show') }}</label>
                <select id="filter" class="form-input" name="filter">
                    <option value="">{{ __('All invoices') }}</option>
                    @foreach(\App\Modules\Shipping\Models\ShippingInvoice::STATUSES as $value => $label)<option value="{{ $value }}" @selected($filter === $value)>{{ __($label) }}</option>@endforeach
                    <option value="unpaid" @selected($filter === 'unpaid')>{{ __('Not fully paid') }}</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <x-forms.submit :label="__('Search')" />
                @if($canManage)<x-ui.button :href="route('user.shipping.invoices.create')" variant="outline"><i class="ph ph-upload-simple"></i> {{ __('Upload invoice') }}</x-ui.button>@endif
            </div>
        </form>

        <section class="section-card">
            @if($invoices->isEmpty())
                <p class="py-8 text-center text-body">{{ __('No invoices yet.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('From') }}</th><th>{{ __('Date') }}</th><th class="text-right">{{ __('Lines') }}</th><th class="text-right">{{ __('Amount') }}</th><th class="text-right">{{ __('Paid') }}</th><th>{{ __('Reconciliation') }}</th><th>{{ __('Payment') }}</th></tr></thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td><a class="font-semibold text-primary" href="{{ route('user.shipping.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a></td>
                                    <td>{{ $invoice->providerLabel() }}</td>
                                    <td>{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}@if($invoice->isOverdue())<span class="badge badge-error">{{ __('Overdue') }}</span>@endif</td>
                                    <td class="text-right">{{ $invoice->lines_count }}@if($invoice->open_lines_count > 0)<span class="block text-xs text-error">{{ __(':count open', ['count' => $invoice->open_lines_count]) }}</span>@endif</td>
                                    <td class="text-right">{{ $invoice->currency }} {{ number_format((float) $invoice->amountDue(), 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $invoice->status, 'label' => __(\App\Modules\Shipping\Models\ShippingInvoice::STATUSES[$invoice->status] ?? $invoice->status)])</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $invoice->payment_status, 'label' => __(\App\Modules\Shipping\Models\Shipment::PAYMENT_STATUSES[$invoice->payment_status] ?? $invoice->payment_status)])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $invoices->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.user>
