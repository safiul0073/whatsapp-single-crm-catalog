<x-layouts.admin :title="__('Manage Pages')">
    <div class="space-y-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Manage Pages') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Compose pages once, manage shared sections cleanly, and keep publishing safe.') }}</p>
            </div>
            <x-ui.button variant="primary" href="{{ route('admin.frontend-pages.create') }}">
                <i class="ph ph-plus-circle"></i> {{ __('Create Page') }}
            </x-ui.button>
        </div>

        <div class="section-card space-y-4">
            <form method="GET" action="{{ route('admin.frontend-pages.index') }}" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <x-forms.input :label="__('Search')" name="search" :value="request('search')" :placeholder="__('Search title or slug')" />
                <x-forms.select
                    :label="__('Status')"
                    name="status"
                    :selected="request('status')"
                    :options="['draft' => __('Draft'), 'published' => __('Published'), 'archived' => __('Archived')]"
                    :placeholder="__('All statuses')"
                />
                <div class="flex items-end gap-3 md:col-span-2">
                    <x-forms.submit :label="__('Filter')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.frontend-pages.index') }}">{{ __('Reset') }}</x-ui.button>
                </div>
            </form>

            <x-tables.table>
                <thead>
                    <tr>
                        <th>{{ __('Page') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Layout') }}</th>
                        <th>{{ __('Sections') }}</th>
                        <th>{{ __('Compatible Themes') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pages as $page)
                        <tr>
                            <td data-th="{{ __('Page') }}">
                                <div class="flex flex-col">
                                    <span class="font-medium text-neutral-900">{{ $page->title }}</span>
                                    <span class="text-xs text-neutral-400">{{ $page->is_home ? '/' : '/' . $page->slug }}</span>
                                </div>
                            </td>
                            <td data-th="{{ __('Status') }}">
                                <x-ui.badge :variant="$page->status === 'published' ? 'success' : ($page->status === 'draft' ? 'warning' : 'danger')">
                                    {{ ucfirst($page->status) }}
                                </x-ui.badge>
                            </td>
                            <td data-th="{{ __('Layout') }}" class="text-sm text-neutral-600">{{ ucfirst($page->default_layout) }}</td>
                            <td data-th="{{ __('Sections') }}" class="text-sm text-neutral-600">{{ $page->sections->count() }}</td>
                            <td data-th="{{ __('Compatible Themes') }}">
                                <div class="flex flex-wrap gap-2">
                                    @forelse($compatibleThemes[$page->id] ?? [] as $themeKey)
                                        <x-ui.badge variant="primary">{{ $themeLabels[$themeKey] ?? ucfirst($themeKey) }}</x-ui.badge>
                                    @empty
                                        <x-ui.badge variant="danger">{{ __('Fallback Only') }}</x-ui.badge>
                                    @endforelse
                                </div>
                            </td>
                            <td data-th="{{ __('Actions') }}" class="text-right">
                                <x-tables.actions>
                                    <x-tables.action icon="pencil-simple" :href="route('admin.frontend-pages.edit', $page)" :label="__('Edit')" />
                                    <x-tables.action icon="{{ $page->status === 'published' ? 'arrow-counter-clockwise' : 'paper-plane-tilt' }}" :href="route('admin.frontend-pages.publish', $page)" :label="$page->status === 'published' ? __('Unpublish') : __('Publish')" />
                                    <x-tables.action icon="trash" variant="danger" :label="__('Delete')" data-modal-trigger="confirmDeletePage-{{ $page->id }}" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-neutral-400">{{ __('No pages found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-tables.table>

            <x-tables.pagination :paginator="$pages" />
        </div>
    </div>

    @foreach($pages as $page)
        @push('modals')
            <x-ui.confirm
                id="confirmDeletePage-{{ $page->id }}"
                :title="__('Delete Page?')"
                :message="__('This will permanently delete the page and its section assignments.')"
                :confirmText="__('Yes, Delete')"
                :cancelText="__('Cancel')"
                formId="delete-page-{{ $page->id }}"
            />
        @endpush
        <form id="delete-page-{{ $page->id }}" method="POST" action="{{ route('admin.frontend-pages.destroy', $page) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
    @endforeach
</x-layouts.admin>
