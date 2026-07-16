<x-layouts.admin :title="__('Notification Templates')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Notification Templates') }}</h1>
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.notification-templates.index')">
                <x-tables.table>
                    <thead>
                        <tr>
                            <x-tables.heading field="name" sortable>{{ __('Name') }}</x-tables.heading>
                            <th>{{ __('Slug') }}</th>
                            <th>{{ __('Channels') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-datatable-body>
                        @include('notification-templates::admin.notification-templates._table-rows')
                    </tbody>
                </x-tables.table>

                <x-slot:pagination>
                    <x-tables.pagination :paginator="$notificationTemplates" />
                </x-slot:pagination>
            </x-tables.datatable>
        </div>
    </div>
</x-layouts.admin>
