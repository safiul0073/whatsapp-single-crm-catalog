<x-layouts.user :title="$template ? __('Edit Custom Pack Template') : __('Create Custom Pack Template')">
    <div class="space-y-6 max-w-5xl">

        {{-- Top Navigation & Header --}}
        <div>
            <a href="{{ route('user.commerce.pack-templates.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400 mb-3 transition">
                <i class="ph ph-arrow-left text-sm"></i>
                <span>{{ __('Back to Custom Pack Templates') }}</span>
            </a>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-11 place-items-center rounded-2xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20">
                        <i class="ph {{ $template ? 'ph-pencil-simple' : 'ph-plus-circle' }} text-2xl"></i>
                    </span>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                            {{ $template ? __('Edit Custom Pack Template') : __('Create Custom Pack Template') }}
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $template ? __('Update piece limits, variety requirements, and assigned products for this wholesale pack.') : __('Configure piece limits, product variety rules, and assign eligible catalog items for storefront wholesale buyers.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Validation Errors --}}
        @if($errors->any())
            <div role="alert" class="rounded-2xl bg-rose-500/10 p-4 text-xs font-medium text-rose-800 dark:text-rose-300">
                <div class="flex items-center gap-2 mb-1.5">
                    <i class="ph ph-warning-circle text-lg shrink-0 text-rose-600"></i>
                    <strong class="font-bold">{{ __('Please correct the following errors:') }}</strong>
                </div>
                <ul class="list-disc pl-5 space-y-0.5 text-xs text-rose-700 dark:text-rose-300">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form Container (Borderless, Clean Shadow) --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 p-6 sm:p-8 shadow-sm space-y-8"
             x-data="packTemplateForm({
                 name: '{{ addslashes(old('name', $template->name ?? '')) }}',
                 published: '{{ old('published', isset($template) ? ($template->published ? '1' : '0') : '1') }}',
                 position: {{ old('position', $template->position ?? 0) }},
                 description: '{{ addslashes(old('description', $template->description ?? '')) }}',
                 minPieces: {{ old('min_pieces', $template->min_pieces ?? 12) }},
                 maxPieces: '{{ old('max_pieces', $template->max_pieces ?? '') }}',
                 minProducts: {{ old('min_products', $template->min_products ?? 1) }},
                 minColors: {{ old('min_colors', $template->min_colors ?? 1) }},
                 minSizes: {{ old('min_sizes', $template->min_sizes ?? 1) }},
                 minVariantQty: {{ old('min_variant_quantity', $template->min_variant_quantity ?? 1) }},
                 selectedProducts: {{ json_encode(array_map('intval', old('product_ids', $template->product_ids ?? []))) }}
             })">

            <form method="POST" action="{{ $template ? route('user.commerce.pack-templates.update', $template->id) : route('user.commerce.pack-templates.store') }}" class="space-y-8">
                @csrf
                @if($template)
                    @method('PUT')
                @endif

                {{-- Step 1: Basic Information --}}
                <div class="space-y-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                        <span class="grid size-5 place-items-center rounded-full bg-indigo-600 text-white text-[10px] font-bold">1</span>
                        <span>{{ __('Template Details') }}</span>
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Template Name') }} *
                            <input
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                name="name"
                                x-model="name"
                                required
                                maxlength="150"
                                placeholder="{{ __('e.g. Starter Summer Mix') }}">
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('The bundle title displayed to wholesale customers') }}</span>
                        </label>

                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Status') }} *
                            <select
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                name="published"
                                x-model="published">
                                <option value="1">{{ __('Published (Visible on Storefront)') }}</option>
                                <option value="0">{{ __('Draft (Hidden)') }}</option>
                            </select>
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('Draft templates are hidden from the store') }}</span>
                        </label>

                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Display Order') }} *
                            <input
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                type="number"
                                name="position"
                                x-model="position"
                                min="0"
                                required>
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('Lower numbers appear first on storefront') }}</span>
                        </label>
                    </div>

                    <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                        {{ __('Guidelines & Customer Instructions (Optional)') }}
                        <textarea
                            class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                            name="description"
                            x-model="description"
                            rows="2"
                            maxlength="2000"
                            placeholder="{{ __('Guidelines or merchandising notes for wholesale buyers...') }}"></textarea>
                    </label>
                </div>

                {{-- Step 2: Pack Rules & Real-time Variety Calculation --}}
                <div class="space-y-4">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                        <span class="grid size-5 place-items-center rounded-full bg-indigo-600 text-white text-[10px] font-bold">2</span>
                        <span>{{ __('Pack Rules & Variety Limits') }}</span>
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Min Total Pieces per Pack') }} *
                            <input
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                type="number"
                                name="min_pieces"
                                x-model="minPieces"
                                min="1"
                                required>
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('Aggregate piece minimum for the entire pack') }}</span>
                        </label>

                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Max Total Pieces (Optional)') }}
                            <input
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                type="number"
                                name="max_pieces"
                                x-model="maxPieces"
                                min="1"
                                placeholder="{{ __('No maximum') }}">
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('Optional upper limit for single pack') }}</span>
                        </label>

                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Min Distinct Products') }} *
                            <input
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                type="number"
                                name="min_products"
                                x-model="minProducts"
                                min="1"
                                required>
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('Minimum product styles buyer must pick') }}</span>
                        </label>

                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Min Colors per Selected Product') }} *
                            <input
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                type="number"
                                name="min_colors"
                                x-model="minColors"
                                min="1"
                                required>
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('Colorways required per chosen product') }}</span>
                        </label>

                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Min Sizes per Product/Color') }} *
                            <input
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                type="number"
                                name="min_sizes"
                                x-model="minSizes"
                                min="1"
                                required>
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('Sizes required for each selected color') }}</span>
                        </label>

                        <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                            {{ __('Min Pieces per Selected Variant') }} *
                            <input
                                class="mt-1.5 block w-full rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition"
                                type="number"
                                name="min_variant_quantity"
                                x-model="minVariantQty"
                                min="1"
                                required>
                            <span class="text-[11px] text-slate-400 mt-1 block">{{ __('Minimum pieces per specific size/color') }}</span>
                        </label>
                    </div>

                    {{-- Live Math Rule Preview Card (Soft Tint, Borderless) --}}
                    <div class="rounded-2xl bg-gradient-to-br from-indigo-50/70 via-indigo-50/30 to-purple-50/40 dark:from-indigo-950/40 dark:via-slate-900 dark:to-purple-950/20 p-5 space-y-3.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="grid size-7 place-items-center rounded-lg bg-indigo-600 text-white text-xs shadow-xs">
                                    <i class="ph ph-calculator"></i>
                                </span>
                                <div>
                                    <h5 class="text-xs font-bold text-slate-900 dark:text-white">{{ __('Rule Calculator & Consistency Check') }}</h5>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Real-time calculation of minimum pieces required by your diversity rules') }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-white dark:bg-slate-800 px-3 py-1 text-xs font-bold text-indigo-600 dark:text-indigo-400 shadow-2xs">
                                {{ __('Variety Floor: ') }} <span class="ml-1 text-slate-900 dark:text-white" x-text="calculatedVarietyMin + ' pcs'"></span>
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs font-mono bg-white/80 dark:bg-slate-900/70 rounded-xl p-3 text-slate-700 dark:text-slate-300 shadow-2xs">
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 font-bold" x-text="minProducts + ' Products'"></span>
                            <span class="text-slate-400">×</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 font-bold" x-text="minColors + ' Colors/Prod'"></span>
                            <span class="text-slate-400">×</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 font-bold" x-text="minSizes + ' Sizes/Color'"></span>
                            <span class="text-slate-400">×</span>
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 font-bold" x-text="minVariantQty + ' Pcs/Variant'"></span>
                            <span class="text-slate-400">=</span>
                            <span class="px-3 py-1 rounded-lg bg-indigo-600 text-white font-bold" x-text="calculatedVarietyMin + ' Minimum Pieces'"></span>
                        </div>

                        {{-- Contradiction Alert --}}
                        <div x-show="isMaxContradiction" x-cloak class="rounded-xl bg-rose-500/10 p-3.5 text-xs text-rose-700 dark:text-rose-300 flex items-start gap-2.5">
                            <i class="ph ph-warning-circle text-lg text-rose-600 shrink-0 mt-0.5"></i>
                            <div>
                                <strong class="font-bold">{{ __('Contradictory Limit Detected!') }}</strong>
                                <p class="mt-0.5">
                                    {{ __('Max total pieces (') }}<span class="font-bold" x-text="maxPieces"></span>{{ __(') is less than the minimum required by your variety rules (') }}<span class="font-bold" x-text="calculatedVarietyMin"></span>{{ __('). Customers will not be able to build a valid pack.') }}
                                </p>
                            </div>
                        </div>

                        {{-- Variety Minimum Notice --}}
                        <div x-show="isMinPiecesLowerThanVariety && !isMaxContradiction" x-cloak class="rounded-xl bg-blue-500/10 p-3 text-xs text-blue-700 dark:text-blue-300 flex items-start gap-2">
                            <i class="ph ph-info text-base text-blue-600 shrink-0 mt-0.5"></i>
                            <p>
                                {{ __('Notice: Configured Pack MOQ is ') }}<strong x-text="minPieces + ' pcs'"></strong>{{ __(', but satisfying your variety rules will automatically require at least ') }}<strong x-text="calculatedVarietyMin + ' pieces'"></strong>.
                            </p>
                        </div>

                        {{-- Selection Requirement Alert --}}
                        <div x-show="isProductSelectionInsufficient" x-cloak class="rounded-xl bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                            <i class="ph ph-warning text-base text-amber-600 shrink-0 mt-0.5"></i>
                            <p>
                                {{ __('You configured ') }}<strong x-text="minProducts"></strong>{{ __(' min distinct products. Please select at least ') }}<strong x-text="minProducts"></strong>{{ __(' eligible products below. Currently: ') }}<strong x-text="selectedProducts.length"></strong>{{ __(' selected.') }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Step 3: Eligible Wholesale Products Selector --}}
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-2">
                            <span class="grid size-5 place-items-center rounded-full bg-indigo-600 text-white text-[10px] font-bold">3</span>
                            <span>{{ __('Assign Eligible Wholesale Products') }}</span>
                        </h3>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="selectAll({{ json_encode($products->pluck('id')) }})"
                                class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline px-2 py-1">
                                {{ __('Select All') }}
                            </button>
                            <span class="text-slate-300 dark:text-slate-700">|</span>
                            <button
                                type="button"
                                @click="deselectAll()"
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-rose-600 px-2 py-1">
                                {{ __('Clear All') }}
                            </button>
                        </div>
                    </div>

                    {{-- Search Toolbar --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                        <div class="relative flex-1 max-w-sm">
                            <i class="ph ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="{{ __('Filter products by name...') }}"
                                class="w-full pl-8 pr-3 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/60 text-slate-900 dark:text-white placeholder-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                        </div>

                        <button
                            type="button"
                            @click="filterMode = filterMode === 'all' ? 'selected' : 'all'"
                            :class="filterMode === 'selected' ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-semibold transition">
                            <i class="ph ph-funnel text-xs"></i>
                            <span x-text="filterMode === 'selected' ? '{{ __('Showing Selected') }}' : '{{ __('Show Selected Only') }}'"></span>
                            (<span x-text="selectedProducts.length"></span>)
                        </button>
                    </div>

                    {{-- Products List (Constrained 48x48 Images, Borderless Cards) --}}
                    <div class="max-h-96 overflow-y-auto space-y-1.5 p-1 rounded-2xl">
                        @foreach($products as $product)
                            @php
                                $activeVariants = $product->variants->where('status', 'active');
                                $availableColors = $activeVariants->filter(fn ($v) => $v->color_id && $v->size)->groupBy('color_id')->count();
                                $maxSizesPerColor = $activeVariants->groupBy('color_id')->map(fn ($g) => $g->pluck('size')->unique()->count())->max() ?? 0;
                            @endphp
                            <div
                                x-show="isProductVisible('{{ addslashes(strtolower($product->name)) }}', {{ $product->id }})"
                                @click="toggleProduct({{ $product->id }})"
                                :class="isProductSelected({{ $product->id }}) ? 'bg-indigo-50/80 dark:bg-indigo-950/40 shadow-xs' : 'bg-slate-50/60 dark:bg-slate-800/40 hover:bg-slate-100/80 dark:hover:bg-slate-800/80'"
                                class="flex items-center justify-between gap-3.5 p-3 rounded-2xl transition cursor-pointer select-none">
                                
                                <div class="flex items-center gap-3.5 min-w-0">
                                    <input
                                        type="checkbox"
                                        name="product_ids[]"
                                        value="{{ $product->id }}"
                                        :checked="isProductSelected({{ $product->id }})"
                                        @click.stop="toggleProduct({{ $product->id }})"
                                        class="size-4.5 rounded-md border-slate-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500 shrink-0">

                                    @if($product->primaryMedia?->url)
                                        <img src="{{ $product->primaryMedia->url }}" alt="{{ $product->name }}"
                                             width="48" height="48"
                                             style="width: 48px; height: 48px; min-width: 48px; max-width: 48px; object-fit: cover;"
                                             class="rounded-xl object-cover shrink-0 bg-slate-200 dark:bg-slate-700">
                                    @else
                                        <div style="width: 48px; height: 48px; min-width: 48px; max-width: 48px;"
                                             class="rounded-xl bg-slate-200/70 dark:bg-slate-700/60 flex items-center justify-center text-slate-400 shrink-0">
                                            <i class="ph ph-t-shirt text-xl"></i>
                                        </div>
                                    @endif

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ $product->name }}</h4>
                                            @if($product->category)
                                                <span class="inline-flex items-center rounded-md bg-slate-200/60 dark:bg-slate-700 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:text-slate-300 shrink-0">{{ $product->category->name }}</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-wrap items-center gap-x-2.5 text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                            <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $currency }} {{ number_format($product->wholesale_price ?? $product->single_piece_price, 2) }}/pc</span>
                                            <span>•</span>
                                            <span>{{ $availableColors }} color(s)</span>
                                            <span>•</span>
                                            <span>up to {{ $maxSizesPerColor }} sizes/color</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0 flex items-center pr-1">
                                    <span x-show="minColors > {{ $availableColors }}" class="inline-flex items-center rounded-full bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-700 dark:text-amber-400">
                                        <i class="ph ph-warning mr-1"></i> {{ __('Needs ') }}<span class="font-bold ml-0.5" x-text="minColors - {{ $availableColors }}"></span>{{ __(' color(s)') }}
                                    </span>
                                    <span x-show="minColors <= {{ $availableColors }}" class="inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                                        <i class="ph ph-check mr-1"></i> {{ __('Meets Variety') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
                    <a href="{{ route('user.commerce.pack-templates.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-white transition">
                        {{ __('Cancel') }}
                    </a>

                    <button
                        type="submit"
                        :disabled="isMaxContradiction || isProductSelectionInsufficient"
                        :class="(isMaxContradiction || isProductSelectionInsufficient) ? 'opacity-50 cursor-not-allowed bg-slate-400' : 'bg-indigo-600 hover:bg-indigo-500 shadow-md shadow-indigo-600/20'"
                        class="inline-flex items-center gap-2 rounded-xl px-6 py-3 text-xs font-semibold text-white transition">
                        <i class="ph ph-floppy-disk text-base"></i>
                        <span>{{ $template ? __('Update Pack Template') : __('Create Pack Template') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Alpine.js Pack Template Controller --}}
    @push('scripts')
    <script>
        function packTemplateForm(initial) {
            return {
                name: initial.name || '',
                published: String(initial.published !== undefined ? initial.published : '1'),
                position: initial.position !== undefined ? initial.position : 0,
                description: initial.description || '',
                minPieces: initial.minPieces || 12,
                maxPieces: initial.maxPieces || '',
                minProducts: initial.minProducts || 1,
                minColors: initial.minColors || 1,
                minSizes: initial.minSizes || 1,
                minVariantQty: initial.minVariantQty || 1,
                selectedProducts: (initial.selectedProducts || []).map(id => parseInt(id)),
                searchQuery: '',
                filterMode: 'all',

                get calculatedVarietyMin() {
                    const p = Math.max(1, parseInt(this.minProducts) || 1);
                    const c = Math.max(1, parseInt(this.minColors) || 1);
                    const s = Math.max(1, parseInt(this.minSizes) || 1);
                    const v = Math.max(1, parseInt(this.minVariantQty) || 1);
                    return p * c * s * v;
                },

                get isMaxContradiction() {
                    if (!this.maxPieces || this.maxPieces === '') return false;
                    return parseInt(this.maxPieces) < this.calculatedVarietyMin;
                },

                get isMinPiecesLowerThanVariety() {
                    return (parseInt(this.minPieces) || 0) < this.calculatedVarietyMin;
                },

                get isProductSelectionInsufficient() {
                    return this.selectedProducts.length < (parseInt(this.minProducts) || 1);
                },

                toggleProduct(id) {
                    const num = parseInt(id);
                    const idx = this.selectedProducts.indexOf(num);
                    if (idx > -1) {
                        this.selectedProducts.splice(idx, 1);
                    } else {
                        this.selectedProducts.push(num);
                    }
                },

                isProductSelected(id) {
                    return this.selectedProducts.includes(parseInt(id));
                },

                selectAll(allIds) {
                    this.selectedProducts = allIds.map(id => parseInt(id));
                },

                deselectAll() {
                    this.selectedProducts = [];
                },

                isProductVisible(name, id) {
                    const q = (this.searchQuery || '').trim().toLowerCase();
                    const matchesSearch = !q || name.toLowerCase().includes(q);
                    if (this.filterMode === 'selected') {
                        return matchesSearch && this.isProductSelected(id);
                    }
                    return matchesSearch;
                }
            };
        }
    </script>
    @endpush
</x-layouts.user>
