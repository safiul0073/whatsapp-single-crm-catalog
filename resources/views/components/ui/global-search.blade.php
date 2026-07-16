@php
    $panelKey = app('current.panel')['key'] ?? 'admin';
    $searchRouteName = $panelKey . '.global-search';
    $searchUrl = \Illuminate\Support\Facades\Route::has($searchRouteName) ? route($searchRouteName) : null;
    $placeholder = $panelKey === 'user'
        ? __('Search pages, tools, and settings...')
        : __('Search users, products...');
@endphp

@if($searchUrl)
<div id="globalSearchModal" class="modal modal-lg" role="dialog" aria-modal="true" aria-label="{{ __('Global Search') }}">
    <div class="modal-backdrop" data-modal-close="globalSearchModal"></div>
    <div class="modal-content !p-0" x-data="globalSearch({ url: '{{ $searchUrl }}' })" @keydown="onKeydown($event)">

        {{-- Search Input --}}
        <div class="flex items-center gap-3 border-b border-neutral-100 px-5 py-4">
            <i class="ph ph-magnifying-glass text-xl text-neutral-400"></i>
            <input id="globalSearchInput"
                   x-ref="searchInput"
                   type="text"
                   x-model="query"
                   placeholder="{{ $placeholder }}"
                   class="w-full bg-transparent text-base text-neutral-900 placeholder-neutral-400 outline-none"
                   autocomplete="off" />
            <button type="button"
                    x-show="query.length > 0"
                    x-cloak
                    @click="clear(); $refs.searchInput?.focus()"
                    class="shrink-0 rounded-lg p-1 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600">
                <i class="ph ph-x text-lg"></i>
            </button>
            <kbd class="hidden shrink-0 rounded-md border border-neutral-200 bg-neutral-50 px-1.5 py-0.5 text-xs font-medium text-neutral-400 sm:inline">ESC</kbd>
        </div>

        {{-- Results Area --}}
        <div class="max-h-[400px] overflow-y-auto scrollbar-hide">

            {{-- Loading --}}
            <div x-show="loading" x-cloak class="flex items-center justify-center py-12">
                <div class="datatable-spinner"></div>
            </div>

            {{-- No Results --}}
            <div x-show="!loading && query.length >= 2 && totalResults === 0" x-cloak class="py-12 text-center">
                <i class="ph ph-magnifying-glass text-4xl text-neutral-300"></i>
                <p class="mt-2 text-sm text-neutral-400">{{ __('No results found for') }} "<span x-text="query" class="font-medium text-neutral-600"></span>"</p>
            </div>

            {{-- Initial State --}}
            <div x-show="!loading && query.length < 2 && totalResults === 0" class="py-12 text-center">
                <i class="ph ph-magnifying-glass text-4xl text-neutral-300"></i>
                <p class="mt-2 text-sm text-neutral-400">{{ __('Type at least 2 characters to search') }}</p>
            </div>

            {{-- Grouped Results --}}
            <template x-for="(group, gi) in groups" :key="gi">
                <div class="border-b border-neutral-50 last:border-0">
                    {{-- Group Header --}}
                    <div class="sticky top-0 bg-neutral-50/80 px-5 py-2 backdrop-blur-sm">
                        <span class="flex items-center gap-2 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                            <i class="ph text-sm" :class="group.icon"></i>
                            <span x-text="group.module"></span>
                        </span>
                    </div>

                    {{-- Results --}}
                    <template x-for="(item, ri) in group.results" :key="item.id">
                        <a :href="item.url"
                           :data-search-index="getFlatIndex(gi, ri)"
                           class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-primary/5"
                           :class="{ 'bg-primary/5': activeIndex === getFlatIndex(gi, ri) }">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-500">
                                <i class="ph" :class="group.icon"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-neutral-900" x-text="item.title"></p>
                                <p x-show="item.subtitle" x-text="item.subtitle" class="truncate text-xs text-neutral-400"></p>
                            </div>
                            <i class="ph ph-arrow-right text-neutral-300"></i>
                        </a>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div x-show="totalResults > 0" x-cloak class="border-t border-neutral-100 px-5 py-3">
            <div class="flex items-center justify-between text-xs text-neutral-400">
                <span><span x-text="totalResults" class="font-medium text-neutral-600"></span> {{ __('results found') }}</span>
                <span class="hidden items-center gap-3 sm:flex">
                    <span><kbd class="rounded border border-neutral-200 bg-neutral-50 px-1 py-0.5 font-mono text-[10px]">&uarr;</kbd> <kbd class="rounded border border-neutral-200 bg-neutral-50 px-1 py-0.5 font-mono text-[10px]">&darr;</kbd> {{ __('navigate') }}</span>
                    <span><kbd class="rounded border border-neutral-200 bg-neutral-50 px-1.5 py-0.5 font-mono text-[10px]">&crarr;</kbd> {{ __('open') }}</span>
                </span>
            </div>
        </div>
    </div>
</div>
@endif
