@forelse($users as $user)
    <tr>
        <td data-th="{{ __('Name') }}">
            <div class="flex items-center justify-end gap-3 lg:justify-start rtl:justify-start">
                @if($user->avatar && avatar_url($user->avatar))
                    <img src="{{ avatar_url($user->avatar) }}" alt="{{ $user->name }}" class="h-10 w-10 rounded-full object-cover" />
                @else
                    <div class="bg-primary/10 text-primary flex h-10 w-10 items-center justify-center rounded-full font-bold">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                @endif
                <div class="text-right lg:text-left">
                    <p class="text-sm font-bold text-neutral-950">{{ $user->name }}</p>
                </div>
            </div>
        </td>
        <td data-th="{{ __('Email') }}" class="text-sm text-neutral-600">{{ $user->email }}</td>
        <td data-th="{{ __('Status') }}">
            <div class="flex justify-end lg:justify-start rtl:justify-start">
                <x-ui.badge :variant="$user->is_active ? 'success' : 'danger'">
                    {{ $user->is_active ? __('Active') : __('Inactive') }}
                </x-ui.badge>
            </div>
        </td>
        <td data-th="{{ __('Created') }}" class="text-sm text-neutral-400">{{ format_date($user->created_at) }}</td>
        <td data-th="{{ __('Actions') }}" class="text-right">
            <x-tables.actions>
                <x-tables.action icon="eye" :href="route('admin.users.show', $user)" :label="__('View')" />
                <x-tables.action icon="pencil-simple" :href="route('admin.users.edit', $user)" :label="__('Edit')" />
                <x-tables.action icon="trash" :label="__('Delete')" variant="danger" data-modal-trigger="confirmDeleteUser-{{ $user->id }}" />
            </x-tables.actions>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5" class="py-8 text-center text-neutral-400">{{ __('No users found.') }}</td>
    </tr>
@endforelse
