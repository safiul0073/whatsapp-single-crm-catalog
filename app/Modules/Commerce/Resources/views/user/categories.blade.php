<x-layouts.user :title="__('Product categories')">
    <div
        class="space-y-6"
        x-data="{
            selectedCategories: [],
            categoryIds: @js($categories->filter(fn ($category) => $category->products_count === 0 && $category->children_count === 0)->pluck('id')->map(fn ($id) => (string) $id)->values()),
            toggleAllCategories(event) { this.selectedCategories = event.target.checked ? [...this.categoryIds] : [] },
            allCategoriesSelected() { return this.categoryIds.length > 0 && this.selectedCategories.length === this.categoryIds.length },
        }"
    >
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('Store organization') }}</p>
                <h1 class="heading-3 text-title">{{ __('Product categories') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Organize garments into clear parent and child categories for easier product management.') }}</p>
            </div>
            <x-ui.button variant="primary" href="{{ route('user.commerce.products.create') }}">
                <i class="ph ph-plus"></i> {{ __('Add product') }}
            </x-ui.button>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => 'categories'])

        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error/10 p-4 text-sm text-error" role="alert">
                <p class="font-semibold">{{ __('Category could not be saved.') }}</p>
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
                        <i class="ph ph-folder-plus text-xl"></i>
                    </span>
                    <div>
                        <h2 class="heading-5 text-title">{{ __('Create category') }}</h2>
                        <p class="text-sm text-body">{{ __('Add a main category or place it under an existing one.') }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('user.commerce.categories.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label class="form-label" for="category_name">{{ __('Category name') }}</label>
                        <input id="category_name" class="form-input" name="name" required maxlength="120" value="{{ old('name') }}" placeholder="{{ __('e.g. Jackets') }}">
                    </div>
                    <div>
                        <label class="form-label" for="category_parent">{{ __('Parent category') }}</label>
                        <select id="category_parent" class="form-input" name="parent_id">
                            <option value="">{{ __('No parent — main category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('parent_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="is_active" value="1">
                    <x-forms.submit :label="__('Create category')" class="w-full" />
                </form>
            </section>

            <section class="section-card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="heading-5 text-title">{{ __('All categories') }}</h2>
                        <p class="text-sm text-body">{{ trans_choice(':count category|:count categories', $categories->count(), ['count' => $categories->count()]) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('user.commerce.categories.bulk-destroy') }}" x-show="selectedCategories.length > 0" x-cloak>
                            @csrf
                            @method('DELETE')
                            <template x-for="id in selectedCategories" :key="id">
                                <input type="hidden" name="ids[]" :value="id">
                            </template>
                            <button type="submit" class="btn btn-sm btn-outline text-error hover:border-error hover:text-error" data-confirm data-confirm-title="{{ __('Delete selected categories?') }}" data-confirm-body="{{ __('Only empty categories can be deleted. Selected categories will be permanently removed.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error">
                                <i class="ph ph-trash"></i>
                                <span x-text="'{{ __('Delete selected') }} (' + selectedCategories.length + ')'"></span>
                            </button>
                        </form>
                        <label class="check-row min-h-10 px-3 py-2">
                            <input type="checkbox" class="app-checkbox" :checked="allCategoriesSelected()" @change="toggleAllCategories($event)" :disabled="categoryIds.length === 0">
                            <span class="text-sm font-medium text-title">{{ __('Select empty') }}</span>
                        </label>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-section text-primary">
                            <i class="ph ph-tree-structure text-xl"></i>
                        </span>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @if($categories->isEmpty())
                        <div class="rounded-2xl border border-dashed border-border p-10 text-center">
                            <i class="ph ph-folders text-4xl text-neutral-300"></i>
                            <h3 class="mt-3 font-semibold text-title">{{ __('No categories yet') }}</h3>
                            <p class="mt-1 text-sm text-body">{{ __('Create categories such as Shirts, Dresses, Jackets, or Uniforms.') }}</p>
                        </div>
                    @else
                        @include('commerce::user.partials.category-tree', ['categories' => $categories->whereNull('parent_id'), 'allCategories' => $categories])
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-layouts.user>
