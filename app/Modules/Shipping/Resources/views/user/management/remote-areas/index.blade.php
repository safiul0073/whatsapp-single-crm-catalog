<x-layouts.user :title="__('Remote Area Checker')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['heading' => __('Remote Area Checker'), 'intro' => __('Check a destination against what each courier and agent has confirmed. Without a confirmed entry the answer is "Unknown / Pending Verification".')])

        <form method="GET" action="{{ route('user.shipping.remote-areas.index') }}" class="section-card grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]">
            <div>
                <label class="form-label" for="country">{{ __('Country') }}</label>
                <select id="country" class="form-input" name="country" required>
                    <option value="">{{ __('Select country') }}</option>
                    @foreach($countries as $code => $name)<option value="{{ $code }}" @selected(($check['country'] ?? '') === $code)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div><label class="form-label" for="postal_code">{{ __('ZIP / Postal code') }}</label><input id="postal_code" class="form-input" name="postal_code" maxlength="20" value="{{ $check['postal_code'] ?? '' }}"></div>
            <div><label class="form-label" for="city">{{ __('City') }}</label><input id="city" class="form-input" name="city" maxlength="80" value="{{ $check['city'] ?? '' }}"></div>
            <div class="flex items-end"><x-forms.submit :label="__('Check')" /></div>
        </form>

        @if($results)
            <section class="section-card">
                <h2 class="heading-5 text-title">{{ __('Result by provider') }}</h2>
                <div class="table-responsive mt-3">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Provider') }}</th><th>{{ __('Status') }}</th>@if($canViewCosts)<th class="text-right">{{ __('Remote fee') }}</th>@endif</tr></thead>
                        <tbody>
                            @foreach($results as $row)
                                <tr>
                                    <td class="font-semibold text-title">{{ $row['provider'] }}</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $row['status'], 'label' => __($row['label'])])</td>
                                    @if($canViewCosts)<td class="text-right">{{ (float) $row['fee'] > 0 ? $row['currency'].' '.number_format((float) $row['fee'], 2) : '—' }}</td>@endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($canManage)
            <section class="section-card">
                <h2 class="heading-5 text-title">{{ __('Add a remote-area entry') }}</h2>
                <form method="POST" action="{{ route('user.shipping.remote-areas.store') }}" class="mt-4 grid gap-4 md:grid-cols-4">
                    @csrf
                    <div>
                        <label class="form-label" for="area_provider">{{ __('Provider') }}</label>
                        <select id="area_provider" class="form-input" name="provider" required>
                            <option value="">{{ __('Select') }}</option>
                            <optgroup label="{{ __('Direct courier') }}">@foreach($carriers as $code => $label)<option value="carrier:{{ $code }}" @selected(old('provider') === 'carrier:'.$code)>{{ $label }} {{ __('Direct') }}</option>@endforeach</optgroup>
                            <optgroup label="{{ __('Shipping agents') }}">@foreach($agents as $agent)<option value="agent:{{ $agent->id }}" @selected(old('provider') === 'agent:'.$agent->id)>{{ $agent->name }}</option>@endforeach</optgroup>
                        </select>
                        @error('provider')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="area_country">{{ __('Country') }}</label>
                        <select id="area_country" class="form-input" name="country_code" required>
                            <option value="">{{ __('Select country') }}</option>
                            @foreach($countries as $code => $name)<option value="{{ $code }}" @selected(old('country_code', $check['country'] ?? '') === $code)>{{ $name }}</option>@endforeach
                        </select>
                        @error('country_code')<p class="form-hint text-error">{{ $message }}</p>@enderror
                    </div>
                    <div><label class="form-label" for="postal_from">{{ __('Postal code from / prefix') }}</label><input id="postal_from" class="form-input" name="postal_from" maxlength="20" value="{{ old('postal_from') }}">@error('postal_from')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                    <div><label class="form-label" for="postal_to">{{ __('Postal code to (optional)') }}</label><input id="postal_to" class="form-input" name="postal_to" maxlength="20" value="{{ old('postal_to') }}"><p class="form-hint">{{ __('Blank = match codes starting with the prefix.') }}</p></div>
                    <div><label class="form-label" for="area_city">{{ __('City (optional)') }}</label><input id="area_city" class="form-input" name="city" maxlength="80" value="{{ old('city') }}"></div>
                    <div>
                        <label class="form-label" for="area_status">{{ __('Status') }}</label>
                        <select id="area_status" class="form-input" name="status" required>@foreach(\App\Modules\Shipping\Models\RemoteArea::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('status', 'remote') === $value)>{{ __($label) }}</option>@endforeach</select>
                    </div>
                    <div><label class="form-label" for="area_fee">{{ __('Fee') }}</label><input id="area_fee" class="form-input" type="number" step="0.01" min="0" name="fee" required value="{{ old('fee', 0) }}"></div>
                    <div><label class="form-label" for="area_currency">{{ __('Currency') }}</label><input id="area_currency" class="form-input" name="currency" maxlength="3" required value="{{ old('currency', $storeCurrency) }}"></div>
                    <div class="md:col-span-3"><label class="form-label" for="area_notes">{{ __('Notes') }}</label><input id="area_notes" class="form-input" name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="{{ __('e.g. confirmed by agent on WhatsApp, 14 Nov') }}"></div>
                    <label class="inline-label flex items-center gap-2 self-end text-sm text-body"><input type="checkbox" name="confirmed" value="1" @checked(old('confirmed'))> {{ __('Confirmed with this provider') }}</label>
                    <div class="md:col-span-4"><x-forms.submit :label="__('Save entry')" /></div>
                </form>
            </section>
        @endif

        <section class="section-card">
            <h2 class="heading-5 text-title">{{ __('Saved entries') }}</h2>
            @if($areas->isEmpty())
                <p class="py-6 text-center text-body">{{ __('No remote-area entries yet.') }}</p>
            @else
                <div class="table-responsive mt-3">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Provider') }}</th><th>{{ __('Country') }}</th><th>{{ __('Postal code') }}</th><th>{{ __('City') }}</th><th>{{ __('Status') }}</th>@if($canViewCosts)<th class="text-right">{{ __('Fee') }}</th>@endif<th>{{ __('Confirmed') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($areas as $area)
                                <tr>
                                    <td class="font-semibold text-title">{{ $area->agent?->name ?? (($carriers[$area->carrier] ?? strtoupper((string) $area->carrier)).' '.__('Direct')) }}</td>
                                    <td>{{ $area->country_code }}</td>
                                    <td>{{ $area->postal_from }}@if($area->postal_to) – {{ $area->postal_to }}@elseif($area->postal_from)…@endif</td>
                                    <td>{{ $area->city ?: '—' }}</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $area->status, 'label' => __(\App\Modules\Shipping\Models\RemoteArea::statusLabel($area->status))])</td>
                                    @if($canViewCosts)<td class="text-right">{{ $area->currency }} {{ number_format((float) $area->fee, 2) }}</td>@endif
                                    <td>@if($area->verified_at)<span class="badge badge-success">{{ $area->verified_at->format('d M Y') }}</span>@else<span class="badge badge-warning">{{ __('Unconfirmed — not used') }}</span>@endif</td>
                                    <td class="text-right">@if($canManage)<form method="POST" action="{{ route('user.shipping.remote-areas.destroy', $area) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">{{ __('Remove') }}</button></form>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $areas->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.user>
