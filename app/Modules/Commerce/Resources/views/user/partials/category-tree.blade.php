@foreach($categories as $category)
    <article class="rounded-2xl border border-border p-4 bg-surface transition-all" x-data="{ editing: false, expanded: false }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <input type="checkbox" class="app-checkbox" value="{{ $category->id }}" x-model="selectedCategories" aria-label="{{ __('Select :category', ['category' => $category->name]) }}" @disabled($category->products_count > 0 || $category->children_count > 0)>
                
                @if($category->children_count > 0)
                    <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary transition-transform" :class="expanded ? 'rotate-90' : ''" @click="expanded = !expanded">
                        <i class="ph ph-caret-right text-xl"></i>
                    </button>
                @else
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $category->parent_id ? 'bg-section text-body' : 'bg-primary/10 text-primary' }}">
                        <i class="ph {{ $category->parent_id ? 'ph-folder-notch' : 'ph-folder' }} text-xl"></i>
                    </span>
                @endif
                
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="truncate font-semibold text-title">{{ $category->name }}</h3>
                        <span class="badge {{ $category->is_active ? 'bg-success/10 text-success' : 'badge-soft' }}">{{ $category->is_active ? __('Active') : __('Hidden') }}</span>
                    </div>
                    <p class="mt-1 text-xs text-body">
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
                    <button type="submit" class="row-action text-error" aria-label="{{ __('Delete :category', ['category' => $category->name]) }}" data-confirm data-confirm-title="{{ __('Delete category?') }}" data-confirm-body="{{ __('Only empty categories can be deleted. This category will be permanently removed.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error" @disabled($category->products_count > 0 || $category->children_count > 0)>
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
                    @foreach ($allCategories->where('id', '!=', $category->id) as $parent)
                        <option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->path ?? $parent->name }}</option>
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

        @if($category->children_count > 0)
            <div x-show="expanded" x-collapse x-cloak class="mt-4 ml-6 border-l-2 border-border pl-4 space-y-3">
                @include('commerce::user.partials.category-tree', ['categories' => $allCategories->where('parent_id', $category->id)])
            </div>
        @endif
    </article>
@endforeach
