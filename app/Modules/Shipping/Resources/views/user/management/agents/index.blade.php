<x-layouts.user :title="__('Shipping Agents')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="heading-4 text-title">{{ __('Shipping Agents') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Third-party agents, their rate charts, surcharges and payment status.') }}</p>
            </div>
            @if($canManage)<x-ui.button :href="route('user.shipping.agents.create')"><i class="ph ph-plus"></i> {{ __('Add agent') }}</x-ui.button>@endif
        </div>

        <div class="section-card">
            @if($agents->isEmpty())
                <p class="py-8 text-center text-body">{{ __('No shipping agents yet.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Agent') }}</th><th>{{ __('Company') }}</th><th>{{ __('WhatsApp / phone') }}</th><th>{{ __('Courier') }}</th><th>{{ __('Active charts') }}</th><th>{{ __('Fuel') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach($agents as $agent)
                                <tr>
                                    <td><a class="font-semibold text-primary" href="{{ route('user.shipping.agents.show', $agent) }}">{{ $agent->name }}</a></td>
                                    <td>{{ $agent->company_name ?: '—' }}</td>
                                    <td>{{ $agent->whatsapp ?: ($agent->phone ?: '—') }}</td>
                                    <td>{{ $agent->carrierLabel() ?: '—' }}</td>
                                    <td>{{ $agent->active_charts_count }}</td>
                                    <td>{{ (float) $agent->fuel_surcharge_percent }}%</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $agent->payment_status])</td>
                                    <td>@include('shipping::user.management.partials.status-badge', ['status' => $agent->is_active ? 'active' : 'inactive'])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-layouts.user>
