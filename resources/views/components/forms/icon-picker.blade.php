@props([
    'label' => '',
    'name',
    'value' => '',
    'required' => false,
    'hint' => '',
    'id' => null,
    'iconsUrl' => null,
])

@php
    $fieldId = $id ?: $name;
    $selectedIcon = old($name, $value);
    $resolvedIconsUrl = $iconsUrl ?? (Route::has('admin.icon-picker.icons') ? route('admin.icon-picker.icons') : null);
@endphp

<div x-data="{
    open: false,
    query: '',
    value: @js($selectedIcon),
    icons: [],
    loaded: false,
    loading: false,
    iconsUrl: @js($resolvedIconsUrl),
    get filteredIcons() {
        if (!this.query) {
            return this.icons;
        }

        return this.icons.filter((icon) => icon.toLowerCase().includes(this.query.toLowerCase()));
    },
    async load() {
        if (this.loaded || this.loading || !this.iconsUrl) {
            return;
        }

        this.loading = true;

        try {
            const response = await fetch(this.iconsUrl);
            this.icons = await response.json();
            this.loaded = true;
        } finally {
            this.loading = false;
        }
    },
    toggle() {
        this.open = !this.open;
        if (this.open) {
            this.load();
        }
    },
    select(icon) {
        this.value = icon;
        this.open = false;
    },
    clear() {
        this.value = '';
        this.query = '';
    },
}" class="space-y-3" data-icon-picker>
    @if ($label)
        <label for="{{ $fieldId }}" class="form-label">
            {{ $label }} @if ($required)
                <span class="required">*</span>
            @endif
        </label>
    @endif

    <input type="hidden" name="{{ $name }}" id="{{ $fieldId }}" x-model="value" />

    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_200px]">
        <button type="button" @click="toggle()"
            class="flex w-full items-center justify-between rounded-xl border border-neutral-100 bg-neutral-0 px-4 py-3 text-left transition hover:border-primary dark:border-neutral-100 dark:bg-neutral-10">
            <span class="flex items-center gap-3">
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700 dark:bg-neutral-100/40 dark:text-neutral-900">
                    <i class="ph text-xl" :class="value || 'ph-shapes'"></i>
                </span>
                <span class="space-y-1">
                    <span class="block text-sm font-semibold text-neutral-900 dark:text-neutral-950">
                        <span x-text="value ? '{{ __('Selected icon') }}' : '{{ __('Choose an icon') }}'"></span>
                    </span>
                    <span class="block text-xs text-neutral-500"
                        x-text="value || '{{ __('No icon selected yet') }}'"></span>
                </span>
            </span>
            <i class="ph ph-caret-down text-sm text-neutral-400 transition" :class="{ 'rotate-180': open }"></i>
        </button>

        <div class="flex items-center gap-2">
            <div class="input-group flex-1">
                <i class="ph ph-magnifying-glass input-icon-left"></i>
                <input type="text" x-model="query" class="input-field has-icon-left"
                    placeholder="{{ __('Search icons') }}" />
            </div>
            <button type="button" @click="clear()"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-neutral-100 text-neutral-500 transition hover:border-error hover:text-error dark:border-neutral-100"
                aria-label="{{ __('Clear icon') }}">
                <i class="ph ph-x"></i>
            </button>
        </div>
    </div>

    <div x-show="open" x-cloak
        class="rounded-2xl border border-neutral-100 bg-neutral-0 p-4 shadow-sm dark:border-neutral-100 dark:bg-neutral-10">
        <div x-show="loading" class="flex justify-center py-8">
            <i class="ph ph-spinner animate-spin text-2xl text-neutral-400"></i>
        </div>

        <div x-show="!loading" class="max-h-72 overflow-y-auto pe-1 sm:max-h-80 lg:max-h-96">
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                <template x-for="icon in filteredIcons" :key="icon">
                    <button type="button" @click="select(icon)"
                        class="flex flex-col items-center gap-2 rounded-xl border px-3 py-3 text-center transition"
                        :class="value === icon ?
                            'border-primary bg-primary/10 text-primary' :
                            'border-neutral-100 text-neutral-600 hover:border-primary hover:text-primary dark:border-neutral-100 dark:text-neutral-700 dark:hover:text-primary'">
                        <i class="ph text-2xl" :class="icon"></i>
                        <span class="text-xs font-medium leading-tight" x-text="icon"></span>
                    </button>
                </template>
            </div>

            <p x-show="filteredIcons.length === 0 && loaded" x-cloak
                class="rounded-xl border border-dashed border-neutral-100 px-4 py-5 text-center text-sm text-neutral-500 dark:border-neutral-100">
                {{ __('No icons matched your search.') }}
            </p>
        </div>
    </div>

    @if ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
