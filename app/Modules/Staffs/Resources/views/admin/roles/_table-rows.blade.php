@forelse($roles as $role)
    <tr>
        <td data-th="{{ __('Name') }}" class="text-sm font-medium text-neutral-900">{{ $role->name }}</td>
        <td data-th="{{ __('Users Count') }}">
            <div class="flex justify-end lg:justify-start rtl:justify-start">
                <x-ui.badge variant="info">{{ $role->users_count }} {{ __('users') }}</x-ui.badge>
            </div>
        </td>
        <td data-th="{{ __('Created') }}" class="text-sm text-neutral-400">{{ format_date($role->created_at) }}</td>
        <td data-th="{{ __('Actions') }}" class="text-right">
            <x-tables.actions>
                @can('roles.edit')
                    <x-tables.action icon="pencil-simple" :href="route('admin.roles.edit', $role)" :label="__('Edit')" />
                @endcan
                @can('roles.delete')
                    @if($role->users_count == 0)
                        <x-tables.action icon="trash" :label="__('Delete')" variant="danger" data-modal-trigger="confirmDeleteRole-{{ $role->id }}" />
                    @endif
                @endcan
            </x-tables.actions>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="4" class="py-8 text-center text-neutral-400">{{ __('No roles found.') }}</td>
    </tr>
@endforelse