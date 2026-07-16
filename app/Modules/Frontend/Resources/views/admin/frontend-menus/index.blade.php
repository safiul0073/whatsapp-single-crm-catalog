<x-layouts.admin :title="__('Menu Management')">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Menu Management') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Build shared navigation menus once and assign them to theme slots without duplicating content.') }}</p>
            </div>
            <x-ui.button variant="primary" href="{{ route('admin.frontend-menus.create') }}">
                <i class="ph ph-plus-circle"></i> {{ __('Create Menu') }}
            </x-ui.button>
        </div>

        <div class="section-card space-y-4">
            <form method="GET" action="{{ route('admin.frontend-menus.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <x-forms.input :label="__('Search')" name="search" :value="request('search')" :placeholder="__('Search by name or slug')" />
                <x-forms.select
                    :label="__('Status')"
                    name="status"
                    :selected="request('status')"
                    :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                    :placeholder="__('All statuses')"
                />
                <div class="flex items-end gap-3 md:col-span-2">
                    <x-forms.submit :label="__('Filter')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.frontend-menus.index') }}">{{ __('Reset') }}</x-ui.button>
                </div>
            </form>

            <x-tables.table>
                <thead>
                    <tr>
                        <th>{{ __('Menu') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Items') }}</th>
                        <th>{{ __('Theme Usage') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($menus as $menu)
                        @php $usage = $usageMap[$menu->id] ?? []; @endphp
                        <tr>
                            <td data-th="{{ __('Menu') }}">
                                <div class="flex flex-col">
                                    <span class="font-medium text-neutral-900">{{ $menu->name }}</span>
                                    <span class="text-xs text-neutral-400">{{ '@' . $menu->slug }}</span>
                                </div>
                            </td>
                            <td data-th="{{ __('Status') }}">
                                <x-ui.badge :variant="$menu->status === 'published' ? 'success' : ($menu->status === 'draft' ? 'warning' : 'danger')">
                                    {{ ucfirst($menu->status) }}
                                </x-ui.badge>
                            </td>
                            <td data-th="{{ __('Items') }}" class="text-sm text-neutral-600">{{ trans_choice(':count item|:count items', $menu->items_count, ['count' => $menu->items_count]) }}</td>
                            <td data-th="{{ __('Theme Usage') }}">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($usage as $assignment)
                                        <x-ui.badge variant="primary">{{ $assignment['theme_label'] }} / {{ $assignment['slot_label'] }}</x-ui.badge>
                                    @empty
                                        <span class="text-xs text-neutral-400">{{ __('Not assigned') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td data-th="{{ __('Actions') }}" class="text-right">
                                <x-tables.actions>
                                    <x-tables.action icon="pencil-simple" :href="route('admin.frontend-menus.edit', $menu)" :label="__('Edit')" />
                                    <x-tables.action icon="{{ $menu->status === 'published' ? 'arrow-counter-clockwise' : 'paper-plane-tilt' }}" onclick="document.getElementById('toggle-menu-{{ $menu->id }}').submit()" :label="$menu->status === 'published' ? __('Unpublish') : __('Publish')" />
                                    <x-tables.action icon="trash" variant="danger" :label="__('Delete')" data-modal-trigger="confirmDeleteMenu-{{ $menu->id }}" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-neutral-400">{{ __('No menus found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-tables.table>

            <x-tables.pagination :paginator="$menus" />
        </div>
    </div>

    @foreach($menus as $menu)
        @php
            $usage = $usageMap[$menu->id] ?? [];
            $deleteMessage = $usage === []
                ? __('This will permanently delete the menu and all of its navigation items.')
                : __('This menu is still assigned to: :usage. Unassign it from theme settings before deleting.', [
                    'usage' => collect($usage)->map(fn ($item) => $item['theme_label'].' / '.$item['slot_label'])->implode(', '),
                ]);
        @endphp
        @push('modals')
            <x-ui.confirm
                id="confirmDeleteMenu-{{ $menu->id }}"
                :title="__('Delete Menu?')"
                :message="$deleteMessage"
                :confirmText="__('Yes, Delete')"
                :cancelText="__('Cancel')"
                formId="delete-menu-{{ $menu->id }}"
            />
        @endpush

        <form id="toggle-menu-{{ $menu->id }}" method="POST" action="{{ route('admin.frontend-menus.publish', $menu) }}" class="hidden">
            @csrf
        </form>

        <form id="delete-menu-{{ $menu->id }}" method="POST" action="{{ route('admin.frontend-menus.destroy', $menu) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-layouts.admin>
