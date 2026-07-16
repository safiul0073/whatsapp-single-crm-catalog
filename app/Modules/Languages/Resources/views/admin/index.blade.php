<x-layouts.admin :title="__('Languages')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Languages') }}</h1>
            <x-ui.button variant="primary" href="{{ route('admin.languages.create') }}">
                <i class="ph ph-plus-circle"></i> {{ __('Add Language') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <x-tables.search :action="route('admin.languages.index')" />

            <x-tables.table>
                <thead>
                    <tr>
                        <x-tables.heading field="code" sortable>{{ __('Code') }}</x-tables.heading>
                        <x-tables.heading field="name" sortable>{{ __('Name') }}</x-tables.heading>
                        <th>{{ __('Native Name') }}</th>
                        <th>{{ __('Direction') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Default') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($languages as $language)
                    <tr>
                        <td data-th="{{ __('Code') }}" class="text-sm font-mono text-neutral-900">{{ $language->code }}</td>
                        <td data-th="{{ __('Name') }}" class="text-sm text-neutral-900">{{ $language->name }}</td>
                        <td data-th="{{ __('Native Name') }}" class="text-sm text-neutral-600">{{ $language->native_name }}</td>
                        <td data-th="{{ __('Direction') }}">
                            <div class="flex justify-end lg:justify-start rtl:justify-start">
                                <x-ui.badge :variant="$language->direction === 'rtl' ? 'warning' : 'info'">
                                    {{ strtoupper($language->direction) }}
                                </x-ui.badge>
                            </div>
                        </td>
                        <td data-th="{{ __('Status') }}">
                            <form method="POST" action="{{ route('admin.languages.toggle-status', $language) }}">
                                @csrf
                                <button type="submit" title="{{ __('Toggle status') }}">
                                    <div class="flex justify-end lg:justify-start rtl:justify-start">
                                        <x-ui.badge :variant="$language->is_active ? 'success' : 'danger'">
                                            {{ $language->is_active ? __('Active') : __('Inactive') }}
                                        </x-ui.badge>
                                    </div>
                                </button>
                            </form>
                        </td>
                        <td data-th="{{ __('Default') }}">
                            @if($language->is_default)
                                <div class="flex justify-end lg:justify-start rtl:justify-start">
                                    <x-ui.badge variant="primary">{{ __('Default') }}</x-ui.badge>
                                </div>
                            @else
                                <form method="POST" action="{{ route('admin.languages.set-default', $language) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-neutral-400 hover:text-primary transition-colors" title="{{ __('Set as default') }}">
                                        {{ __('Set default') }}
                                    </button>
                                </form>
                            @endif
                        </td>
                        <td data-th="{{ __('Actions') }}" class="text-right">
                            <x-tables.actions>
                                <x-tables.action icon="translate" :href="route('admin.languages.translations', $language)" :label="__('Translate')" />
                                <x-tables.action icon="pencil-simple" :href="route('admin.languages.edit', $language)" :label="__('Edit')" />
                                @unless($language->is_default)
                                <x-tables.action icon="trash" :label="__('Delete')" variant="danger" data-modal-trigger="confirmDeleteLanguage-{{ $language->id }}" />
                                @endunless
                            </x-tables.actions>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-neutral-400">{{ __('No languages found.') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </x-tables.table>

            <x-tables.pagination :paginator="$languages" />
        </div>
    </div>

    @foreach($languages as $language)
        @unless($language->is_default)
        @push('modals')
            <x-ui.confirm
                id="confirmDeleteLanguage-{{ $language->id }}"
                :title="__('Delete Language?')"
                :message="__('Are you sure you want to delete \':name\'? This action cannot be undone.', ['name' => $language->name])"
                :confirmText="__('Yes, Delete')"
                :cancelText="__('Cancel')"
                formId="delete-language-{{ $language->id }}"
            />
        @endpush
        @endunless
    @endforeach

    @foreach($languages as $language)
        @unless($language->is_default)
        <form id="delete-language-{{ $language->id }}" method="POST" action="{{ route('admin.languages.destroy', $language) }}" class="hidden">
            @csrf
            @method('DELETE')
        </form>
        @endunless
    @endforeach
</x-layouts.admin>
