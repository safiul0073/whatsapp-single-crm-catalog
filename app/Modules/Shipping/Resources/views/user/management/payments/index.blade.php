<x-layouts.user :title="__('Agent Payments')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['tabs' => 'billing', 'heading' => __('Agent Payments'), 'intro' => __('What you owe each agent, what has been paid, and the receipts. Recording a payment here does not send any money.')])

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('Balances') }}</h2>
            @if($balances->isEmpty())
                <p class="mt-3 text-sm text-body">{{ __('No shipping agents yet.') }}</p>
            @else
                <div class="table-responsive mt-4">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Agent') }}</th><th class="text-right">{{ __('Owed') }}</th><th class="text-right">{{ __('Open invoices') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($balances as $row)
                                <tr>
                                    <td class="font-semibold text-title">{{ $row['agent']->name }}</td>
                                    <td @class(['text-right', 'font-semibold text-error' => ! str_starts_with($row['balance'], '-') && (float) $row['balance'] > 0])>{{ $row['agent']->currency }} {{ number_format((float) $row['balance'], 2) }}</td>
                                    <td class="text-right">{{ $row['open_invoices'] }}</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $row['agent']->payment_status])</td>
                                    <td class="text-right"><x-ui.button :href="route('user.shipping.payments.statement', $row['agent'])" variant="outline" size="sm">{{ __('Statement') }}</x-ui.button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if($canManage)
            <section class="section-card" id="record">
                <h2 class="heading-5 text-title">{{ __('Record a payment') }}</h2>
                <form method="POST" action="{{ route('user.shipping.payments.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 md:grid-cols-3">
                    @csrf
                    <div>
                        <label class="form-label" for="provider">{{ __('Paid to') }}</label>
                        <select id="provider" class="form-input" name="provider" required>
                            <option value="">{{ __('Select') }}</option>
                            <optgroup label="{{ __('Shipping agents') }}">@foreach($agents as $agent)<option value="agent:{{ $agent->id }}" @selected(old('provider', $defaults['provider']) === 'agent:'.$agent->id)>{{ $agent->name }} ({{ $agent->currency }})</option>@endforeach</optgroup>
                            <optgroup label="{{ __('Direct courier accounts') }}">@foreach($carrierAccounts as $account)<option value="account:{{ $account->id }}" @selected(old('provider', $defaults['provider']) === 'account:'.$account->id)>{{ $account->name }}</option>@endforeach</optgroup>
                        </select>
                        @error('provider')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="shipping_invoice_id">{{ __('For invoice (optional)') }}</label>
                        <select id="shipping_invoice_id" class="form-input" name="shipping_invoice_id">
                            <option value="">{{ __('General payment on account') }}</option>
                            @foreach($openInvoices as $open)<option value="{{ $open->id }}" @selected((int) old('shipping_invoice_id', $selectedInvoice) === $open->id)>{{ $open->invoice_number }} · {{ $open->providerLabel() }} · {{ $open->currency }} {{ $open->outstanding() }}</option>@endforeach
                        </select>
                        @error('shipping_invoice_id')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                    <div><label class="form-label" for="paid_on">{{ __('Paid on') }}</label><input id="paid_on" class="form-input" type="date" name="paid_on" required value="{{ old('paid_on', today()->toDateString()) }}">@error('paid_on')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label" for="amount">{{ __('Amount') }}</label><input id="amount" class="form-input" type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount', $defaults['amount']) }}">@error('amount')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label" for="currency">{{ __('Currency') }}</label><input id="currency" class="form-input" name="currency" maxlength="3" required value="{{ old('currency', $defaults['currency']) }}">@error('currency')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                    <div>
                        <label class="form-label" for="method">{{ __('Method') }}</label>
                        <select id="method" class="form-input" name="method">@foreach(\App\Modules\Shipping\Models\ShippingPayment::METHODS as $value => $label)<option value="{{ $value }}" @selected(old('method') === $value)>{{ __($label) }}</option>@endforeach</select>
                    </div>
                    <div><label class="form-label" for="reference">{{ __('Transaction reference') }}</label><input id="reference" class="form-input" name="reference" maxlength="120" value="{{ old('reference') }}"></div>
                    <div><label class="form-label" for="receipt">{{ __('Receipt (PDF or image)') }}</label><input id="receipt" class="form-input" type="file" name="receipt" accept=".pdf,.png,.jpg,.jpeg">@error('receipt')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label" for="payment_notes">{{ __('Notes') }}</label><input id="payment_notes" class="form-input" name="notes" maxlength="500" value="{{ old('notes') }}"></div>
                    <div class="md:col-span-3"><x-forms.submit :label="__('Record payment')" /></div>
                </form>
            </section>
        @endif

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('Payment history') }}</h2>
            @if($payments->isEmpty())
                <p class="mt-3 text-sm text-body">{{ __('No payments recorded yet.') }}</p>
            @else
                <div class="table-responsive mt-4">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Paid to') }}</th><th>{{ __('Invoice') }}</th><th>{{ __('Method') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Receipt') }}</th><th>{{ __('Recorded by') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($payments as $payment)
                                <tr>
                                    <td>{{ $payment->paid_on->format('d M Y') }}</td>
                                    <td>{{ $payment->providerLabel() }}</td>
                                    <td>@if($payment->invoice)<a class="text-primary" href="{{ route('user.shipping.invoices.show', $payment->invoice) }}">{{ $payment->invoice->invoice_number }}</a>@else{{ __('On account') }}@endif</td>
                                    <td>{{ __($payment->methodLabel()) }}@if($payment->reference)<span class="block text-xs text-body">{{ $payment->reference }}</span>@endif</td>
                                    <td class="text-right font-semibold text-title">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</td>
                                    <td>@if($payment->receipt_path)<a class="text-primary" href="{{ route('user.shipping.payments.receipt', $payment) }}">{{ __('Download') }}</a>@else—@endif</td>
                                    <td>{{ $payment->recorder?->name ?? '—' }}</td>
                                    <td class="text-right">@if($canManage)<form method="POST" action="{{ route('user.shipping.payments.destroy', $payment) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit" aria-label="{{ __('Remove payment') }}"><i class="ph ph-trash"></i></button></form>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $payments->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.user>
