<x-layouts.user :title="__('Courier Accounts & APIs')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="heading-4 text-title">{{ __('Courier Accounts & APIs') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Direct UPS, FedEx and Aramex accounts. Credentials are encrypted on the server and never shown again. An account with credentials and a ship-from address is asked for live rates whenever rates are requested for a shipment.') }}</p>
            </div>
            <x-ui.button :href="route('user.shipping.carriers.create')"><i class="ph ph-plus"></i> {{ __('Add account') }}</x-ui.button>
        </div>
        @include('shipping::user.management.partials.section-tabs', ['section' => 'partners'])
        <div class="section-card">
            @if($accounts->isEmpty())
                <p class="py-8 text-center text-body">{{ __('No courier accounts yet.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Account') }}</th><th>{{ __('Courier') }}</th><th>{{ __('Account number') }}</th><th>{{ __('Environment') }}</th><th>{{ __('API credentials') }}</th><th>{{ __('Live rates') }}</th><th>{{ __('API booking') }}</th><th>{{ __('Weight rule') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach($accounts as $account)
                                <tr>
                                    <td class="font-semibold text-title">{{ $account->name }}</td>
                                    <td>{{ $account->carrierLabel() }}</td>
                                    <td>{{ $account->account_number ?: '—' }}</td>
                                    <td>{{ __(ucfirst($account->environment)) }}</td>
                                    <td>@if($account->hasCredentials())<span class="badge badge-success">{{ __('Saved') }}</span>@else<span class="badge badge-neutral">{{ __('Not set') }}</span>@endif</td>
                                    <td>
                                        @if($account->last_error)<span class="badge badge-error" title="{{ $account->last_error }}">{{ __('Error') }}</span><span class="block text-xs text-body">{{ \Illuminate\Support\Str::limit($account->last_error, 60) }}</span>
                                        @elseif($account->isReadyForLiveRates())<span class="badge badge-success">{{ __('Ready') }}</span>@if($account->last_success_at)<span class="block text-xs text-body">{{ $account->last_success_at->diffForHumans() }}</span>@endif
                                        @else<span class="badge badge-neutral">{{ __('Not set up') }}</span>@endif
                                    </td>
                                    <td>@if($account->booking_enabled)<span class="badge badge-info">{{ __('On') }}</span>@else<span class="badge badge-neutral">{{ __('Off') }}</span>@endif</td>
                                    <td>÷{{ $account->dim_divisor }}, {{ (float) $account->weight_rounding_step }} kg</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $account->is_active ? 'active' : 'inactive'])</td>
                                    <td class="text-right"><x-ui.button :href="route('user.shipping.carriers.edit', $account)" variant="outline" size="sm">{{ __('Edit') }}</x-ui.button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.user>
