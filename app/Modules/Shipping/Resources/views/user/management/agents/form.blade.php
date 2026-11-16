<x-layouts.user :title="$agent->exists ? __('Edit Shipping Agent') : __('Add Shipping Agent')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        @include('shipping::user.management.partials.page-header', [
            'heading' => $agent->exists ? __('Edit Shipping Agent') : __('Add Shipping Agent'),
            'back' => ['href' => $agent->exists ? route('user.shipping.agents.show', $agent) : route('user.shipping.agents.index'), 'label' => __('Back')],
        ])

        <form method="POST" action="{{ $agent->exists ? route('user.shipping.agents.update', $agent) : route('user.shipping.agents.store') }}" class="section-card grid gap-4 md:grid-cols-3">
            @csrf
            @if($agent->exists) @method('PUT') @endif
            <div><label class="form-label" for="name">{{ __('Agent name') }}</label><input id="name" class="form-input" name="name" maxlength="80" required value="{{ old('name', $agent->name) }}">@error('name')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="company_name">{{ __('Company name') }}</label><input id="company_name" class="form-input" name="company_name" maxlength="120" value="{{ old('company_name', $agent->company_name) }}"></div>
            <div>
                <label class="form-label" for="carrier">{{ __('Associated courier') }}</label>
                <select id="carrier" class="form-input" name="carrier">
                    <option value="">{{ __('Not specified') }}</option>
                    @foreach(\App\Modules\Shipping\Models\CarrierAccount::CARRIERS as $code => $label)<option value="{{ $code }}" @selected(old('carrier', $agent->carrier) === $code)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label" for="phone">{{ __('Contact number') }}</label><input id="phone" class="form-input" name="phone" maxlength="30" value="{{ old('phone', $agent->phone) }}"></div>
            <div><label class="form-label" for="whatsapp">{{ __('WhatsApp') }}</label><input id="whatsapp" class="form-input" name="whatsapp" maxlength="30" value="{{ old('whatsapp', $agent->whatsapp) }}"></div>
            <div>
                <label class="form-label" for="payment_status">{{ __('Payment status') }}</label>
                <select id="payment_status" class="form-input" name="payment_status">@foreach(\App\Modules\Shipping\Models\ShippingAgent::PAYMENT_STATUSES as $value)<option value="{{ $value }}" @selected(old('payment_status', $agent->payment_status) === $value)>{{ __(ucfirst($value)) }}</option>@endforeach</select>
            </div>
            <div><label class="form-label" for="currency">{{ __('Quoting currency') }}</label><input id="currency" class="form-input" name="currency" maxlength="3" required value="{{ old('currency', $agent->currency) }}">@error('currency')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="exchange_rate">{{ __('Exchange rate to :currency', ['currency' => $storeCurrency]) }}</label><input id="exchange_rate" class="form-input" type="number" step="0.00000001" min="0" name="exchange_rate" required value="{{ old('exchange_rate', (float) $agent->exchange_rate) }}"><p class="form-hint">{{ __('1 unit of the agent currency in :currency. Use 1 when the currencies match.', ['currency' => $storeCurrency]) }}</p>@error('exchange_rate')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="fuel_surcharge_percent">{{ __('Fuel surcharge (%)') }}</label><input id="fuel_surcharge_percent" class="form-input" type="number" step="0.001" min="0" max="100" name="fuel_surcharge_percent" required value="{{ old('fuel_surcharge_percent', (float) $agent->fuel_surcharge_percent) }}"></div>
            <div><label class="form-label" for="other_fee">{{ __('Other fixed fee per shipment') }}</label><input id="other_fee" class="form-input" type="number" step="0.01" min="0" name="other_fee" required value="{{ old('other_fee', (float) $agent->other_fee) }}"></div>
            <div><label class="form-label" for="other_fee_label">{{ __('Other fee name') }}</label><input id="other_fee_label" class="form-input" name="other_fee_label" maxlength="80" value="{{ old('other_fee_label', $agent->other_fee_label) }}" placeholder="{{ __('e.g. Handling fee') }}"></div>
            <div><label class="form-label" for="dim_divisor">{{ __('Dimensional divisor') }}</label><input id="dim_divisor" class="form-input" type="number" min="1000" max="10000" name="dim_divisor" required value="{{ old('dim_divisor', $agent->dim_divisor) }}"><p class="form-hint">{{ __('L × W × H (cm) ÷ this number = dimensional kg.') }}</p></div>
            <div><label class="form-label" for="weight_rounding_step">{{ __('Round weight up to (kg)') }}</label><input id="weight_rounding_step" class="form-input" type="number" step="0.001" min="0.001" max="5" name="weight_rounding_step" required value="{{ old('weight_rounding_step', (float) $agent->weight_rounding_step) }}"></div>
            <label class="inline-label flex items-center gap-2 self-end text-sm text-body"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $agent->is_active))> {{ __('Active — include in rate comparison') }}</label>
            <div class="md:col-span-3"><label class="form-label" for="notes">{{ __('Notes') }}</label><textarea id="notes" class="form-input" name="notes" rows="2" maxlength="500">{{ old('notes', $agent->notes) }}</textarea></div>
            <div class="md:col-span-3"><x-forms.submit :label="__('Save agent')" /></div>
        </form>
    </div>
</x-layouts.user>
