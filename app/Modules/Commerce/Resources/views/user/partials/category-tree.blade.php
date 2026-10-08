@php($depth = $depth ?? 0)
@foreach($categories as $category)
    @php($isDeletable = $category->products_count === 0 && $category->children_count === 0)
    <li x-data="{ editing: false, expanded: {{ $depth === 0 ? 'true' : 'false' }} }" data-category-row="{{ $category->id }}">
        <div @class([
            'group flex items-center gap-3 border-b border-neutral-100 py-2.5 pr-3 transition-colors hover:bg-section/60',
            'pl-3' => $depth === 0,
            'pl-10' => $depth === 1,
            'pl-[4.25rem]' => $depth === 2,
            'pl-24' => $depth >= 3,
        ])>
            <input type="checkbox" class="app-checkbox shrink-0" value="{{ $category->id }}" x-model="selectedCategories" aria-label="{{ __('Select :category', ['category' => $category->name]) }}" @disabled(! $isDeletable)>

            @if($category->children_count > 0)
                <button type="button" class="grid h-7 w-7 shrink-0 place-items-center rounded-md text-body transition hover:bg-neutral-100 hover:text-title" @click="expanded = !expanded" :aria-expanded="expanded" aria-label="{{ __('Toggle :category', ['category' => $category->name]) }}">
                    <i class="ph-bold ph-caret-right text-sm transition-transform" :class="expanded && 'rotate-90'"></i>
                </button>
            @else
                <span class="w-7 shrink-0"></span>
            @endif

            <span @class([
                'grid h-8 w-8 shrink-0 place-items-center rounded-md',
                'bg-primary/10 text-primary' => $depth === 0,
                'bg-neutral-100 text-body' => $depth > 0,
            ])>
                <i class="ph {{ $category->children_count > 0 ? 'ph-folders' : 'ph-folder' }} text-base"></i>
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 items-center gap-2">
                    <span @class(['truncate text-title', 'font-semibold' => $depth === 0, 'font-medium' => $depth > 0])>{{ $category->name }}</span>
                    @unless($category->is_active)
                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[11px] font-medium text-body">{{ __('Hidden') }}</span>
                    @endunless
                </div>
            </div>

            <div class="hidden shrink-0 items-center gap-1.5 sm:flex">
                <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-xs text-body" title="{{ __('Products') }}">
                    <i class="ph ph-t-shirt mr-0.5 align-[-1px]"></i>{{ $category->products_count }}
                </span>
                @if($category->children_count > 0)
                    <span class="rounded-md bg-neutral-100 px-2 py-0.5 text-xs text-body" title="{{ __('Sub-categories') }}">
                        <i class="ph ph-tree-structure mr-0.5 align-[-1px]"></i>{{ $category->children_count }}
                    </span>
                @endif
                <span @class([
                    'h-2 w-2 rounded-full',
                    'bg-success' => $category->is_active,
                    'bg-neutral-300' => ! $category->is_active,
                ]) title="{{ $category->is_active ? __('Active') : __('Hidden') }}"></span>
            </div>

            <div class="flex shrink-0 items-center gap-0.5">
                <button type="button" class="grid h-8 w-8 place-items-center rounded-md text-body transition hover:bg-neutral-100 hover:text-title" @click="editing = !editing" :aria-expanded="editing" aria-label="{{ __('Edit :category', ['category' => $category->name]) }}" title="{{ __('Edit') }}">
                    <i class="ph ph-pencil-simple text-base"></i>
                </button>
                <form method="POST" action="{{ route('user.commerce.categories.destroy', $category) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="grid h-8 w-8 place-items-center rounded-md text-body transition hover:bg-error/10 hover:text-error disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-body" aria-label="{{ __('Delete :category', ['category' => $category->name]) }}" title="{{ $isDeletable ? __('Delete') : __('Only empty categories can be deleted') }}" data-confirm data-confirm-title="{{ __('Delete category?') }}" data-confirm-body="{{ __('Only empty categories can be deleted. This category will be permanently removed.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error" @disabled(! $isDeletable)>
                        <i class="ph ph-trash text-base"></i>
                    </button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('user.commerce.categories.update', $category) }}" @class([
            'grid gap-3 border-b border-neutral-100 bg-section/50 py-3 pr-3 md:grid-cols-[1fr_1fr_auto]',
            'pl-12' => $depth === 0,
            'pl-[4.75rem]' => $depth === 1,
            'pl-[6.25rem]' => $depth >= 2,
        ]) x-show="editing" x-cloak>
            @csrf
            @method('PUT')
            <div>
                <label class="form-label" for="category_name_{{ $category->id }}">{{ __('Name') }}</label>
                <input id="category_name_{{ $category->id }}" class="form-input rounded-lg!" name="name" required maxlength="120" value="{{ $category->name }}">
            </div>
            <div>
                <label class="form-label" for="category_parent_{{ $category->id }}">{{ __('Parent') }}</label>
                <select id="category_parent_{{ $category->id }}" class="form-input rounded-lg!" name="parent_id">
                    <option value="">{{ __('No parent') }}</option>
                    @foreach ($allCategories->where('id', '!=', $category->id) as $parent)
                        <option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->path ?? $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <input type="hidden" name="is_active" value="0">
                <label class="flex h-11 items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3">
                    <input type="checkbox" class="app-checkbox" name="is_active" value="1" @checked($category->is_active)>
                    <span class="text-sm font-medium text-title">{{ __('Active') }}</span>
                </label>
                <button type="submit" class="btn btn-primary rounded-lg!">{{ __('Save') }}</button>
                <button type="button" class="btn btn-outline rounded-lg!" @click="editing = false">{{ __('Cancel') }}</button>
            </div>
        </form>

        @if($category->children_count > 0)
            <ul x-show="expanded" x-cloak>
                @include('commerce::user.partials.category-tree', ['categories' => $allCategories->where('parent_id', $category->id), 'allCategories' => $allCategories, 'depth' => $depth + 1])
            </ul>
        @endif
    </li>
@endforeach
