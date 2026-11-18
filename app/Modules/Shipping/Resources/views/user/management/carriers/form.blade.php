<x-layouts.user :title="$account->exists ? __('Edit Courier Account') : __('Add Courier Account')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['heading' => $account->exists ? __('Edit Courier Account') : __('Add Courier Account'), 'back' => ['href' => route('user.shipping.carriers.index'), 'label' => __('Back to courier accounts')]])

        <form method="POST" action="{{ $account->exists ? route('user.shipping.carriers.update', $account) : route('user.shipping.carriers.store') }}" class="section-card grid gap-4 md:grid-cols-3" autocomplete="off">
            @csrf
            @if($account->exists) @method('PUT') @endif
            <div><label class="form-label" for="name">{{ __('Account name') }}</label><input id="name" class="form-input" name="name" maxlength="80" required value="{{ old('name', $account->name) }}">@error('name')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div>
                <label class="form-label" for="carrier">{{ __('Courier') }}</label>
                <select id="carrier" class="form-input" name="carrier" required>@foreach(\App\Modules\Shipping\Models\CarrierAccount::CARRIERS as $code => $label)<option value="{{ $code }}" @selected(old('carrier', $account->carrier) === $code)>{{ $label }}</option>@endforeach</select>
            </div>
            <div><label class="form-label" for="account_number">{{ __('Account number') }}</label><input id="account_number" class="form-input" name="account_number" maxlength="60" value="{{ old('account_number', $account->account_number) }}"></div>
            <div>
                <label class="form-label" for="environment">{{ __('Environment') }}</label>
                <select id="environment" class="form-input" name="environment">@foreach(\App\Modules\Shipping\Models\CarrierAccount::ENVIRONMENTS as $value)<option value="{{ $value }}" @selected(old('environment', $account->environment) === $value)>{{ __(ucfirst($value)) }}</option>@endforeach</select>
            </div>
            <div><label class="form-label" for="dim_divisor">{{ __('Dimensional divisor') }}</label><input id="dim_divisor" class="form-input" type="number" min="1000" max="10000" name="dim_divisor" required value="{{ old('dim_divisor', $account->dim_divisor) }}">@error('dim_divisor')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="weight_rounding_step">{{ __('Round weight up to (kg)') }}</label><input id="weight_rounding_step" class="form-input" type="number" step="0.001" min="0.001" max="5" name="weight_rounding_step" required value="{{ old('weight_rounding_step', (float) $account->weight_rounding_step) }}"></div>

            <div><label class="form-label" for="exchange_rate">{{ __('Exchange rate to :currency', ['currency' => $storeCurrency]) }}</label><input id="exchange_rate" class="form-input" type="number" step="0.00000001" min="0" name="exchange_rate" required value="{{ old('exchange_rate', (float) $account->exchange_rate) }}"><p class="form-hint">{{ __('1 unit of the currency this courier bills in, in :currency. Keep 1 when it bills in :currency.', ['currency' => $storeCurrency]) }}</p>@error('exchange_rate')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>

            <div class="md:col-span-3">
                <h2 class="text-sm font-semibold text-title">{{ __('Ship-from address') }}</h2>
                <p class="form-hint">{{ __('Where parcels are collected. Needed for live rates and booking.') }}</p>
            </div>
            <div><label class="form-label" for="origin_name">{{ __('Shipper name') }}</label><input id="origin_name" class="form-input" name="origin_name" maxlength="120" value="{{ old('origin_name', $account->origin_name) }}"></div>
            <div class="md:col-span-2"><label class="form-label" for="origin_address_line1">{{ __('Street address') }}</label><input id="origin_address_line1" class="form-input" name="origin_address_line1" maxlength="255" value="{{ old('origin_address_line1', $account->origin_address_line1) }}"></div>
            <div><label class="form-label" for="origin_city">{{ __('City') }}</label><input id="origin_city" class="form-input" name="origin_city" maxlength="80" value="{{ old('origin_city', $account->origin_city) }}"></div>
            <div><label class="form-label" for="origin_state">{{ __('State / Province') }}</label><input id="origin_state" class="form-input" name="origin_state" maxlength="80" value="{{ old('origin_state', $account->origin_state) }}"></div>
            <div><label class="form-label" for="origin_postal_code">{{ __('Postal code') }}</label><input id="origin_postal_code" class="form-input" name="origin_postal_code" maxlength="20" value="{{ old('origin_postal_code', $account->origin_postal_code) }}"></div>
            <div>
                <label class="form-label" for="origin_country_code">{{ __('Country') }}</label>
                <select id="origin_country_code" class="form-input" name="origin_country_code">
                    <option value="">{{ __('Select country') }}</option>
                    @foreach($countries as $code => $name)<option value="{{ $code }}" @selected(old('origin_country_code', $account->origin_country_code) === $code)>{{ $name }}</option>@endforeach
                </select>
                @error('origin_country_code')<p class="form-hint text-error">{{ $message }}</p>@enderror
            </div>
            <div><label class="form-label" for="origin_phone">{{ __('Shipper phone') }}</label><input id="origin_phone" class="form-input" name="origin_phone" maxlength="30" value="{{ old('origin_phone', $account->origin_phone) }}" placeholder="+880 1700 000000"><p class="form-hint">{{ __('Needed for booking through the API.') }}</p>@error('origin_phone')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div><label class="form-label" for="opt_goods_description">{{ __('Goods description for customs') }}</label><input id="opt_goods_description" class="form-input" name="options[goods_description]" maxlength="100" value="{{ old('options.goods_description', $account->option('goods_description')) }}" placeholder="{{ __('Garments') }}"></div>

            <div class="md:col-span-3">
                <h2 class="text-sm font-semibold text-title">{{ __('Courier-specific options') }}</h2>
                <p class="form-hint">{{ __('FedEx uses the pickup type. Aramex uses the product group, product type and payment type printed on your Aramex account agreement — confirm the codes with Aramex.') }}</p>
            </div>
            <div>
                <label class="form-label" for="opt_pickup_type">{{ __('FedEx pickup type') }}</label>
                <select id="opt_pickup_type" class="form-input" name="options[pickup_type]">
                    <option value="">{{ __('Default (drop off)') }}</option>
                    @foreach(\App\Modules\Shipping\Services\Carriers\FedexRateClient::PICKUP_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('options.pickup_type', $account->option('pickup_type')) === $value)>{{ __($label) }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label" for="opt_product_group">{{ __('Aramex product group') }}</label><input id="opt_product_group" class="form-input" name="options[product_group]" maxlength="10" value="{{ old('options.product_group', $account->option('product_group')) }}" placeholder="EXP">@error('options.product_group')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="form-label" for="opt_product_type">{{ __('Aramex product type') }}</label><input id="opt_product_type" class="form-input" name="options[product_type]" maxlength="10" value="{{ old('options.product_type', $account->option('product_type')) }}" placeholder="PPX"></div>
                <div><label class="form-label" for="opt_payment_type">{{ __('Aramex payment type') }}</label><input id="opt_payment_type" class="form-input" name="options[payment_type]" maxlength="5" value="{{ old('options.payment_type', $account->option('payment_type')) }}" placeholder="P"></div>
            </div>

            <div class="md:col-span-3">
                <h2 class="text-sm font-semibold text-title">{{ __('API credentials') }}</h2>
                <p class="form-hint">{{ $account->hasCredentials() ? __('Credentials are saved. Leave a field blank to keep its current value.') : __('Optional for now. Fill in only the fields your courier issued.') }}</p>
            </div>
            @foreach(['client_id' => __('Client ID / API key'), 'client_secret' => __('Client secret'), 'username' => __('Username'), 'password' => __('Password'), 'account_pin' => __('Account PIN (Aramex)'), 'account_entity' => __('Account entity (Aramex)'), 'account_country_code' => __('Account country code')] as $field => $label)
                <div><label class="form-label" for="cred_{{ $field }}">{{ $label }}</label><input id="cred_{{ $field }}" class="form-input" type="password" name="credentials[{{ $field }}]" maxlength="500" autocomplete="new-password" placeholder="{{ filled($account->credentials[$field] ?? null) ? '••••••••' : '' }}"></div>
            @endforeach
            @if($account->hasCredentials())
                <label class="inline-label flex items-center gap-2 self-end text-sm text-body"><input type="checkbox" name="clear_credentials" value="1"> {{ __('Remove all saved credentials') }}</label>
            @endif

            <div class="md:col-span-3"><label class="form-label" for="notes">{{ __('Notes') }}</label><textarea id="notes" class="form-input" name="notes" rows="2" maxlength="500">{{ old('notes', $account->notes) }}</textarea></div>
            <label class="inline-label flex items-center gap-2 text-sm text-body"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active))> {{ __('Active') }}</label>
            <div class="md:col-span-2">
                <label class="inline-label flex items-center gap-2 text-sm text-body"><input type="hidden" name="booking_enabled" value="0"><input type="checkbox" name="booking_enabled" value="1" @checked(old('booking_enabled', $account->booking_enabled))> {{ __('Allow booking and labels through the API') }}</label>
                <p class="form-hint">{{ __('Off by default. When on, people with booking permission can create real, billable shipments on this account — always one at a time, after ticking a confirmation. Try it on a sandbox account first.') }}</p>
            </div>
            <div class="md:col-span-3"><x-forms.submit :label="__('Save courier account')" /></div>
        </form>

        @if($account->exists)
            <section class="section-card">
                <h2 class="heading-5 text-title">{{ __('Live rate connection') }}</h2>
                @if($account->liveRateGaps())
                    <p class="mt-2 text-sm text-body">{{ __('Still needed for live rates: :fields.', ['fields' => implode(', ', $account->liveRateGaps())]) }}</p>
                @else
                    <p class="mt-2 text-sm text-body">{{ __('Ready. Live rates are requested from this account whenever rates are requested for a shipment.') }}</p>
                @endif
                @if($account->last_error)<p class="mt-2 text-sm text-error">{{ __('Last error (:when): :message', ['when' => $account->last_error_at?->format('d M Y H:i'), 'message' => $account->last_error]) }}</p>@endif
                @if($account->last_success_at)<p class="mt-2 text-sm text-body">{{ __('Last successful rate request: :when', ['when' => $account->last_success_at->format('d M Y H:i')]) }}</p>@endif
                @if($account->booking_enabled && $account->bookingGaps())
                    <p class="mt-2 text-sm text-body">{{ __('Still needed for API booking: :fields.', ['fields' => implode(', ', $account->bookingGaps())]) }}</p>
                @endif
                <form method="POST" action="{{ route('user.shipping.carriers.test', $account) }}" class="mt-3">@csrf<button class="btn btn-outline" type="submit">{{ __('Test saved credentials') }}</button></form>
            </section>
            <form method="POST" action="{{ route('user.shipping.carriers.destroy', $account) }}">@csrf @method('DELETE')<button class="btn btn-ghost" type="submit">{{ __('Delete this courier account') }}</button></form>
        @endif
    </div>
</x-layouts.user>
