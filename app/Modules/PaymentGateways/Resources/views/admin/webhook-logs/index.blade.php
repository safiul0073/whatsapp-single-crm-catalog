<x-layouts.admin :title="__('Webhook Logs')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Webhook Logs') }}</h1>
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.webhook-logs.index')">
                <x-tables.table>
                    <thead>
                        <tr>
                            <th>{{ __('Gateway') }}</th>
                            <th>{{ __('Event') }}</th>
                            <th>{{ __('Event ID') }}</th>
                            <th>{{ __('Processed') }}</th>
                            <x-tables.heading field="created_at" sortable>{{ __('Received') }}</x-tables.heading>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-datatable-body>
                        @include('payment-gateways::admin.webhook-logs._table-rows')
                    </tbody>
                </x-tables.table>

                <x-slot:pagination>
                    <x-tables.pagination :paginator="$webhookLogs" />
                </x-slot:pagination>
            </x-tables.datatable>
        </div>
    </div>
</x-layouts.admin>
