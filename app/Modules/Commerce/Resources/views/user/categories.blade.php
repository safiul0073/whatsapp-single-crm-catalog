<x-layouts.user :title="__('Product categories')">
    <div class="space-y-6">
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
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="heading-5 text-title">{{ __('All categories') }}</h2>
                        <p class="text-sm text-body">{{ trans_choice(':count category|:count categories', $categories->count(), ['count' => $categories->count()]) }}</p>
                    </div>
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-section text-primary">
                        <i class="ph ph-tree-structure text-xl"></i>
                    </span>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse ($categories as $category)
                        <article class="rounded-2xl border border-border p-4" x-data="{ editing: false }">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $category->parent_id ? 'bg-section text-body' : 'bg-primary/10 text-primary' }}">
                                        <i class="ph {{ $category->parent_id ? 'ph-folder-notch' : 'ph-folder' }} text-xl"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="truncate font-semibold text-title">{{ $category->name }}</h3>
                                            <span class="badge {{ $category->is_active ? 'bg-success/10 text-success' : 'badge-soft' }}">{{ $category->is_active ? __('Active') : __('Hidden') }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-body">
                                            @if ($category->parent)
                                                {{ __('Under :parent', ['parent' => $category->parent->name]) }} ·
                                            @endif
                                            {{ trans_choice(':count product|:count products', $category->products_count, ['count' => $category->products_count]) }} ·
                                            {{ trans_choice(':count child|:count children', $category->children_count, ['count' => $category->children_count]) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline" @click="editing = !editing" :aria-expanded="editing">
                                        <i class="ph ph-pencil-simple"></i> {{ __('Edit') }}
                                    </button>
                                    <form method="POST" action="{{ route('user.commerce.categories.destroy', $category) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="row-action text-error" aria-label="{{ __('Delete :category', ['category' => $category->name]) }}" @disabled($category->products_count > 0 || $category->children_count > 0)>
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <form method="POST" action="{{ route('user.commerce.categories.update', $category) }}" class="mt-4 grid gap-3 border-t border-border pt-4 md:grid-cols-[1fr_1fr_auto]" x-show="editing" x-cloak>
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="form-label" for="category_name_{{ $category->id }}">{{ __('Name') }}</label>
                                    <input id="category_name_{{ $category->id }}" class="form-input" name="name" required maxlength="120" value="{{ $category->name }}">
                                </div>
                                <div>
                                    <label class="form-label" for="category_parent_{{ $category->id }}">{{ __('Parent') }}</label>
                                    <select id="category_parent_{{ $category->id }}" class="form-input" name="parent_id">
                                        <option value="">{{ __('No parent') }}</option>
                                        @foreach ($categories->where('id', '!=', $category->id) as $parent)
                                            <option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-end gap-3">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="check-row min-h-11 px-3 py-2">
                                        <input type="checkbox" class="app-checkbox" name="is_active" value="1" @checked($category->is_active)>
                                        <span class="text-sm font-medium text-title">{{ __('Active') }}</span>
                                    </label>
                                    <x-forms.submit :label="__('Save')" />
                                </div>
                            </form>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-border p-10 text-center">
                            <i class="ph ph-folders text-4xl text-neutral-300"></i>
                            <h3 class="mt-3 font-semibold text-title">{{ __('No categories yet') }}</h3>
                            <p class="mt-1 text-sm text-body">{{ __('Create categories such as Shirts, Dresses, Jackets, or Uniforms.') }}</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-layouts.user>
