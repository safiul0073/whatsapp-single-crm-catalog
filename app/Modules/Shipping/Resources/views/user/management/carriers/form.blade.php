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
            <div class="md:col-span-3"><x-forms.submit :label="__('Save courier account')" /></div>
        </form>

        @if($account->exists)
            <form method="POST" action="{{ route('user.shipping.carriers.destroy', $account) }}">@csrf @method('DELETE')<button class="btn btn-ghost" type="submit">{{ __('Delete this courier account') }}</button></form>
        @endif
    </div>
</x-layouts.user>
