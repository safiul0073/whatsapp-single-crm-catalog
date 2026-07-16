<x-layouts.admin :title="__('Manage Frontend')">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Manage Frontend') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Create reusable frontend sections that stay compatible with multiple themes.') }}</p>
            </div>
            <x-ui.button variant="primary" href="{{ route('admin.frontend-sections.create') }}">
                <i class="ph ph-plus-circle"></i> {{ __('Create Section') }}
            </x-ui.button>
        </div>

        <div class="section-card space-y-4">
            <form method="GET" action="{{ route('admin.frontend-sections.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <x-forms.input :label="__('Search')" name="search" :value="request('search')" :placeholder="__('Search by name, slug, or type')" />
                <x-forms.select :label="__('Type')" name="type" :selected="request('type')" :options="$sectionTypes" :placeholder="__('All types')" />
                <x-forms.select
                    :label="__('Status')"
                    name="status"
                    :selected="request('status')"
                    :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                    :placeholder="__('All statuses')"
                />
                <div class="flex items-end gap-3">
                    <x-forms.submit :label="__('Filter')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.frontend-sections.index') }}">{{ __('Reset') }}</x-ui.button>
                </div>
            </form>

            <x-tables.table>
                <thead>
                    <tr>
                        <th>{{ __('Section') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Used In') }}</th>
                        <th>{{ __('Compatible Themes') }}</th>
                        <th>{{ __('Active Theme') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sections as $section)
                        @php $meta = $annotations[$section->id] ?? ['compatible_themes' => [], 'active_theme_supported' => false]; @endphp
                        <tr>
                            <td data-th="{{ __('Section') }}">
                                <div class="flex flex-col">
                                    <span class="font-medium text-neutral-900">{{ $section->name }}</span>
                                    <span class="text-xs text-neutral-400">{{ '@' . $section->slug }}</span>
                                </div>
                            </td>
                            <td data-th="{{ __('Type') }}" class="text-sm text-neutral-600">{{ $sectionTypes[$section->type] ?? $section->type }}</td>
                            <td data-th="{{ __('Status') }}">
                                <x-ui.badge :variant="$section->status === 'published' ? 'success' : ($section->status === 'draft' ? 'warning' : 'danger')">
                                    {{ ucfirst($section->status) }}
                                </x-ui.badge>
                            </td>
                            <td data-th="{{ __('Used In') }}" class="text-sm text-neutral-600">{{ trans_choice(':count page|:count pages', $section->pages_count, ['count' => $section->pages_count]) }}</td>
                            <td data-th="{{ __('Compatible Themes') }}">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($meta['compatible_themes'] as $themeKey)
                                        <x-ui.badge variant="primary">{{ $themeLabels[$themeKey] ?? $themeKey }}</x-ui.badge>
                                    @empty
                                        <span class="text-xs text-neutral-400">{{ __('None') }}</span>
                                    @endforelse
                                </div>
                            </td>
                            <td data-th="{{ __('Active Theme') }}">
                                <x-ui.badge :variant="$meta['active_theme_supported'] ? 'success' : 'danger'">
                                    {{ $meta['active_theme_supported'] ? __('Supported') : __('Fallback') }}
                                </x-ui.badge>
                            </td>
                            <td data-th="{{ __('Actions') }}" class="text-right">
                                <x-tables.actions>
                                    <x-tables.action icon="pencil-simple" :href="route('admin.frontend-sections.edit', $section)" :label="__('Edit')" />
                                    <x-tables.action icon="trash" variant="danger" :label="__('Delete')" data-modal-trigger="confirmDeleteSection-{{ $section->id }}" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-neutral-400">{{ __('No sections found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-tables.table>

            <x-tables.pagination :paginator="$sections" />
        </div>
    </div>

    @foreach($sections as $section)
        @push('modals')
            <x-ui.confirm
                id="confirmDeleteSection-{{ $section->id }}"
                :title="__('Delete Section?')"
                :message="__('This will remove the section from the library. Existing page relationships will also be removed.')"
                :confirmText="__('Yes, Delete')"
                :cancelText="__('Cancel')"
                formId="delete-section-{{ $section->id }}"
            />
        @endpush
        <form id="delete-section-{{ $section->id }}" method="POST" action="{{ route('admin.frontend-sections.destroy', $section) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-layouts.admin>
