{{-- Shared "More icons…" modal; open via the `feature-icon-picker:open` window event. --}}
<div
    x-data="featureIconPicker({ iconsUrl: '{{ route('user.commerce.icon-picker.icons') }}' })"
    @feature-icon-picker:open.window="show($event.detail)"
    @keydown.escape.window="open = false"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-0 sm:items-center sm:p-4" @click.self="open = false">
        <div class="flex max-h-[85vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:max-w-2xl sm:rounded-2xl dark:bg-neutral-10" role="dialog" aria-modal="true" aria-label="{{ __('Choose an icon') }}">
            <div class="flex items-center gap-3 border-b border-neutral-200 p-4">
                <div class="input-group flex-1">
                    <i class="ph ph-magnifying-glass input-icon-left"></i>
                    <input type="text" x-model="query" class="input-field has-icon-left" placeholder="{{ __('Search icons, e.g. snowflake, truck, leaf') }}" x-ref="search">
                </div>
                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-neutral-500 hover:bg-neutral-100" @click="open = false" aria-label="{{ __('Close') }}">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>
            <div class="overflow-y-auto p-4">
                <div x-show="loading" class="flex justify-center py-10">
                    <i class="ph ph-spinner animate-spin text-2xl text-neutral-400"></i>
                </div>
                <div x-show="!loading" class="grid grid-cols-3 gap-2 sm:grid-cols-5 md:grid-cols-6">
                    <template x-for="icon in filteredIcons" :key="icon">
                        <button
                            type="button"
                            @click="choose(icon)"
                            class="flex flex-col items-center gap-1.5 rounded-lg border px-2 py-3 text-center transition"
                            :class="value === icon ? 'border-primary bg-primary/10 text-primary' : 'border-neutral-100 text-neutral-600 hover:border-primary hover:text-primary'"
                        >
                            <i class="ph text-2xl" :class="icon"></i>
                            <span class="w-full truncate text-[11px] font-medium" x-text="icon.replace('ph-', '')"></span>
                        </button>
                    </template>
                </div>
                <p x-show="!loading && loaded && filteredIcons.length === 0" x-cloak class="py-8 text-center text-sm text-neutral-500">
                    {{ __('No icons matched your search.') }}
                </p>
                <p class="mt-4 text-xs text-neutral-400">{{ __('Tip: common icons (ruler, palette, t-shirt, truck, leaf, snowflake, star…) show the same picture on the storefront and app; others show a check mark there.') }}</p>
            </div>
        </div>
    </div>
</div>
