<x-layouts.user :title="__('Upload Invoice')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['heading' => __('Upload Invoice'), 'back' => ['href' => route('user.shipping.invoices.index'), 'label' => __('Back to invoices')]])

        <form method="POST" action="{{ route('user.shipping.invoices.store') }}" enctype="multipart/form-data" class="section-card grid gap-4 md:grid-cols-3">
            @csrf
            <div>
                <label class="form-label" for="provider">{{ __('From') }}</label>
                <select id="provider" class="form-input" name="provider" required>
                    <option value="">{{ __('Select') }}</option>
                    <optgroup label="{{ __('Shipping agents') }}">@foreach($agents as $agent)<option value="agent:{{ $agent->id }}" @selected(old('provider') === 'agent:'.$agent->id)>{{ $agent->name }} ({{ $agent->currency }})</option>@endforeach</optgroup>
                    <optgroup label="{{ __('Direct courier accounts') }}">@foreach($carrierAccounts as $account)<option value="account:{{ $account->id }}" @selected(old('provider') === 'account:'.$account->id)>{{ $account->name }} ({{ $account->carrierLabel() }})</option>@endforeach</optgroup>
                    <optgroup label="{{ __('Direct courier') }}">@foreach(\App\Modules\Shipping\Models\CarrierAccount::CARRIERS as $code => $label)<option value="carrier:{{ $code }}" @selected(old('provider') === 'carrier:'.$code)>{{ $label }}</option>@endforeach</optgroup>
                </select>
                @error('provider')<p class="form-hint text-error">{{ $message }}</p>@enderror
            </div>
            <div><label class="form-label" for="invoice_number">{{ __('Invoice number') }}</label><input id="invoice_number" class="form-input" name="invoice_number" maxlength="80" required value="{{ old('invoice_number') }}">@error('invoice_number')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="stated_total">{{ __('Invoice total (as printed)') }}</label><input id="stated_total" class="form-input" type="number" step="0.01" min="0" name="stated_total" value="{{ old('stated_total') }}"><p class="form-hint">{{ __('Used to check that every line was captured.') }}</p>@error('stated_total')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="invoice_date">{{ __('Invoice date') }}</label><input id="invoice_date" class="form-input" type="date" name="invoice_date" value="{{ old('invoice_date') }}">@error('invoice_date')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="due_date">{{ __('Due date') }}</label><input id="due_date" class="form-input" type="date" name="due_date" value="{{ old('due_date') }}">@error('due_date')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="currency">{{ __('Currency') }}</label><input id="currency" class="form-input" name="currency" maxlength="3" required value="{{ old('currency', $storeCurrency) }}">@error('currency')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="exchange_rate">{{ __('Exchange rate to :currency', ['currency' => $storeCurrency]) }}</label><input id="exchange_rate" class="form-input" type="number" step="0.00000001" min="0" name="exchange_rate" value="{{ old('exchange_rate') }}" placeholder="{{ __('From the agent or courier account') }}"><p class="form-hint">{{ __('Only needed when the invoice is in another currency.') }}</p>@error('exchange_rate')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div class="md:col-span-2">
                <label class="form-label" for="file">{{ __('Invoice file (Excel, CSV or PDF)') }}</label>
                <input id="file" class="form-input" type="file" name="file" accept=".xlsx,.xls,.csv,.pdf">
                <p class="form-hint">{{ __('Excel and CSV files are read automatically. Expected columns: tracking number (or AWB / waybill), total, and optional freight, fuel, remote area, additional handling, oversize, overweight, address correction, customs, weight and date. A PDF is stored, and its lines are entered by hand.') }}</p>
                @error('file')<p class="form-hint text-error">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-3"><label class="form-label" for="notes">{{ __('Notes') }}</label><input id="notes" class="form-input" name="notes" maxlength="500" value="{{ old('notes') }}"></div>
            <div class="md:col-span-3"><x-forms.submit :label="__('Save and reconcile')" /></div>
        </form>
    </div>
</x-layouts.user>
