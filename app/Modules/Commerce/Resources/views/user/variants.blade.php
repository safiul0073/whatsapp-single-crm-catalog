<x-layouts.user :title="__('Variants & Sizes')">
    <div
        class="space-y-6"
        x-data="{
            selectedRecords: [],
            recordIds: @js($presets->pluck('id')->map(fn ($id) => (string) $id)->values()),
            toggleAllRecords(event) { this.selectedRecords = event.target.checked ? [...this.recordIds] : [] },
            allRecordsSelected() { return this.recordIds.length > 0 && this.selectedRecords.length === this.recordIds.length },
        }"
    >
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('Product setup') }}</p>
                <h1 class="heading-3 text-title">{{ __('Variant & Size Presets') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Create reusable size sets (e.g. Adult S–XXL, Kids 2Y–8Y) to use across multiple products.') }}</p>
            </div>
            <x-ui.button variant="primary" href="{{ route('user.commerce.products.create') }}">
                <i class="ph ph-plus"></i> {{ __('Add product') }}
            </x-ui.button>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => 'categories'])

        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert">
                <p class="font-semibold">{{ __('The variant preset could not be saved.') }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
            {{-- Create Preset Form Card --}}
            <section class="section-card h-fit">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph ph-squares-four text-xl"></i>
                    </span>
                    <div>
                        <h2 class="heading-5 text-title">{{ __('Create size preset') }}</h2>
                        <p class="text-sm text-body">{{ __('Available in product wizard for 1-click application.') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('user.commerce.variants.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="form-label" for="preset_name">{{ __('Preset Name') }}</label>
                        <input id="preset_name" class="form-input" name="name" required maxlength="120" value="{{ old('name') }}" placeholder="{{ __('e.g. Standard Adult (S–XXL)') }}">
                    </div>

                    <div>
                        <label class="form-label" for="preset_values">{{ __('Sizes / Values (comma-separated)') }}</label>
                        <input id="preset_values" class="form-input font-medium" name="values_csv" required value="{{ old('values_csv') }}" placeholder="{{ __('e.g. S, M, L, XL, XXL') }}">
                        <span class="text-xs text-body mt-1 block">{{ __('Separate sizes with commas.') }}</span>
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" id="is_active_create" name="is_active" value="1" class="app-checkbox" checked>
                        <label for="is_active_create" class="text-sm font-medium text-title cursor-pointer">{{ __('Active and selectable in products') }}</label>
                    </div>

                    <x-forms.submit :label="__('Create preset')" class="w-full" />
                </form>
            </section>

            {{-- List of Presets --}}
            <section class="section-card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="heading-5 text-title">{{ __('All Variant Presets') }}</h2>
                        <p class="text-sm text-body">{{ trans_choice(':count preset|:count presets', $presets->count(), ['count' => $presets->count()]) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('user.commerce.variants.bulk-destroy') }}" x-show="selectedRecords.length > 0" x-cloak>
                            @csrf
                            @method('DELETE')
                            <template x-for="id in selectedRecords" :key="id">
                                <input type="hidden" name="ids[]" :value="id">
                            </template>
                            <button type="submit" class="btn btn-sm btn-outline text-error hover:border-error hover:text-error" data-confirm data-confirm-title="{{ __('Delete selected presets?') }}" data-confirm-body="{{ __('Selected presets will be permanently removed.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error">
                                <i class="ph ph-trash"></i>
                                <span x-text="'{{ __('Delete selected') }} (' + selectedRecords.length + ')'"></span>
                            </button>
                        </form>
                        <label class="check-row min-h-10 px-3 py-2">
                            <input type="checkbox" class="app-checkbox" :checked="allRecordsSelected()" @change="toggleAllRecords($event)" :disabled="recordIds.length === 0">
                            <span class="text-sm font-medium text-title">{{ __('Select all') }}</span>
                        </label>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($presets as $preset)
                        <article class="rounded-2xl border border-border p-4 bg-neutral-0" x-data="{ editing: false }">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <input type="checkbox" class="app-checkbox" value="{{ $preset->id }}" x-model="selectedRecords" aria-label="{{ __('Select :name', ['name' => $preset->name]) }}">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <i class="ph ph-tag text-xl"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-bold text-title">{{ $preset->name }}</h3>
                                            <span class="badge {{ $preset->is_active ? 'bg-success/10 text-success' : 'badge-soft' }} text-xs">{{ $preset->is_active ? __('Active') : __('Hidden') }}</span>
                                        </div>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            @foreach($preset->values as $val)
                                                <span class="rounded-md bg-neutral-100 border border-neutral-200 px-2 py-0.5 text-xs font-semibold text-neutral-800">{{ $val }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 self-end sm:self-center">
                                    <button type="button" class="btn btn-sm btn-outline" @click="editing = ! editing">
                                        <i class="ph ph-pencil-simple"></i>
                                        <span x-text="editing ? '{{ __('Cancel') }}' : '{{ __('Edit') }}'"></span>
                                    </button>
                                    <form method="POST" action="{{ route('user.commerce.variants.destroy', $preset) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="row-action text-error" data-confirm data-confirm-title="{{ __('Delete :name?', ['name' => $preset->name]) }}" data-confirm-body="{{ __('This preset will be permanently removed.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error" aria-label="{{ __('Delete :name', ['name' => $preset->name]) }}">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Edit Preset Form --}}
                            <form method="POST" action="{{ route('user.commerce.variants.update', $preset) }}" class="mt-4 border-t border-border pt-4 space-y-4" x-show="editing" x-cloak>
                                @csrf
                                @method('PUT')
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="form-label">{{ __('Preset Name') }}</label>
                                        <input class="form-input" name="name" required maxlength="120" value="{{ $preset->name }}">
                                    </div>
                                    <div>
                                        <label class="form-label">{{ __('Sizes / Values (comma-separated)') }}</label>
                                        <input class="form-input font-medium" name="values_csv" required value="{{ implode(', ', $preset->values) }}">
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <label class="check-row">
                                        <input type="checkbox" name="is_active" value="1" class="app-checkbox" @checked($preset->is_active)>
                                        <span class="text-sm font-medium text-title">{{ __('Active') }}</span>
                                    </label>
                                    <x-forms.submit :label="__('Save changes')" />
                                </div>
                            </form>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-border p-8 text-center text-body">
                            <i class="ph ph-squares-four text-3xl text-neutral-400"></i>
                            <p class="mt-2 font-semibold text-title">{{ __('No variant presets yet') }}</p>
                            <p class="text-xs">{{ __('Create your first reusable size preset using the form on the left.') }}</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts.user>
