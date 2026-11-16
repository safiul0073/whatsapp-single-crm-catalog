<x-layouts.user :title="$shipment->reference">
    @php
        $money = fn ($amount, $currency = null) => $amount === null ? '—' : ($currency ?? $shipment->currency).' '.number_format((float) $amount, 2);
        $user = auth()->user();
        $canQuote = $user->can('shipping.quotes.manage');
        $canApprove = $user->can('shipping.quotes.approve');
        $canViewBilling = $user->can('shipping.billing.view');
        $canBill = $user->can('shipping.billing.manage');
        $reasons = \App\Modules\Shipping\Models\ShippingCharge::REASONS;
        $actualCharges = $shipment->charges->pluck('amount', 'reason');
    @endphp
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', [
            'heading' => $shipment->reference,
            'intro' => __('Order :number', ['number' => $shipment->order?->number]),
            'back' => ['href' => route('user.shipping.shipments.index'), 'label' => __('Back to shipments')],
        ])

        <div class="flex flex-wrap items-center gap-2 text-sm">
            @include('shipping::user.management.partials.status-badge', ['status' => $shipment->status, 'label' => __($shipment->statusLabel())])
            @can('commerce.orders.view')<a class="text-primary" href="{{ route('user.commerce.orders.show', $shipment->order_id) }}">{{ __('Open order') }}</a>@endcan
            @foreach($siblings as $sibling)
                <a class="badge badge-neutral" href="{{ route('user.shipping.shipments.show', $sibling) }}">{{ $sibling->reference }}</a>
            @endforeach
        </div>

        @if($shipment->needs_review && $canViewBilling)
            <div class="section-card border border-error">
                <h2 class="heading-5 text-error"><i class="ph ph-warning"></i> {{ __('Unexpected charge — owner review needed') }}</h2>
                <p class="mt-1 text-sm text-body">{{ $shipment->review_reason }}</p>
                @can('shipping.billing.approve')
                    <form method="POST" action="{{ route('user.shipping.billing.review', $shipment) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf @method('PUT')
                        <div class="grow"><label class="form-label" for="review_note">{{ __('Note (optional)') }}</label><input id="review_note" class="form-input" name="note" maxlength="200"></div>
                        <x-forms.submit :label="__('Approve this charge')" />
                    </form>
                @endcan
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Address --}}
                <section class="section-card" id="address">
                    <div class="flex items-center justify-between">
                        <h2 class="heading-5 text-title">{{ __('Destination address') }}</h2>
                        @if($shipment->hasCompleteAddress())<span class="badge badge-success">{{ __('Complete') }}</span>@else<span class="badge badge-warning">{{ __('Incomplete') }}</span>@endif
                    </div>
                    @if($canManage)
                        <form method="POST" action="{{ route('user.shipping.shipments.update', $shipment) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                            @csrf @method('PUT')
                            <div><label class="form-label" for="recipient_name">{{ __('Customer name') }}</label><input id="recipient_name" class="form-input" name="recipient_name" maxlength="120" required value="{{ old('recipient_name', $shipment->recipient_name) }}">@error('recipient_name')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                            <div><label class="form-label" for="phone">{{ __('Phone with country code') }}</label><input id="phone" class="form-input" name="phone" maxlength="30" required value="{{ old('phone', $shipment->phone) }}" placeholder="+1 202 555 0147"><p class="form-hint">{{ __('USA numbers without a code get +1 automatically.') }}</p>@error('phone')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                            <div>
                                <label class="form-label" for="country_code">{{ __('Country') }}</label>
                                <select id="country_code" class="form-input" name="country_code" required>
                                    <option value="">{{ __('Select country') }}</option>
                                    @foreach($countries as $code => $name)<option value="{{ $code }}" @selected(old('country_code', $shipment->country_code) === $code)>{{ $name }}</option>@endforeach
                                </select>
                                @error('country_code')<p class="form-hint text-error">{{ $message }}</p>@enderror
                            </div>
                            <div><label class="form-label" for="state">{{ __('State / Province') }}</label><input id="state" class="form-input" name="state" maxlength="80" value="{{ old('state', $shipment->state) }}"></div>
                            <div><label class="form-label" for="city">{{ __('City') }}</label><input id="city" class="form-input" name="city" maxlength="80" required value="{{ old('city', $shipment->city) }}">@error('city')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                            <div><label class="form-label" for="postal_code">{{ __('ZIP / Postal code') }}</label><input id="postal_code" class="form-input" name="postal_code" maxlength="20" required value="{{ old('postal_code', $shipment->postal_code) }}">@error('postal_code')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                            <div class="md:col-span-2"><label class="form-label" for="address_line1">{{ __('Street address') }}</label><input id="address_line1" class="form-input" name="address_line1" maxlength="255" required value="{{ old('address_line1', $shipment->address_line1) }}">@error('address_line1')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                            <div class="md:col-span-2"><label class="form-label" for="address_line2">{{ __('Apartment, suite (optional)') }}</label><input id="address_line2" class="form-input" name="address_line2" maxlength="255" value="{{ old('address_line2', $shipment->address_line2) }}"></div>
                            <div>
                                <label class="form-label" for="address_type">{{ __('Address type') }}</label>
                                <select id="address_type" class="form-input" name="address_type" required>
                                    <option value="">{{ __('Select type') }}</option>
                                    @foreach(\App\Modules\Shipping\Models\Shipment::ADDRESS_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('address_type', $shipment->address_type) === $value)>{{ __($label) }}</option>@endforeach
                                </select>
                                @error('address_type')<p class="form-hint text-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div><label class="form-label" for="total_sets">{{ __('Total sets') }}</label><input id="total_sets" class="form-input" type="number" min="0" name="total_sets" required value="{{ old('total_sets', $shipment->total_sets) }}"></div>
                                <div><label class="form-label" for="total_pieces">{{ __('Total pieces') }}</label><input id="total_pieces" class="form-input" type="number" min="0" name="total_pieces" required value="{{ old('total_pieces', $shipment->total_pieces) }}"></div>
                            </div>
                            <div class="md:col-span-2"><label class="form-label" for="notes">{{ __('Internal notes') }}</label><textarea id="notes" class="form-input" name="notes" rows="2" maxlength="1000">{{ old('notes', $shipment->notes) }}</textarea></div>
                            <div class="md:col-span-2"><x-forms.submit :label="__('Save address and details')" /></div>
                        </form>
                    @else
                        <div class="mt-3 space-y-1 text-sm text-body">
                            <p class="text-title">{{ $shipment->recipient_name }}</p>
                            <p>{{ $shipment->phone }}</p>
                            <p>{{ collect([$shipment->address_line1, $shipment->address_line2])->filter()->implode(', ') }}</p>
                            <p>{{ collect([$shipment->city, $shipment->state, $shipment->postal_code, $shipment->country_code])->filter()->implode(', ') }}</p>
                            <p>{{ __('Sets') }}: {{ $shipment->total_sets }} · {{ __('Pieces') }}: {{ $shipment->total_pieces }}</p>
                        </div>
                    @endif
                </section>

                {{-- Cartons --}}
                <section class="section-card" id="cartons">
                    <h2 class="heading-5 text-title">{{ __('Cartons and weight') }}</h2>
                    @if($canManage)
                        <form method="POST" action="{{ route('user.shipping.shipments.cartons', $shipment) }}" class="mt-4 space-y-3">
                            @csrf @method('PUT')
                            @include('shipping::user.management.partials.carton-rows', ['cartons' => $shipment->cartons->map->only(['quantity', 'actual_weight_kg', 'length_cm', 'width_cm', 'height_cm', 'packaging_type'])->all()])
                            <x-forms.submit :label="__('Save cartons')" />
                        </form>
                    @endif
                    @if($weight)
                        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div><dt class="text-body">{{ __('Packages') }}</dt><dd class="font-semibold text-title">{{ $weight['packages'] }}</dd></div>
                            <div><dt class="text-body">{{ __('Actual weight') }}</dt><dd class="font-semibold text-title">{{ $weight['actual_weight_kg'] }} kg</dd></div>
                            <div><dt class="text-body">{{ __('Dimensional (÷:divisor)', ['divisor' => $weight['divisor']]) }}</dt><dd class="font-semibold text-title">{{ $weight['dimensional_weight_kg'] }} kg</dd></div>
                            <div><dt class="text-body">{{ __('Billable weight') }}</dt><dd class="font-semibold text-title">{{ $weight['billable_weight_kg'] }} kg</dd></div>
                        </dl>
                        <p class="form-hint">{{ __('Each package is rated on its own and rounded up to :step kg. Agents and couriers with a different divisor are rated with their own rule when quoting.', ['step' => $weight['rounding_step']]) }}</p>
                        @if($weight['flags'])
                            <div class="mt-2 flex flex-wrap gap-2">@foreach($weight['flags'] as $flag)<span class="badge badge-warning">{{ __($flagLabels[$flag] ?? $flag) }}</span>@endforeach</div>
                        @endif
                    @else
                        <p class="mt-3 text-sm text-body">{{ __('No cartons yet. Add cartons to calculate billable weight and request rates.') }}</p>
                    @endif
                </section>

                {{-- Quotes --}}
                @if($canViewCosts)
                    <section class="section-card" id="quotes">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="heading-5 text-title">{{ __('Rate comparison') }}</h2>
                            @if($canQuote)
                                <form method="POST" action="{{ route('user.shipping.quotes.request', $shipment) }}">@csrf<x-forms.submit class="btn-sm" :label="__('Request rates from agent charts')" /></form>
                            @endif
                        </div>
                        @error('quote')<p class="form-hint text-error">{{ $message }}</p>@enderror
                        @error('cartons')<p class="form-hint text-error">{{ $message }}</p>@enderror
                        @error('status')<p class="form-hint text-error">{{ $message }}</p>@enderror
                        @if($quotes->isEmpty())
                            <p class="mt-3 text-sm text-body">{{ __('No quotations yet. Request rates from agent charts or enter a quotation received by WhatsApp or from a courier.') }}</p>
                        @else
                            <div class="table-responsive mt-4">
                                <table class="table w-full text-sm whitespace-nowrap">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Provider') }}</th><th class="text-right">{{ __('Base') }}</th><th class="text-right">{{ __('Fuel') }}</th><th class="text-right">{{ __('Remote') }}</th><th class="text-right">{{ __('Heavy / oversize') }}</th><th class="text-right">{{ __('Other') }}</th><th class="text-right">{{ __('Total') }}</th><th>{{ __('Transit') }}</th><th>{{ __('Source') }}</th><th>{{ __('Status') }}</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($quotes as $row)
                                            @php
                                                $quote = $row['quote'];
                                                $by = $row['charges_by_reason'];
                                                $heavy = (float) ($by['overweight'] ?? 0) + (float) ($by['oversize'] ?? 0) + (float) ($by['additional_handling'] ?? 0);
                                                $other = (float) ($by['other'] ?? 0) + (float) ($by['address_correction'] ?? 0) + (float) ($by['customs'] ?? 0);
                                            @endphp
                                            <tr @class(['bg-success/10' => $row['is_lowest']])>
                                                <td>
                                                    <span class="font-semibold text-title">{{ $quote->providerLabel() }}</span>
                                                    @if($row['is_lowest'])<span class="badge badge-success">{{ __('Lowest') }}</span>@endif
                                                    @if($quote->service)<span class="block text-xs text-body">{{ $quote->service }}</span>@endif
                                                    @if(in_array($quote->remote_status, ['remote', 'extended_remote'], true))<span class="badge badge-warning">{{ __(\App\Modules\Shipping\Models\RemoteArea::statusLabel($quote->remote_status)) }}</span>@elseif($quote->remote_status === 'unknown')<span class="block text-xs text-body">{{ __('Remote area: pending verification') }}</span>@endif
                                                </td>
                                                <td class="text-right">{{ number_format((float) $quote->base_rate, 2) }}</td>
                                                <td class="text-right">{{ isset($by['fuel']) ? number_format((float) $by['fuel'], 2) : '—' }}</td>
                                                <td class="text-right">{{ isset($by['remote_area']) ? number_format((float) $by['remote_area'], 2) : '—' }}</td>
                                                <td class="text-right">{{ $heavy > 0 ? number_format($heavy, 2) : ($quote->charges->whereIn('reason', ['overweight', 'oversize', 'additional_handling'])->isNotEmpty() ? __('To confirm') : '—') }}</td>
                                                <td class="text-right">{{ $other > 0 ? number_format($other, 2) : '—' }}</td>
                                                <td class="text-right font-semibold text-title">
                                                    {{ $money($quote->total, $quote->currency) }}
                                                    @if($quote->currency !== $shipment->currency)<span class="block text-xs font-normal text-body">≈ {{ $money($quote->total_store) }}</span>@endif
                                                </td>
                                                <td>{{ $quote->transit_days ?: '—' }}</td>
                                                <td><span class="block">{{ __($quote->sourceLabel()) }}</span><span class="text-xs text-body">{{ $quote->quoted_at?->format('d M Y H:i') }}</span></td>
                                                <td>
                                                    @include('shipping::user.management.partials.status-badge', ['status' => $quote->status])
                                                    @if($row['is_expired'])<span class="badge badge-error">{{ __('Expired') }}</span>@elseif($quote->valid_until)<span class="block text-xs text-body">{{ __('Valid to :date', ['date' => $quote->valid_until->format('d M')]) }}</span>@endif
                                                </td>
                                                <td class="space-y-1 text-right">
                                                    @if($quote->status !== 'approved')
                                                        @if($canQuote && $quote->status === 'unverified')
                                                            <form method="POST" action="{{ route('user.shipping.quotes.status', $quote) }}">@csrf @method('PUT')<input type="hidden" name="status" value="verified"><button class="btn btn-outline btn-sm" type="submit">{{ __('Mark verified') }}</button></form>
                                                        @endif
                                                        @if($canApprove && $quote->status !== 'rejected')
                                                            <form method="POST" action="{{ route('user.shipping.quotes.approve', [$shipment, $quote]) }}">
                                                                @csrf
                                                                @unless($row['is_confirmed'])<label class="inline-label mb-1 flex items-center justify-end gap-1 text-xs text-body"><input type="checkbox" name="override" value="1"> {{ __('Owner override') }}</label>@endunless
                                                                <button class="btn btn-primary btn-sm" type="submit">{{ __('Approve') }}</button>
                                                            </form>
                                                        @endif
                                                        @if($canQuote && $quote->status !== 'rejected')
                                                            <form method="POST" action="{{ route('user.shipping.quotes.status', $quote) }}">@csrf @method('PUT')<input type="hidden" name="status" value="rejected"><button class="btn btn-ghost btn-sm" type="submit">{{ __('Reject') }}</button></form>
                                                        @endif
                                                    @else
                                                        <span class="text-xs text-body">{{ __('Approved by :name', ['name' => $shipment->approver?->name ?? '—']) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="form-hint">{{ __('Unverified or expired quotations are estimates, not confirmed prices. The final carrier or agent is only set when someone with approval rights approves it.') }}</p>
                        @endif

                        @if($canQuote)
                            <details class="mt-4 rounded-md border border-border-soft p-4" @if($errors->hasAny(['provider', 'base_rate', 'currency', 'exchange_rate', 'valid_until'])) open @endif>
                                <summary class="cursor-pointer text-sm font-semibold text-title">{{ __('Add a quotation (WhatsApp or direct courier)') }}</summary>
                                <form method="POST" action="{{ route('user.shipping.quotes.store', $shipment) }}" class="mt-4 grid gap-4 md:grid-cols-3">
                                    @csrf
                                    <div>
                                        <label class="form-label" for="quote_provider">{{ __('Provider') }}</label>
                                        <select id="quote_provider" class="form-input" name="provider" required>
                                            <option value="">{{ __('Select') }}</option>
                                            <optgroup label="{{ __('Shipping agents') }}">@foreach($agents as $agent)<option value="agent:{{ $agent->id }}" @selected(old('provider') === 'agent:'.$agent->id)>{{ $agent->name }}</option>@endforeach</optgroup>
                                            <optgroup label="{{ __('Direct courier accounts') }}">@foreach($carrierAccounts as $account)<option value="account:{{ $account->id }}" @selected(old('provider') === 'account:'.$account->id)>{{ $account->name }} ({{ $account->carrierLabel() }})</option>@endforeach</optgroup>
                                            <optgroup label="{{ __('Direct courier') }}">@foreach(\App\Modules\Shipping\Models\CarrierAccount::CARRIERS as $code => $label)<option value="carrier:{{ $code }}" @selected(old('provider') === 'carrier:'.$code)>{{ $label }} {{ __('Direct') }}</option>@endforeach</optgroup>
                                        </select>
                                        @error('provider')<p class="form-hint text-error">{{ $message }}</p>@enderror
                                    </div>
                                    <div><label class="form-label" for="quote_service">{{ __('Service') }}</label><input id="quote_service" class="form-input" name="service" maxlength="80" value="{{ old('service') }}" placeholder="{{ __('e.g. Express Saver') }}"></div>
                                    <div><label class="form-label" for="quote_transit">{{ __('Estimated delivery') }}</label><input id="quote_transit" class="form-input" name="transit_days" maxlength="40" value="{{ old('transit_days') }}" placeholder="{{ __('e.g. 5-7 days') }}"></div>
                                    <div><label class="form-label" for="quote_base">{{ __('Base shipping rate') }}</label><input id="quote_base" class="form-input" type="number" step="0.01" min="0" name="base_rate" required value="{{ old('base_rate') }}">@error('base_rate')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                                    <div><label class="form-label" for="quote_currency">{{ __('Currency') }}</label><input id="quote_currency" class="form-input" name="currency" maxlength="3" required value="{{ old('currency', $storeCurrency) }}">@error('currency')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                                    <div><label class="form-label" for="quote_rate">{{ __('Exchange rate to :currency', ['currency' => $storeCurrency]) }}</label><input id="quote_rate" class="form-input" type="number" step="0.00000001" min="0" name="exchange_rate" value="{{ old('exchange_rate') }}" placeholder="1"><p class="form-hint">{{ __('Only needed when the quotation is in another currency.') }}</p>@error('exchange_rate')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                                    @foreach($reasons as $reason => $label)
                                        <div><label class="form-label" for="quote_charge_{{ $reason }}">{{ __($label) }}</label><input id="quote_charge_{{ $reason }}" class="form-input" type="number" step="0.01" min="0" name="charges[{{ $reason }}]" value="{{ old('charges.'.$reason) }}" placeholder="0.00"></div>
                                    @endforeach
                                    <div>
                                        <label class="form-label" for="quote_remote">{{ __('Remote area (per this provider)') }}</label>
                                        <select id="quote_remote" class="form-input" name="remote_status" required>
                                            <option value="unknown" @selected(old('remote_status', 'unknown') === 'unknown')>{{ __('Unknown / Pending Verification') }}</option>
                                            @foreach(\App\Modules\Shipping\Models\RemoteArea::STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('remote_status') === $value)>{{ __($label) }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div><label class="form-label" for="quote_weight">{{ __('Billable weight quoted (kg)') }}</label><input id="quote_weight" class="form-input" type="number" step="0.001" min="0" name="billable_weight_kg" value="{{ old('billable_weight_kg', $weight['billable_weight_kg'] ?? '') }}"></div>
                                    <div><label class="form-label" for="quote_valid">{{ __('Valid until') }}</label><input id="quote_valid" class="form-input" type="date" name="valid_until" value="{{ old('valid_until') }}"><p class="form-hint">{{ __('Blank = :days days from now.', ['days' => config('shipping_carriers.quote_validity_days')]) }}</p></div>
                                    <div class="md:col-span-3"><label class="form-label" for="quote_notes">{{ __('Notes') }}</label><input id="quote_notes" class="form-input" name="notes" maxlength="500" value="{{ old('notes') }}"></div>
                                    <div class="md:col-span-3"><x-forms.submit :label="__('Add quotation')" /></div>
                                </form>
                            </details>
                        @endif
                    </section>
                @endif

                {{-- Billing --}}
                @if($canViewBilling && $shipment->approved_quote_id)
                    <section class="section-card" id="billing">
                        <h2 class="heading-5 text-title">{{ __('Billing and reconciliation') }}</h2>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div><dt class="text-body">{{ __('Initial estimate') }}</dt><dd class="font-semibold text-title">{{ $money($shipment->estimated_cost) }}</dd></div>
                            <div><dt class="text-body">{{ __('Approved cost') }}</dt><dd class="font-semibold text-title">{{ $money($shipment->approved_cost) }}</dd></div>
                            <div><dt class="text-body">{{ __('Actual billed') }}</dt><dd class="font-semibold text-title">{{ $money($shipment->final_cost) }}</dd></div>
                            <div><dt class="text-body">{{ __('Variance') }}</dt><dd @class(['font-semibold', 'text-error' => $variance !== null && (float) $variance > 0, 'text-title' => $variance === null || (float) $variance <= 0])>{{ $variance === null ? '—' : $shipment->currency.' '.$variance }}</dd></div>
                        </dl>
                        @if($shipment->charges->isNotEmpty())
                            <ul class="mt-3 flex flex-wrap gap-2 text-sm">@foreach($shipment->charges as $charge)<li class="badge badge-neutral">{{ __($charge->reasonLabel()) }}: {{ number_format((float) $charge->amount, 2) }} {{ $shipment->billed_currency }}</li>@endforeach</ul>
                        @endif
                        @if($canBill)
                            <form method="POST" action="{{ route('user.shipping.billing.update', $shipment) }}" class="mt-4 grid gap-4 md:grid-cols-4">
                                @csrf @method('PUT')
                                <div><label class="form-label" for="billed_amount">{{ __('Total billed amount') }}</label><input id="billed_amount" class="form-input" type="number" step="0.01" min="0" name="billed_amount" required value="{{ old('billed_amount', $shipment->billed_amount) }}">@error('billed_amount')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                                <div><label class="form-label" for="billed_currency">{{ __('Currency') }}</label><input id="billed_currency" class="form-input" name="billed_currency" maxlength="3" required value="{{ old('billed_currency', $shipment->billed_currency ?? $shipment->approvedQuote?->currency ?? $shipment->currency) }}"></div>
                                <div><label class="form-label" for="billed_exchange_rate">{{ __('Exchange rate to :currency', ['currency' => $shipment->currency]) }}</label><input id="billed_exchange_rate" class="form-input" type="number" step="0.00000001" min="0" name="billed_exchange_rate" value="{{ old('billed_exchange_rate', $shipment->billed_exchange_rate ?? $shipment->approvedQuote?->exchange_rate) }}"></div>
                                <div>
                                    <label class="form-label" for="payment_status">{{ __('Payment status') }}</label>
                                    <select id="payment_status" class="form-input" name="payment_status">@foreach(\App\Modules\Shipping\Models\Shipment::PAYMENT_STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('payment_status', $shipment->payment_status) === $value)>{{ __($label) }}</option>@endforeach</select>
                                </div>
                                <p class="text-sm font-semibold text-title md:col-span-4">{{ __('Surcharges included in the bill, by reason') }}</p>
                                @foreach($reasons as $reason => $label)
                                    <div><label class="form-label" for="bill_charge_{{ $reason }}">{{ __($label) }}</label><input id="bill_charge_{{ $reason }}" class="form-input" type="number" step="0.01" min="0" name="charges[{{ $reason }}]" value="{{ old('charges.'.$reason, isset($actualCharges[$reason]) ? (float) $actualCharges[$reason] : '') }}" placeholder="0.00"></div>
                                @endforeach
                                <div class="md:col-span-4"><x-forms.submit :label="__('Save billed amount')" /></div>
                            </form>
                        @endif
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                {{-- Booking --}}
                <section class="section-card" id="tracking">
                    <h2 class="heading-5 text-title">{{ __('Courier and tracking') }}</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-body">{{ __('Courier') }}</dt><dd class="text-title">{{ ($canViewCosts ? $shipment->providerLabel() : $shipment->carrierLabel()) ?: __('Not approved yet') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-body">{{ __('Tracking number') }}</dt><dd class="text-title">@if($shipment->tracking_url)<a class="text-primary" href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener">{{ $shipment->tracking_number }}</a>@else{{ $shipment->tracking_number ?: '—' }}@endif</dd></div>
                        @if($shipment->shipped_at)<div class="flex justify-between gap-3"><dt class="text-body">{{ __('Shipped') }}</dt><dd class="text-title">{{ $shipment->shipped_at->format('d M Y') }}</dd></div>@endif
                        @if($shipment->delivered_at)<div class="flex justify-between gap-3"><dt class="text-body">{{ __('Delivered') }}</dt><dd class="text-title">{{ $shipment->delivered_at->format('d M Y') }}</dd></div>@endif
                    </dl>
                    @if($canManage)
                        @if($shipment->approved_quote_id)
                            <form method="POST" action="{{ route('user.shipping.shipments.booking', $shipment) }}" class="mt-4 space-y-3">
                                @csrf @method('PUT')
                                <div><label class="form-label" for="tracking_number">{{ __('Tracking number') }}</label><input id="tracking_number" class="form-input" name="tracking_number" maxlength="80" required value="{{ old('tracking_number', $shipment->tracking_number) }}">@error('tracking_number')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                                <div><label class="form-label" for="tracking_url">{{ __('Tracking link') }}</label><input id="tracking_url" class="form-input" type="url" name="tracking_url" maxlength="500" value="{{ old('tracking_url', $shipment->tracking_url) }}" placeholder="https://...">@error('tracking_url')<p class="form-hint text-error">{{ $message }}</p>@enderror</div>
                                <div>
                                    <label class="form-label" for="booking_status">{{ __('Progress') }}</label>
                                    <select id="booking_status" class="form-input" name="status">
                                        @foreach(['booked' => 'Booked', 'in_transit' => 'In Transit', 'delivered' => 'Delivered'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $shipment->status) === $value)>{{ __($label) }}</option>@endforeach
                                    </select>
                                </div>
                                <x-forms.submit :label="__('Save tracking')" />
                                <p class="form-hint">{{ __('The customer sees the courier, tracking number and progress only. Booking with the courier is done by you; nothing is sent automatically.') }}</p>
                            </form>
                        @else
                            <p class="mt-3 text-sm text-body">{{ __('Approve a carrier or agent before adding tracking.') }}</p>
                        @endif
                        <form method="POST" action="{{ route('user.shipping.shipments.status', $shipment) }}" class="mt-4 flex items-end gap-2 border-t border-border-soft pt-4">
                            @csrf @method('PUT')
                            <div class="grow">
                                <label class="form-label" for="shipment_status">{{ __('Set status') }}</label>
                                <select id="shipment_status" class="form-input" name="status">@foreach(\App\Modules\Shipping\Models\Shipment::STATUSES as $value => $label)<option value="{{ $value }}" @selected($shipment->status === $value)>{{ __($label) }}</option>@endforeach</select>
                            </div>
                            <button class="btn btn-outline" type="submit">{{ __('Update') }}</button>
                        </form>
                    @endif
                </section>

                {{-- Remote area --}}
                <section class="section-card" id="remote">
                    <h2 class="heading-5 text-title">{{ __('Remote-area status by provider') }}</h2>
                    @if($remoteStatuses->contains(fn ($row) => in_array($row['status'], ['remote', 'extended_remote'], true)))
                        <p class="mt-2 text-sm text-error"><i class="ph ph-warning"></i> {{ __('A remote-area charge is confirmed for at least one provider.') }}</p>
                    @endif
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach($remoteStatuses as $row)
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-title">{{ $row['provider'] }}</span>
                                <span class="text-right">
                                    @include('shipping::user.management.partials.status-badge', ['status' => $row['status'], 'label' => __($row['label'])])
                                    @if($canViewCosts && (float) $row['fee'] > 0)<span class="block text-xs text-body">{{ $row['currency'] }} {{ number_format((float) $row['fee'], 2) }}</span>@endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="form-hint">{{ __('A postal code is only marked remote when that provider has confirmed it.') }} <a class="text-primary" href="{{ route('user.shipping.remote-areas.index', ['country' => $shipment->country_code, 'postal_code' => $shipment->postal_code, 'city' => $shipment->city]) }}">{{ __('Open checker') }}</a></p>
                </section>

                {{-- Products --}}
                <section class="section-card">
                    <h2 class="heading-5 text-title">{{ __('Products in this order') }}</h2>
                    <ul class="mt-3 space-y-1 text-sm text-body">
                        @foreach($shipment->order?->items ?? [] as $item)
                            <li class="flex justify-between gap-3"><span>{{ $item->product_name }} @if($item->attributes)<span class="text-xs">({{ collect($item->attributes)->filter()->implode(' / ') }})</span>@endif</span><span class="text-title">× {{ $item->quantity }}</span></li>
                        @endforeach
                    </ul>
                </section>

                @if($auditEntries->isNotEmpty())
                    <section class="section-card">
                        <h2 class="heading-5 text-title">{{ __('History') }}</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach($auditEntries as $entry)
                                <li><span class="text-title">{{ $entry->summary }}</span><span class="block text-xs text-body">{{ $entry->user?->name ?? __('System') }} · {{ $entry->created_at?->format('d M Y H:i') }}</span></li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.user>
