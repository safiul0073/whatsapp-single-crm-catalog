<x-layouts.admin :title="__('Roles')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Roles') }}</h1>
            @can('roles.create')
                <x-ui.button variant="primary" href="{{ route('admin.roles.create') }}">
                    <i class="ph ph-plus-circle"></i> {{ __('Add Role') }}
                </x-ui.button>
            @endcan
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.roles.index')">
                <x-tables.table>
                    <thead>
                        <tr>
                            <x-tables.heading field="name" sortable>{{ __('Name') }}</x-tables.heading>
                            <th>{{ __('Users Count') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-datatable-body>
                        @include('staffs::admin.roles._table-rows')
                    </tbody>
                </x-tables.table>

                <x-slot:pagination>
                    <x-tables.pagination :paginator="$roles" />
                </x-slot:pagination>
            </x-tables.datatable>
        </div>
    </div>

    @foreach($roles as $role)
        @if($role->users_count == 0)
            @push('modals')
                <x-ui.confirm
                    id="confirmDeleteRole-{{ $role->id }}"
                    :title="__('Delete Role?')"
                    :message="__('Are you sure you want to delete \':name\'? This action cannot be undone.', ['name' => $role->name])"
                    :confirmText="__('Yes, Delete')"
                    :cancelText="__('Cancel')"
                    formId="delete-role-{{ $role->id }}"
                />
            @endpush
        @endif
    @endforeach

    @foreach($roles as $role)
        @if($role->users_count == 0)
            <form id="delete-role-{{ $role->id }}" method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @endforeach
</x-layouts.admin>