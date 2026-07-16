<x-layouts.admin :title="__('Price Plans')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Price Plans') }}</h1>
            <x-ui.button variant="primary" href="{{ route('admin.plans.create') }}">
                <i class="ph ph-plus-circle"></i> {{ __('Add Plan') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <x-tables.datatable :url="route('admin.plans.index')" :searchable="false" :perPageOptions="[]">
                <x-tables.table>
                    <thead>
                        <tr>
                            <th>{{ __('Plan') }}</th>
                            <th>{{ __('Price') }}</th>
                            <th>{{ __('Limits') }}</th>
                            <th>{{ __('Features') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($plans as $plan)
                            <tr>
                                <td data-th="{{ __('Plan') }}">
                                    <div class="text-right lg:text-left">
                                        <p class="text-sm font-bold text-neutral-950">{{ $plan->name }}</p>
                                        <p class="text-xs text-neutral-600">{{ $plan->slug }}</p>
                                        @if ($plan->description)
                                            <p class="mt-1 max-w-xs text-xs text-neutral-400 lg:max-w-sm">{{ $plan->description }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td data-th="{{ __('Price') }}">
                                    <p class="text-sm font-medium text-neutral-900">{{ currency_format($plan->price) }}</p>
                                    <p class="text-xs text-neutral-400">{{ ucfirst($plan->interval) }}</p>
                                </td>
                                <td data-th="{{ __('Limits') }}">
                                    <div class="flex max-w-xs flex-wrap justify-end gap-1.5 lg:justify-start">
                                        @forelse (($plan->limits ?? []) as $key => $value)
                                            <x-ui.badge variant="neutral">{{ str_replace('_', ' ', $key) }}: {{ number_format((int) $value) }}</x-ui.badge>
                                        @empty
                                            <span class="text-sm text-neutral-400">{{ __('No limits set') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td data-th="{{ __('Features') }}">
                                    <div class="max-w-xs text-sm text-neutral-600">
                                        @forelse (array_slice($plan->features ?? [], 0, 3) as $feature)
                                            <p>{{ $feature }}</p>
                                        @empty
                                            <span class="text-neutral-400">{{ __('No features set') }}</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td data-th="{{ __('Status') }}">
                                    <div class="flex justify-end lg:justify-start rtl:justify-start">
                                        <x-ui.badge :variant="$plan->is_active ? 'success' : 'danger'">
                                            {{ $plan->is_active ? __('Active') : __('Inactive') }}
                                        </x-ui.badge>
                                    </div>
                                </td>
                                <td data-th="{{ __('Actions') }}" class="text-right">
                                    <x-tables.actions>
                                        <x-tables.action icon="pencil-simple" :href="route('admin.plans.edit', $plan)" :label="__('Edit')" />
                                        <x-tables.action icon="trash" :label="__('Delete')" variant="danger" data-modal-trigger="confirmDeletePlan-{{ $plan->id }}" />
                                    </x-tables.actions>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="table-empty-state">{{ __('No plans found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-tables.table>
            </x-tables.datatable>
        </div>
    </div>

    @foreach($plans as $plan)
        @push('modals')
            <x-ui.confirm
                id="confirmDeletePlan-{{ $plan->id }}"
                :title="__('Delete Plan?')"
                :message="__('Are you sure you want to delete \':name\'? Existing subscriptions may lose their plan reference.', ['name' => $plan->name])"
                :confirmText="__('Yes, Delete')"
                :cancelText="__('Cancel')"
                formId="delete-plan-{{ $plan->id }}"
            />
        @endpush
    @endforeach

    @foreach($plans as $plan)
        <form id="delete-plan-{{ $plan->id }}" method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-layouts.admin>
