<x-layouts.admin :title="__('Notification Logs')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Notification Logs') }}</h1>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="section-card flex items-center gap-3 !p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <i class="ph ph-paper-plane-tilt text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-neutral-900">{{ number_format($stats['total']) }}</p>
                    <p class="text-xs text-neutral-400">{{ __('Total') }}</p>
                </div>
            </div>
            <div class="section-card flex items-center gap-3 !p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-success/10 text-success">
                    <i class="ph ph-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-neutral-900">{{ number_format($stats['sent']) }}</p>
                    <p class="text-xs text-neutral-400">{{ __('Sent') }}</p>
                </div>
            </div>
            <div class="section-card flex items-center gap-3 !p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-danger/10 text-danger">
                    <i class="ph ph-x-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-neutral-900">{{ number_format($stats['failed']) }}</p>
                    <p class="text-xs text-neutral-400">{{ __('Failed') }}</p>
                </div>
            </div>
            <div class="section-card flex items-center gap-3 !p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-warning/10 text-warning">
                    <i class="ph ph-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-neutral-900">{{ number_format($stats['queued']) }}</p>
                    <p class="text-xs text-neutral-400">{{ __('Queued') }}</p>
                </div>
            </div>
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.notification-logs.index')">
                <x-tables.table>
                    <thead>
                        <tr>
                            <x-tables.heading field="template_slug" sortable>{{ __('Template') }}</x-tables.heading>
                            <th>{{ __('Channel') }}</th>
                            <th>{{ __('Recipient') }}</th>
                            <th>{{ __('Status') }}</th>
                            <x-tables.heading field="created_at" sortable>{{ __('Sent At') }}</x-tables.heading>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-datatable-body>
                        @include('notification-templates::admin.notification-logs._table-rows')
                    </tbody>
                </x-tables.table>

                <x-slot:pagination>
                    <x-tables.pagination :paginator="$notificationLogs" />
                </x-slot:pagination>
            </x-tables.datatable>
        </div>
    </div>
</x-layouts.admin>
