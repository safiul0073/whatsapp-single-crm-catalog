<x-layouts.user :title="$title">
    <div
        class="space-y-6"
        x-data="{
            selectedRecords: [],
            recordIds: @js($records->filter(fn ($record) => $record->products_count === 0)->pluck('id')->map(fn ($id) => (string) $id)->values()),
            toggleAllRecords(event) { this.selectedRecords = event.target.checked ? [...this.recordIds] : [] },
            allRecordsSelected() { return this.recordIds.length > 0 && this.selectedRecords.length === this.recordIds.length },
        }"
    >
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('Product setup') }}</p>
                <h1 class="heading-3 text-title">{{ $title }}</h1>
                <p class="mt-1 text-sm text-body">{{ $description }}</p>
            </div>
            <x-ui.button variant="primary" href="{{ route('user.commerce.products.create') }}">
                <i class="ph ph-plus"></i> {{ __('Add product') }}
            </x-ui.button>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => $helpKey])

        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert">
                <p class="font-semibold">{{ __('The :item could not be saved.', ['item' => $singular]) }}</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="section-card h-fit">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <i class="ph {{ $icon }} text-xl"></i>
                    </span>
                    <div>
                        <h2 class="heading-5 text-title">{{ __('Create :item', ['item' => $singular]) }}</h2>
                        <p class="text-sm text-body">{{ __('It will become available in the product form immediately.') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ $storeRoute }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="form-label" for="taxonomy_name">{{ __('Name') }}</label>
                        <input id="taxonomy_name" class="form-input" name="name" required maxlength="{{ $maxLength }}" value="{{ old('name') }}" placeholder="{{ __('Enter :item name', ['item' => $singular]) }}">
                    </div>
                    <input type="hidden" name="is_active" value="1">
                    <x-forms.submit :label="__('Create :item', ['item' => $singular])" class="w-full" />
                </form>
            </section>

            <section class="section-card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="heading-5 text-title">{{ __('All :items', ['items' => strtolower($title)]) }}</h2>
                        <p class="text-sm text-body">{{ trans_choice(':count record|:count records', $records->count(), ['count' => $records->count()]) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ $bulkDestroyRoute }}" x-show="selectedRecords.length > 0" x-cloak>
                            @csrf
                            @method('DELETE')
                            <template x-for="id in selectedRecords" :key="id">
                                <input type="hidden" name="ids[]" :value="id">
                            </template>
                            <button type="submit" class="btn btn-sm btn-outline text-error hover:border-error hover:text-error" data-confirm data-confirm-title="{{ __('Delete selected :items?', ['items' => strtolower($title)]) }}" data-confirm-body="{{ __('Only records without products can be deleted. Selected records will be permanently removed.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error">
                                <i class="ph ph-trash"></i>
                                <span x-text="'{{ __('Delete selected') }} (' + selectedRecords.length + ')'"></span>
                            </button>
                        </form>
                        <label class="check-row min-h-10 px-3 py-2">
                            <input type="checkbox" class="app-checkbox" :checked="allRecordsSelected()" @change="toggleAllRecords($event)" :disabled="recordIds.length === 0">
                            <span class="text-sm font-medium text-title">{{ __('Select unused') }}</span>
                        </label>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-section text-primary">
                            <i class="ph {{ $icon }} text-xl"></i>
                        </span>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($records as $record)
                        <article class="rounded-2xl border border-border p-4" x-data="{ editing: false }">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <input type="checkbox" class="app-checkbox" value="{{ $record->id }}" x-model="selectedRecords" aria-label="{{ __('Select :name', ['name' => $record->name]) }}" @disabled($record->products_count > 0)>
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <i class="ph {{ $icon }} text-xl"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="truncate font-semibold text-title">{{ $record->name }}</h3>
                                            <span class="badge {{ $record->is_active ? 'bg-success/10 text-success' : 'badge-soft' }}">{{ $record->is_active ? __('Active') : __('Hidden') }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-body">{{ trans_choice(':count product|:count products', $record->products_count, ['count' => $record->products_count]) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline" @click="editing = !editing" :aria-expanded="editing">
                                        <i class="ph ph-pencil-simple"></i> {{ __('Edit') }}
                                    </button>
                                    <form method="POST" action="{{ route($destroyRouteName, [$routeParameter => $record]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="row-action text-error" aria-label="{{ __('Delete :name', ['name' => $record->name]) }}" data-confirm data-confirm-title="{{ __('Delete :item?', ['item' => $singular]) }}" data-confirm-body="{{ __('Only records without products can be deleted. This record will be permanently removed.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error" @disabled($record->products_count > 0)>
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST" action="{{ route($updateRouteName, [$routeParameter => $record]) }}" class="mt-4 grid gap-3 border-t border-border pt-4 md:grid-cols-[1fr_auto]" x-show="editing" x-cloak>
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="form-label" for="taxonomy_name_{{ $record->id }}">{{ __('Name') }}</label>
                                    <input id="taxonomy_name_{{ $record->id }}" class="form-input" name="name" required maxlength="{{ $maxLength }}" value="{{ $record->name }}">
                                </div>
                                <div class="flex items-end gap-3">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="check-row min-h-11 px-3 py-2">
                                        <input type="checkbox" class="app-checkbox" name="is_active" value="1" @checked($record->is_active)>
                                        <span class="text-sm font-medium text-title">{{ __('Active') }}</span>
                                    </label>
                                    <x-forms.submit :label="__('Save')" />
                                </div>
                            </form>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-border p-10 text-center">
                            <i class="ph {{ $icon }} text-4xl text-neutral-300"></i>
                            <h3 class="mt-3 font-semibold text-title">{{ __('No :items yet', ['items' => strtolower($title)]) }}</h3>
                            <p class="mt-1 text-sm text-body">{{ __('Create the first one using the form.') }}</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts.user>
