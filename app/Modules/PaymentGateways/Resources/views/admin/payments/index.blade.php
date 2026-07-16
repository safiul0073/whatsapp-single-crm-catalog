<x-layouts.admin :title="__('Payments')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Payments') }}</h1>
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.payments.index')">
                <x-tables.table>
                    <thead>
                        <tr>
                            <th>{{ __('UUID') }}</th>
                            <x-tables.heading field="amount" sortable>{{ __('Amount') }}</x-tables.heading>
                            <th>{{ __('Gateway') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <x-tables.heading field="created_at" sortable>{{ __('Date') }}</x-tables.heading>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-datatable-body>
                        @include('payment-gateways::admin.payments._table-rows')
                    </tbody>
                </x-tables.table>

                <x-slot:pagination>
                    <x-tables.pagination :paginator="$payments" />
                </x-slot:pagination>
            </x-tables.datatable>
        </div>
    </div>
</x-layouts.admin>
