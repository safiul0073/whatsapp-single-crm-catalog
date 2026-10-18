<x-layouts.user :title="__('Custom Pack Templates')">
    <div x-data="{ deleteModalOpen: false, deleteActionUrl: '', templateNameToDelete: '' }" class="space-y-6">
        <header class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('Custom Pack Templates') }}</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    {{ __('Create templates for multi-product wholesale packs. Each custom pack independently validates against these rules before customers can add it to their cart.') }}
                </p>
            </div>
        </header>

        @if(session('success'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm font-medium text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800/60 dark:text-emerald-300">
                <i class="ph ph-check-circle inline mr-1 text-base"></i> {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm font-medium text-amber-800 dark:bg-amber-950/40 dark:border-amber-800/60 dark:text-amber-300">
                <i class="ph ph-warning inline mr-1 text-base"></i> {{ session('warning') }}
            </div>
        @endif

        @if($errors->any())
            <div role="alert" class="rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm font-medium text-rose-800 dark:bg-rose-950/40 dark:border-rose-800/60 dark:text-rose-300">
                <i class="ph ph-warning-circle inline mr-1 text-base"></i> {{ $errors->first() }}
            </div>
        @endif

        <!-- Existing Templates -->
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Configured Templates') }} ({{ $templates->count() }})</h2>

            @forelse($templates as $template)
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $template->name }}</h3>
                                @if($template->published)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950 dark:text-emerald-400">{{ __('Published') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/20 dark:bg-slate-800 dark:text-slate-400">{{ __('Draft') }}</span>
                                @endif
                                <span class="text-xs text-slate-400">{{ __('Order') }}: {{ $template->position }}</span>
                            </div>
                            @if($template->description)
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $template->description }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="deleteModalOpen = true; deleteActionUrl = '{{ route('user.commerce.pack-templates.destroy', $template->id) }}'; templateNameToDelete = '{{ addslashes($template->name) }}'"
                                class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-400">
                                <i class="ph ph-trash mr-1"></i> {{ __('Delete') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-xs bg-slate-50 dark:bg-slate-800/50 rounded-lg p-3">
                        <div><span class="text-slate-500 dark:text-slate-400 block">{{ __('Min pieces') }}:</span> <strong class="text-slate-800 dark:text-slate-200">{{ $template->min_pieces }}</strong></div>
                        <div><span class="text-slate-500 dark:text-slate-400 block">{{ __('Max pieces') }}:</span> <strong class="text-slate-800 dark:text-slate-200">{{ $template->max_pieces ?: __('No limit') }}</strong></div>
                        <div><span class="text-slate-500 dark:text-slate-400 block">{{ __('Min products') }}:</span> <strong class="text-slate-800 dark:text-slate-200">{{ $template->min_products }}</strong></div>
                        <div><span class="text-slate-500 dark:text-slate-400 block">{{ __('Min colors/prod') }}:</span> <strong class="text-slate-800 dark:text-slate-200">{{ $template->min_colors }}</strong></div>
                        <div><span class="text-slate-500 dark:text-slate-400 block">{{ __('Min sizes/color') }}:</span> <strong class="text-slate-800 dark:text-slate-200">{{ $template->min_sizes }}</strong></div>
                        <div><span class="text-slate-500 dark:text-slate-400 block">{{ __('Min per variant') }}:</span> <strong class="text-slate-800 dark:text-slate-200">{{ $template->min_variant_quantity }}</strong></div>
                    </div>

                    <details class="group border-t border-slate-100 dark:border-slate-800/80 pt-3" @if(old('_edit_id') == $template->id) open @endif>
                        <summary class="cursor-pointer text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                            <span>{{ __('Edit Template Rules & Products') }}</span>
                            <i class="ph ph-caret-down group-open:rotate-180 transition-transform"></i>
                        </summary>

                        <form method="POST" action="{{ route('user.commerce.pack-templates.update', $template->id) }}" class="mt-4 space-y-4">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="_edit_id" value="{{ $template->id }}">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Template Name') }} *
                                    <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" name="name" required maxlength="150" value="{{ old('_edit_id') == $template->id ? old('name', $template->name) : $template->name }}">
                                </label>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Status') }} *
                                    @php
                                        $pubVal = (string) (old('_edit_id') == $template->id ? old('published', $template->published ? '1' : '0') : ($template->published ? '1' : '0'));
                                    @endphp
                                    <select class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" name="published">
                                        <option value="1" @selected($pubVal === '1')>{{ __('Published (Visible on Storefront)') }}</option>
                                        <option value="0" @selected($pubVal === '0')>{{ __('Draft (Hidden)') }}</option>
                                    </select>
                                </label>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Display Order') }} *
                                    <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="position" min="0" value="{{ old('_edit_id') == $template->id ? old('position', $template->position) : $template->position }}" required>
                                </label>
                            </div>

                            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                {{ __('Description') }}
                                <textarea class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" name="description" rows="2" maxlength="2000">{{ old('_edit_id') == $template->id ? old('description', $template->description) : $template->description }}</textarea>
                            </label>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Min Total Pieces per Pack') }} *
                                    <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_pieces" min="1" value="{{ old('_edit_id') == $template->id ? old('min_pieces', $template->min_pieces) : $template->min_pieces }}" required>
                                </label>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Max Total Pieces (Optional)') }}
                                    <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="max_pieces" min="1" value="{{ old('_edit_id') == $template->id ? old('max_pieces', $template->max_pieces) : $template->max_pieces }}" placeholder="{{ __('No maximum') }}">
                                </label>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Min Distinct Products') }} *
                                    <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_products" min="1" value="{{ old('_edit_id') == $template->id ? old('min_products', $template->min_products ?? 1) : ($template->min_products ?? 1) }}" required>
                                </label>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Min Colors per Selected Product') }} *
                                    <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_colors" min="1" value="{{ old('_edit_id') == $template->id ? old('min_colors', $template->min_colors) : $template->min_colors }}" required>
                                </label>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Min Sizes per Product/Color') }} *
                                    <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_sizes" min="1" value="{{ old('_edit_id') == $template->id ? old('min_sizes', $template->min_sizes) : $template->min_sizes }}" required>
                                </label>
                                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                                    {{ __('Min Pieces per Selected Variant') }} *
                                    <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_variant_quantity" min="1" value="{{ old('_edit_id') == $template->id ? old('min_variant_quantity', $template->min_variant_quantity) : $template->min_variant_quantity }}" required>
                                </label>
                            </div>

                            <fieldset class="border border-slate-200 dark:border-slate-700/80 rounded-lg p-3 space-y-2">
                                <legend class="text-xs font-semibold text-slate-700 dark:text-slate-300 px-1">{{ __('Eligible Wholesale Products') }}</legend>
                                <div class="max-h-56 overflow-y-auto space-y-2 pr-1">
                                    @php
                                        $selectedProductIds = old('_edit_id') == $template->id ? old('product_ids', $template->product_ids ?? []) : ($template->product_ids ?? []);
                                    @endphp
                                    @foreach($products as $product)
                                        @php
                                            $availableColors = $product->variants->where('status', 'active')->filter(fn ($v) => $v->color_id && $v->size)->groupBy('color_id')->filter(fn ($variants) => $variants->pluck('size')->unique()->count() >= ($template->min_sizes ?? 1))->count();
                                            $isWarning = $availableColors < ($template->min_colors ?? 1);
                                        @endphp
                                        <label class="flex items-start gap-2 text-xs p-1.5 rounded hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer">
                                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, $selectedProductIds)) class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 mt-0.5">
                                            <div class="flex-1">
                                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ $product->name }}</span>
                                                <span class="text-slate-400 ml-1">({{ $availableColors }} qualifying color(s))</span>
                                                @if($isWarning)
                                                    <span class="ml-1 inline-flex items-center text-amber-600 dark:text-amber-400 font-medium text-[11px]"><i class="ph ph-warning mr-0.5"></i> Needs more colors/sizes</span>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <div class="flex justify-end pt-2">
                                <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500">
                                    {{ __('Update Template') }}
                                </button>
                            </div>
                        </form>
                    </details>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 dark:border-slate-800 p-8 text-center text-slate-500 text-sm">
                    {{ __('No custom pack templates configured yet. Create one below to enable mixed-product wholesale packs.') }}
                </div>
            @endforelse
        </div>

        <!-- Create New Template Card -->
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="ph ph-plus-circle text-indigo-600 dark:text-indigo-400 text-lg"></i>
                {{ __('Create New Custom Pack Template') }}
            </h2>

            <form method="POST" action="{{ route('user.commerce.pack-templates.store') }}" class="space-y-4">
                @csrf
                @php $creatingTemplate = ! old('_edit_id'); @endphp
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Template Name') }} *
                        <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" name="name" required maxlength="150" placeholder="e.g. Starter Summer Mix" value="{{ $creatingTemplate ? old('name') : '' }}">
                    </label>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Status') }} *
                        <select class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" name="published">
                            <option value="1" @selected(($creatingTemplate ? old('published', '1') : '1') === '1')>{{ __('Published (Visible on Storefront)') }}</option>
                            <option value="0" @selected($creatingTemplate && old('published') === '0')>{{ __('Draft (Hidden)') }}</option>
                        </select>
                    </label>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Display Order') }} *
                        <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="position" min="0" value="{{ $creatingTemplate ? old('position', 0) : 0 }}" required>
                    </label>
                </div>

                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                    {{ __('Description') }}
                    <textarea class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" name="description" rows="2" maxlength="2000" placeholder="Guidelines or notes for customers">{{ $creatingTemplate ? old('description') : '' }}</textarea>
                </label>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Min Total Pieces per Pack') }} *
                        <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_pieces" min="1" value="{{ $creatingTemplate ? old('min_pieces', 12) : 12 }}" required>
                    </label>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Max Total Pieces (Optional)') }}
                        <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="max_pieces" min="1" value="{{ $creatingTemplate ? old('max_pieces') : '' }}" placeholder="{{ __('No maximum') }}">
                    </label>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Min Distinct Products') }} *
                        <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_products" min="1" value="{{ $creatingTemplate ? old('min_products', 1) : 1 }}" required>
                    </label>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Min Colors per Selected Product') }} *
                        <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_colors" min="1" value="{{ $creatingTemplate ? old('min_colors', 1) : 1 }}" required>
                    </label>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Min Sizes per Product/Color') }} *
                        <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_sizes" min="1" value="{{ $creatingTemplate ? old('min_sizes', 1) : 1 }}" required>
                    </label>
                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Min Pieces per Selected Variant') }} *
                        <input class="mt-1 block w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white" type="number" name="min_variant_quantity" min="1" value="{{ $creatingTemplate ? old('min_variant_quantity', 1) : 1 }}" required>
                    </label>
                </div>

                <fieldset class="border border-slate-200 dark:border-slate-700/80 rounded-lg p-3 space-y-2">
                    <legend class="text-xs font-semibold text-slate-700 dark:text-slate-300 px-1">{{ __('Eligible Wholesale Products') }} *</legend>
                    <div class="max-h-56 overflow-y-auto space-y-2 pr-1">
                        @foreach($products as $product)
                            @php
                                $availableColors = $product->variants->where('status', 'active')->filter(fn ($v) => $v->color_id && $v->size)->groupBy('color_id')->count();
                            @endphp
                            <label class="flex items-start gap-2 text-xs p-1.5 rounded hover:bg-slate-50 dark:hover:bg-slate-800/60 cursor-pointer">
                                <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked($creatingTemplate && in_array($product->id, old('product_ids', []))) class="rounded border-slate-300 dark:border-slate-700 text-indigo-600 mt-0.5">
                                <div class="flex-1">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $product->name }}</span>
                                    <span class="text-slate-400 ml-1">({{ $availableColors }} color(s) available)</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500">
                        <i class="ph ph-floppy-disk mr-1"></i> {{ __('Create Pack Template') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Delete Confirmation Modal (Alpine.js - no JS alert/confirm) -->
        <div
            x-show="deleteModalOpen"
            x-cloak
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div
                    x-show="deleteModalOpen"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @click="deleteModalOpen = false"
                    class="fixed inset-0 bg-slate-900/60 transition-opacity"></div>

                <div
                    x-show="deleteModalOpen"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="relative transform overflow-hidden rounded-xl bg-white dark:bg-slate-900 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="ph ph-warning text-xl text-rose-600 dark:text-rose-400"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-white" id="modal-title">{{ __('Delete Custom Pack Template') }}</h3>
                            <div class="mt-2">
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Are you sure you want to delete template') }} <strong x-text="templateNameToDelete"></strong>? {{ __('Any existing orders with this template snapshot will remain safe, but storefront customers will no longer be able to build packs with it.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-2">
                        <form :action="deleteActionUrl" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex w-full justify-center rounded-md bg-rose-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 sm:w-auto">
                                {{ __('Yes, Delete Template') }}
                            </button>
                        </form>
                        <button
                            type="button"
                            @click="deleteModalOpen = false"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-900 dark:text-slate-200 shadow-sm ring-1 ring-inset ring-slate-300 dark:ring-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 sm:mt-0 sm:w-auto">
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
