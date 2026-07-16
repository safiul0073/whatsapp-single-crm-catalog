<x-layouts.admin :title="__('Staffs')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Staffs') }}</h1>
            @can('staffs.create')
                <x-ui.button variant="primary" href="{{ route('admin.staffs.create') }}">
                    <i class="ph ph-plus-circle"></i> {{ __('Add Staff') }}
                </x-ui.button>
            @endcan
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.staffs.index')">
                <x-tables.table>
                    <thead>
                        <tr>
                            <x-tables.heading field="name" sortable>{{ __('Name') }}</x-tables.heading>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Status') }}</th>
                            <x-tables.heading field="created_at" sortable>{{ __('Created') }}</x-tables.heading>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-datatable-body>
                        @include('staffs::admin.staffs._table-rows')
                    </tbody>
                </x-tables.table>

                <x-slot:pagination>
                    <x-tables.pagination :paginator="$staffs" />
                </x-slot:pagination>
            </x-tables.datatable>
        </div>
    </div>

    @foreach($staffs as $staff)
        @push('modals')
            <x-ui.confirm
                id="confirmDeleteStaff-{{ $staff->id }}"
                :title="__('Delete Staff?')"
                :message="__('Are you sure you want to delete \':name\'? This action cannot be undone.', ['name' => $staff->name])"
                :confirmText="__('Yes, Delete')"
                :cancelText="__('Cancel')"
                formId="delete-staff-{{ $staff->id }}"
            />
        @endpush
    @endforeach

    @foreach($staffs as $staff)
        <form id="delete-staff-{{ $staff->id }}" method="POST" action="{{ route('admin.staffs.destroy', $staff) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-layouts.admin>