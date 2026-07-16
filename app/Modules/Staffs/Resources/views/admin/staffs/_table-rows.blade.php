@forelse($staffs as $staff)
    <tr>
        <td data-th="{{ __('Name') }}">
            <div class="flex items-center justify-end gap-3 lg:justify-start rtl:justify-start">
                @if($staff->avatar)
                    <img src="{{ Storage::url($staff->avatar) }}" alt="{{ $staff->name }}" class="h-10 w-10 rounded-full object-cover" />
                @else
                    <div class="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-full font-bold">
                        {{ strtoupper(substr($staff->name, 0, 2)) }}
                    </div>
                @endif
                <div class="text-right lg:text-left">
                    <p class="text-sm font-bold text-neutral-950">{{ $staff->name }}</p>
                    <p class="text-xs text-neutral-600">{{ __('Staff') }}</p>
                </div>
            </div>
        </td>
        <td data-th="{{ __('Email') }}" class="text-sm text-neutral-600">{{ $staff->email }}</td>
        <td data-th="{{ __('Phone') }}" class="text-sm text-neutral-600">{{ $staff->phone ?? __('N/A') }}</td>
        <td data-th="{{ __('Status') }}">
            <div class="flex justify-end lg:justify-start rtl:justify-start">
                <x-ui.badge :variant="$staff->is_active ? 'success' : 'danger'">
                    {{ $staff->is_active ? __('Active') : __('Inactive') }}
                </x-ui.badge>
            </div>
        </td>
        <td data-th="{{ __('Created') }}" class="text-sm text-neutral-400">{{ format_date($staff->created_at) }}</td>
        <td data-th="{{ __('Actions') }}" class="text-right">
            <x-tables.actions>
                <x-tables.action icon="eye" :href="route('admin.staffs.show', $staff)" :label="__('View')" />
                @can('staffs.edit')
                    <x-tables.action icon="pencil-simple" :href="route('admin.staffs.edit', $staff)" :label="__('Edit')" />
                @endcan
                @can('staffs.delete')
                    <x-tables.action icon="trash" :label="__('Delete')" variant="danger" data-modal-trigger="confirmDeleteStaff-{{ $staff->id }}" />
                @endcan
            </x-tables.actions>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="py-8 text-center text-neutral-400">{{ __('No staff members found.') }}</td>
    </tr>
@endforelse