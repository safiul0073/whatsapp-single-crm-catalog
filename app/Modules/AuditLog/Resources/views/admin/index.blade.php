<x-layouts.admin :title="__('Audit Logs')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Audit Logs') }}</h1>
        </div>

        <div class="section-card">
            <x-forms.filters action="{{ route('admin.audit-logs.index') }}">
                <x-forms.filters.select name="action" label="Action" :options="$actions" />
                <x-forms.filters.select name="auditable_type" label="Type" :options="$types->mapWithKeys(fn ($t) => [$t => class_basename($t)])" />
                <x-forms.filters.date name="date_from" label="Date From" />
                <x-forms.filters.date name="date_to" label="Date To" />
            </x-forms.filters>

            <x-tables.table>
                <thead>
                    <tr>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('IP Address') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td data-th="{{ __('User') }}">
                            @if($log->user)
                                <div>
                                    <p class="text-sm font-medium text-neutral-900">{{ $log->user->name }}</p>
                                    <p class="text-xs text-neutral-400">{{ $log->user->email }}</p>
                                </div>
                            @else
                                <span class="text-sm text-neutral-400">{{ __('System') }}</span>
                            @endif
                        </td>
                        <td data-th="{{ __('Action') }}">
                            <div class="flex justify-end lg:justify-start rtl:justify-start">
                                <x-ui.badge :variant="match($log->action) {
                                    'created' => 'success',
                                    'updated' => 'info',
                                    'deleted' => 'danger',
                                    default => 'default'
                                }">
                                    {{ ucfirst($log->action) }}
                                </x-ui.badge>
                            </div>
                        </td>
                        <td data-th="{{ __('Type') }}" class="text-sm text-neutral-600">{{ class_basename($log->auditable_type) }}</td>
                        <td data-th="{{ __('IP Address') }}" class="text-sm text-neutral-600">{{ $log->ip_address ?? __('N/A') }}</td>
                        <td data-th="{{ __('Date') }}" class="text-sm text-neutral-400">{{ format_date($log->created_at, true) }}</td>
                        <td data-th="{{ __('Actions') }}" class="text-right">
                            <a href="{{ route('admin.audit-logs.show', $log) }}" class="btn-icon h-9 w-9" title="{{ __('View Details') }}">
                                <i class="ph ph-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-neutral-400">{{ __('No audit logs found.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </x-tables.table>

            <x-tables.pagination :paginator="$logs" />
        </div>
    </div>
</x-layouts.admin>
