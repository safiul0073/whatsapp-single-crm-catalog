<x-layouts.user :title="__('Custom Pack Templates')">
    <div x-data="{
        deleteModalOpen: false,
        deleteActionUrl: '',
        templateNameToDelete: ''
    }" class="space-y-6">

        {{-- Page Header --}}
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20">
                    <i class="ph ph-package text-2xl"></i>
                </span>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('Custom Pack Templates') }}</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('Multi-product wholesale pack bundles with real-time variety & MOQ enforcement.') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('user.commerce.storefront.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/60 transition shadow-xs">
                    <i class="ph ph-arrow-left text-base"></i>
                    <span>{{ __('Storefront Settings') }}</span>
                </a>
                <a href="{{ route('user.commerce.pack-templates.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-600/20 hover:bg-emerald-500 transition">
                    <i class="ph ph-plus-circle text-base"></i>
                    <span>{{ __('Create Template') }}</span>
                </a>
            </div>
        </header>

        {{-- Summary Stats --}}
        @php
            $packStats = [
                [__('Total Templates'), $templates->count(), 'ph-stack', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'],
                [__('Published'), $templates->where('published', true)->count(), 'ph-check-circle', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400'],
                [__('Drafts'), $templates->where('published', false)->count(), 'ph-eye-slash', 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400'],
                [__('Wholesale Products'), $products->count(), 'ph-t-shirt', 'bg-sky-50 text-sky-600 dark:bg-sky-950/40 dark:text-sky-400'],
            ];
        @endphp
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach($packStats as [$statLabel, $statValue, $statIcon, $statTone])
                <div class="flex items-center gap-3 rounded-2xl border border-black/5 bg-white p-4 shadow-2xs dark:border-slate-800 dark:bg-slate-900">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl {{ $statTone }}"><i class="ph {{ $statIcon }} text-xl"></i></span>
                    <div class="min-w-0">
                        <span class="block truncate text-xs font-medium text-slate-500 dark:text-slate-400">{{ $statLabel }}</span>
                        <span class="block text-xl font-bold text-slate-900 dark:text-white">{{ $statValue }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Alerts --}}
        @if(session('success'))
            <div class="rounded-2xl bg-emerald-500/10 p-4 text-xs font-medium text-emerald-800 dark:text-emerald-300 flex items-center gap-2.5">
                <i class="ph ph-check-circle text-lg shrink-0 text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="rounded-2xl bg-amber-500/10 p-4 text-xs font-medium text-amber-800 dark:text-amber-300 flex items-center gap-2.5">
                <i class="ph ph-warning text-lg shrink-0 text-amber-600"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

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

        {{-- Configured Templates Section --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="ph ph-stack text-emerald-600 dark:text-emerald-400 text-lg"></i>
                    <span>{{ __('Configured Templates') }}</span>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                        {{ $templates->count() }}
                    </span>
                </h2>
            </div>

            @forelse($templates as $template)
                <article class="rounded-2xl border border-black/5 bg-white p-5 shadow-2xs space-y-5 transition hover:shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                    {{-- Template Header --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="grid size-11 place-items-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/70 dark:text-emerald-400 shrink-0 mt-0.5">
                                <i class="ph ph-package text-2xl"></i>
                            </span>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ $template->name }}</h3>
                                    @if($template->published)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                            <i class="ph ph-check-circle"></i> {{ __('Published') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                            <i class="ph ph-eye-slash"></i> {{ __('Draft') }}
                                        </span>
                                    @endif
                                    <span class="text-xs font-medium text-slate-500 bg-slate-100 dark:bg-slate-800 px-2.5 py-0.5 rounded-lg">Order #{{ $template->position }}</span>
                                    <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/60 px-2.5 py-0.5 rounded-lg">
                                        {{ $template->min_pieces }}{{ $template->max_pieces ? ' - ' . $template->max_pieces : '+' }} {{ __('Pieces') }}
                                    </span>
                                </div>
                                @if($template->description)
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-2xl">{{ $template->description }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                            <a
                                href="{{ route('user.commerce.pack-templates.edit', $template->id) }}"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                                <i class="ph ph-pencil-simple text-sm"></i>
                                <span>{{ __('Edit Template') }}</span>
                            </a>
                            <button
                                type="button"
                                @click="deleteModalOpen = true; deleteActionUrl = '{{ route('user.commerce.pack-templates.destroy', $template->id) }}'; templateNameToDelete = '{{ addslashes($template->name) }}'"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-rose-500/10 px-3.5 py-2 text-xs font-semibold text-rose-700 dark:text-rose-400 hover:bg-rose-500/20 transition">
                                <i class="ph ph-trash text-sm"></i>
                                <span>{{ __('Delete') }}</span>
                            </button>
                        </div>
                    </div>

                    {{-- Rule Metrics Tiles (Borderless, Soft Tint) --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 text-xs">
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-[11px] text-slate-400 font-medium flex items-center gap-1"><i class="ph ph-package text-emerald-600 dark:text-emerald-400"></i> {{ __('Pack MOQ') }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white mt-1 block">≥ {{ $template->min_pieces }} pcs</span>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-[11px] text-slate-400 font-medium flex items-center gap-1"><i class="ph ph-prohibit text-emerald-600 dark:text-emerald-400"></i> {{ __('Pack Cap') }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white mt-1 block">{{ $template->max_pieces ? 'Max ' . $template->max_pieces . ' pcs' : __('No limit') }}</span>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-[11px] text-slate-400 font-medium flex items-center gap-1"><i class="ph ph-t-shirt text-emerald-600 dark:text-emerald-400"></i> {{ __('Min Products') }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white mt-1 block">≥ {{ $template->min_products }} styles</span>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-[11px] text-slate-400 font-medium flex items-center gap-1"><i class="ph ph-palette text-emerald-600 dark:text-emerald-400"></i> {{ __('Colors / Prod') }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white mt-1 block">≥ {{ $template->min_colors }} colors</span>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-[11px] text-slate-400 font-medium flex items-center gap-1"><i class="ph ph-ruler text-emerald-600 dark:text-emerald-400"></i> {{ __('Sizes / Color') }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white mt-1 block">≥ {{ $template->min_sizes }} sizes</span>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                            <span class="text-[11px] text-slate-400 font-medium flex items-center gap-1"><i class="ph ph-stack text-emerald-600 dark:text-emerald-400"></i> {{ __('Per Variant') }}</span>
                            <span class="text-sm font-bold text-slate-900 dark:text-white mt-1 block">≥ {{ $template->min_variant_quantity }} pcs</span>
                        </div>
                    </div>

                    {{-- Eligible Assigned Products Preview --}}
                    @php
                        $assignedProducts = $products->whereIn('id', $template->product_ids ?? []);
                    @endphp
                    <div class="pt-2">
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-2.5">
                            <span class="font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                                <i class="ph ph-check-square-offset text-emerald-600"></i>
                                {{ __('Eligible Wholesale Products') }} ({{ $assignedProducts->count() }})
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @forelse($assignedProducts as $assigned)
                                @php
                                    $availCols = $assigned->variants->where('status', 'active')->filter(fn ($v) => $v->color_id && $v->size)->groupBy('color_id')->count();
                                    $isLow = $availCols < $template->min_colors;
                                @endphp
                                <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white py-1 pl-1 pr-3 text-xs dark:border-slate-700 dark:bg-slate-800/70">
                                    @if($assigned->primaryMedia?->url)
                                        <img src="{{ $assigned->primaryMedia->url }}" alt="{{ $assigned->name }}"
                                             width="24" height="24"
                                             class="size-6 min-w-6 rounded-md object-cover">
                                    @else
                                        <i class="ph ph-t-shirt text-slate-400"></i>
                                    @endif
                                    <span class="font-medium text-slate-800 dark:text-slate-200 max-w-[180px] truncate">{{ $assigned->name }}</span>
                                    @if($isLow)
                                        <span class="text-amber-500 text-xs inline-flex items-center" title="Has {{ $availCols }} color(s) available; requires {{ $template->min_colors }}"><i class="ph ph-warning-circle"></i></span>
                                    @endif
                                </div>
                            @empty
                                <span class="text-xs text-rose-500 font-medium">{{ __('No eligible products assigned.') }}</span>
                            @endforelse
                        </div>
                    </div>
                </article>
            @empty
                {{-- Clean Empty State --}}
                <div class="rounded-2xl bg-white dark:bg-slate-900 p-10 text-center shadow-xs space-y-4">
                    <span class="mx-auto grid size-16 place-items-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400">
                        <i class="ph ph-package text-3xl"></i>
                    </span>
                    <div class="max-w-md mx-auto space-y-1.5">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('No Custom Pack Templates Configured') }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Custom pack templates allow wholesale buyers to create their own mixed-product bundles with real-time color and size variety enforcement.') }}
                        </p>
                    </div>
                    <a href="{{ route('user.commerce.pack-templates.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-md shadow-emerald-600/20 hover:bg-emerald-500 transition">
                        <i class="ph ph-plus-circle text-base"></i>
                        <span>{{ __('Create Your First Pack Template') }}</span>
                    </a>
                </div>
            @endforelse
        </section>

        {{-- Delete Confirmation Modal --}}
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
                    class="relative transform overflow-hidden rounded-2xl bg-white dark:bg-slate-900 px-4 pb-4 pt-5 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-950 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="ph ph-warning text-xl text-rose-600 dark:text-rose-400"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-base font-semibold leading-6 text-slate-900 dark:text-white" id="modal-title">
                                {{ __('Delete Custom Pack Template') }}
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('Are you sure you want to delete template') }} <strong x-text="templateNameToDelete" class="text-slate-800 dark:text-slate-200"></strong>?
                                    {{ __('Any existing orders containing this template snapshot remain preserved, but storefront buyers will no longer be able to build packs with this template.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-2">
                        <form :action="deleteActionUrl" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex w-full justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-rose-500 sm:w-auto transition">
                                {{ __('Yes, Delete Template') }}
                            </button>
                        </form>
                        <button
                            type="button"
                            @click="deleteModalOpen = false"
                            class="mt-3 inline-flex w-full justify-center rounded-xl bg-slate-100 dark:bg-slate-800 px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 sm:mt-0 sm:w-auto transition">
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-layouts.user>
