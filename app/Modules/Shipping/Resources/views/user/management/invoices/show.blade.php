<x-layouts.user :title="__('Invoice :number', ['number' => $invoice->invoice_number])">
    @php
        $reasons = \App\Modules\Shipping\Models\ShippingCharge::REASONS;
        $amount = fn ($value) => $invoice->currency.' '.number_format((float) $value, 2);
    @endphp
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', [
            'heading' => __('Invoice :number', ['number' => $invoice->invoice_number]),
            'intro' => $invoice->providerLabel(),
            'back' => ['href' => route('user.shipping.invoices.index'), 'label' => __('Back to invoices')],
        ])

        <section class="section-card">
            <div class="flex flex-wrap items-center gap-2">
                @include('shipping::user.management.partials.status-badge', ['status' => $invoice->status, 'label' => __(\App\Modules\Shipping\Models\ShippingInvoice::STATUSES[$invoice->status] ?? $invoice->status)])
                @include('shipping::user.management.partials.status-badge', ['status' => $invoice->payment_status, 'label' => __(\App\Modules\Shipping\Models\Shipment::PAYMENT_STATUSES[$invoice->payment_status] ?? $invoice->payment_status)])
                @if($invoice->isOverdue())<span class="badge badge-error">{{ __('Overdue') }}</span>@endif
                @if($invoice->file_path)<a class="text-sm text-primary" href="{{ route('user.shipping.invoices.file', $invoice) }}"><i class="ph ph-file-arrow-down"></i> {{ $invoice->original_name }}</a>@endif
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
                <div><dt class="text-body">{{ __('Invoice date') }}</dt><dd class="font-semibold text-title">{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-body">{{ __('Due date') }}</dt><dd class="font-semibold text-title">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-body">{{ __('Total on invoice') }}</dt><dd class="font-semibold text-title">{{ $invoice->stated_total !== null ? $amount($invoice->stated_total) : '—' }}</dd></div>
                <div><dt class="text-body">{{ __('Sum of lines') }}</dt><dd @class(['font-semibold', 'text-error' => $invoice->totalMismatch(), 'text-title' => ! $invoice->totalMismatch()])>{{ $amount($invoice->lines_total) }}</dd></div>
                <div><dt class="text-body">{{ __('Paid') }}</dt><dd class="font-semibold text-title">{{ $amount($invoice->paid_amount) }}</dd></div>
                <div><dt class="text-body">{{ __('Exchange rate to :currency', ['currency' => $storeCurrency]) }}</dt><dd class="font-semibold text-title">{{ (float) $invoice->exchange_rate }}</dd></div>
            </dl>
            @if($invoice->totalMismatch())
                <p class="mt-3 text-sm text-error"><i class="ph ph-warning"></i> {{ __('The lines do not add up to the invoice total. Add the missing lines before paying.') }}</p>
            @endif
            @if($invoice->notes)<p class="mt-3 text-sm text-body">{{ $invoice->notes }}</p>@endif
            @error('invoice')<p class="mt-3 form-hint text-error">{{ $message }}</p>@enderror
            @if($canManage)
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('user.shipping.invoices.reconcile', $invoice) }}">@csrf<x-forms.submit :label="__('Reconcile again')" /></form>
                    <x-ui.button :href="route('user.shipping.payments.index', ['invoice' => $invoice->id])" variant="outline"><i class="ph ph-money"></i> {{ __('Record a payment') }}</x-ui.button>
                    @if($invoice->payments->isEmpty())
                        <form method="POST" action="{{ route('user.shipping.invoices.destroy', $invoice) }}">@csrf @method('DELETE')<button class="btn btn-ghost" type="submit">{{ __('Delete invoice') }}</button></form>
                    @endif
                </div>
            @endif
        </section>

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('Lines') }}</h2>
            @if($invoice->lines->isEmpty())
                <p class="mt-3 text-sm text-body">{{ __('No lines yet.') }}</p>
            @else
                <div class="table-responsive mt-4">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Tracking / reference') }}</th><th>{{ __('Shipment') }}</th><th class="text-right">{{ __('Weight') }}</th><th class="text-right">{{ __('Base') }}</th><th>{{ __('Surcharges') }}</th><th class="text-right">{{ __('Total') }}</th><th class="text-right">{{ __('Approved cost') }}</th><th>{{ __('Match') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($invoice->lines as $line)
                                <tr>
                                    <td><span class="text-title">{{ $line->tracking_number ?? '—' }}</span>@if($line->reference)<span class="block text-xs text-body">{{ $line->reference }}</span>@endif @if($line->ship_date)<span class="block text-xs text-body">{{ $line->ship_date->format('d M Y') }}</span>@endif</td>
                                    <td>
                                        @if($line->shipment)
                                            <a class="text-primary" href="{{ route('user.shipping.shipments.show', $line->shipment) }}#billing">{{ $line->shipment->reference }}</a>
                                            @if($line->shipment->needs_review)<span class="badge badge-error">{{ __('Over approved') }}</span>@endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $line->billed_weight_kg !== null ? (float) $line->billed_weight_kg.' kg' : '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $line->base_amount, 2) }}</td>
                                    <td>{{ $line->charges->map(fn ($charge) => __($charge->reasonLabel()).' '.number_format((float) $charge->amount, 2))->implode(', ') ?: '—' }}</td>
                                    <td class="text-right font-semibold text-title">{{ number_format((float) $line->total_amount, 2) }}</td>
                                    <td class="text-right">{{ $line->shipment?->approved_cost !== null ? $line->shipment->currency.' '.number_format((float) $line->shipment->approved_cost, 2) : '—' }}</td>
                                    <td>
                                        @include('shipping::user.management.partials.status-badge', ['status' => $line->match_status, 'label' => __($line->matchLabel())])
                                        @if($line->note)<span class="block max-w-xs whitespace-normal text-xs text-body">{{ $line->note }}</span>@endif
                                    </td>
                                    <td class="space-y-1 text-right">
                                        @if($canManage)
                                            @if($line->match_status !== 'matched')
                                                <form method="POST" action="{{ route('user.shipping.invoices.lines.resolve', $line) }}" class="flex items-center justify-end gap-1">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="action" value="link">
                                                    <label class="sr-only" for="link_{{ $line->id }}">{{ __('Shipment') }}</label>
                                                    <select id="link_{{ $line->id }}" class="form-input" name="shipment_id" required>
                                                        <option value="">{{ __('Link to shipment') }}</option>
                                                        @foreach($linkable as $candidate)<option value="{{ $candidate->id }}" @selected($line->shipping_shipment_id === $candidate->id)>{{ $candidate->reference }}{{ $candidate->tracking_number ? ' · '.$candidate->tracking_number : '' }}</option>@endforeach
                                                    </select>
                                                    <button class="btn btn-outline btn-sm" type="submit">{{ __('Link') }}</button>
                                                </form>
                                            @endif
                                            @if($line->match_status !== 'ignored')
                                                <form method="POST" action="{{ route('user.shipping.invoices.lines.resolve', $line) }}">@csrf @method('PUT')<input type="hidden" name="action" value="ignore"><button class="btn btn-ghost btn-sm" type="submit">{{ __('Set aside') }}</button></form>
                                            @endif
                                            <form method="POST" action="{{ route('user.shipping.invoices.lines.destroy', $line) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit" aria-label="{{ __('Remove line') }}"><i class="ph ph-trash"></i></button></form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="form-hint">{{ __('A matched line sets the actual billed cost of its shipment. Anything above the approved cost is sent to the owner for review before the shipment can be closed.') }}</p>
            @endif
            @error('shipment_id')<p class="form-hint text-error">{{ $message }}</p>@enderror

            @if($canManage)
                <details class="mt-4 rounded-md border border-border-soft p-4" @if($errors->hasAny(['tracking_number', 'reference', 'base_amount'])) open @endif>
                    <summary class="cursor-pointer text-sm font-semibold text-title">{{ __('Add a line by hand') }}</summary>
                    <form method="POST" action="{{ route('user.shipping.invoices.lines.store', $invoice) }}" class="mt-4 grid gap-4 md:grid-cols-4">
                        @csrf
                        <div><label class="form-label" for="line_tracking">{{ __('Tracking number') }}</label><input id="line_tracking" class="form-input" name="tracking_number" maxlength="80" value="{{ old('tracking_number') }}">@error('tracking_number')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                        <div><label class="form-label" for="line_reference">{{ __('Shipment or order reference') }}</label><input id="line_reference" class="form-input" name="reference" maxlength="80" value="{{ old('reference') }}"></div>
                        <div><label class="form-label" for="line_date">{{ __('Ship date') }}</label><input id="line_date" class="form-input" type="date" name="ship_date" value="{{ old('ship_date') }}"></div>
                        <div><label class="form-label" for="line_weight">{{ __('Billed weight (kg)') }}</label><input id="line_weight" class="form-input" type="number" step="0.001" min="0" name="billed_weight_kg" value="{{ old('billed_weight_kg') }}"></div>
                        <div><label class="form-label" for="line_base">{{ __('Base / freight') }}</label><input id="line_base" class="form-input" type="number" step="0.01" min="0" name="base_amount" required value="{{ old('base_amount') }}">@error('base_amount')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                        @foreach($reasons as $reason => $label)
                            <div><label class="form-label" for="line_charge_{{ $reason }}">{{ __($label) }}</label><input id="line_charge_{{ $reason }}" class="form-input" type="number" step="0.01" min="0" name="charges[{{ $reason }}]" value="{{ old('charges.'.$reason) }}" placeholder="0.00"></div>
                        @endforeach
                        <div class="md:col-span-4"><x-forms.submit :label="__('Add line')" /></div>
                    </form>
                </details>
            @endif
        </section>

        @if($invoice->payments->isNotEmpty())
            <section class="section-card">
                <h2 class="heading-5 text-title">{{ __('Payments') }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach($invoice->payments as $payment)
                        <li class="flex flex-wrap justify-between gap-2">
                            <span class="text-title">{{ $payment->paid_on->format('d M Y') }} · {{ __($payment->methodLabel()) }}@if($payment->reference) · {{ $payment->reference }}@endif</span>
                            <span class="text-title">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}@if($payment->receipt_path) · <a class="text-primary" href="{{ route('user.shipping.payments.receipt', $payment) }}">{{ __('Receipt') }}</a>@endif</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.user>
