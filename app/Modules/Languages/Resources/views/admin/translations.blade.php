<x-layouts.admin :title="__('Edit Translations') . ' - ' . $language->name">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Edit Translations') }} - {{ $language->name }} ({{ $language->code }})</h1>
            <x-ui.button variant="outline" href="{{ route('admin.languages.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <form method="POST" action="{{ route('admin.languages.translations.update', $language) }}">
            @csrf
            @method('PUT')

            <div class="section-card" style="overflow: visible;">
                <div class="py-4 border-b border-neutral-100">
                    <div class="input-group max-w-sm">
                        <i class="ph ph-magnifying-glass input-icon-left"></i>
                        <input
                            type="text"
                            id="translationSearch"
                            placeholder="{{ __('Search translations...') }}"
                            class="input-field has-icon-left"
                        />
                    </div>
                </div>

                <x-tables.table class="table-fixed">
                    <thead>
                        <tr>
                            <th class="w-1/2">{{ __('Key') }}</th>
                            <th class="w-1/2">{{ __('Translation') }} ({{ strtoupper($language->code) }})</th>
                        </tr>
                    </thead>
                    <tbody id="translationRows">
                        @foreach($sourceKeys as $key => $defaultValue)
                        <tr class="translation-row">
                            <td class="text-sm">{{ $key }}</td>
                            <td>
                                <input
                                    type="text"
                                    name="translations[{{ $key }}]"
                                    value="{{ $translations[$key] ?? '' }}"
                                    class="input-field w-full"
                                    placeholder="{{ $defaultValue }}"
                                    dir="{{ $language->direction }}"
                                />
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>

                <div class="sticky bottom-0 z-10 flex items-center gap-3 p-4 border-t border-neutral-100 bg-neutral-0 rounded-b-2xl">
                    <x-forms.submit :label="__('Save Translations')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.languages.index') }}">{{ __('Cancel') }}</x-ui.button>
                </div>
            </div>
        </form>
    </div>

    @push('styles')
    <style>
        @media (max-width: 991px) {
            .translation-row td {
                text-align: start !important;
            }

            .translation-row td::before {
                float: none !important;
                display: block;
                margin-bottom: 0.25rem;
            }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        document.getElementById('translationSearch').addEventListener('input', function () {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.translation-row').forEach(function (row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    </script>
    @endpush
</x-layouts.admin>
