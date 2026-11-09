{{-- Product feature icons: the preset list shown in the product wizard's Step 7 dropdown. --}}
<div
    id="feature-icons"
    class="scroll-mt-24 rounded-2xl border border-neutral-200/90 bg-white p-6 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 space-y-5"
    x-data="featureIconPresets({{ Illuminate\Support\Js::from(['presets' => $featureIcons, 'defaults' => $featureIconDefaults]) }})"
>
    <input type="hidden" name="feature_icons_submitted" value="1">

    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 pb-4 dark:border-neutral-800">
        <div class="flex items-center gap-2.5">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary">
                <i class="ph ph-seal-check text-lg"></i>
            </span>
            <div>
                <h2 class="heading-4 text-title text-base font-semibold">{{ __('Product feature icons') }}</h2>
                <p class="text-xs text-body">{{ __('The icon choices offered in Step 7 (Features / Highlights) when editing a product.') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline text-xs" @click="reset()">{{ __('Reset to defaults') }}</button>
            <button type="button" class="btn btn-sm btn-primary text-xs" @click="add()">
                <i class="ph ph-plus"></i> {{ __('Add icon') }}
            </button>
        </div>
    </div>

    <div class="space-y-2">
        <template x-for="(preset, idx) in presets" :key="idx">
            <div class="flex items-center gap-3 rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800">
                <button
                    type="button"
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-neutral-100 text-neutral-800 transition hover:bg-primary/10 hover:text-primary dark:bg-neutral-800 dark:text-neutral-100"
                    @click="pick(idx)"
                    :aria-label="'{{ __('Change icon') }} ' + (preset.label || '')"
                >
                    <i class="ph text-xl" :class="preset.icon"></i>
                </button>
                <button type="button" class="hidden w-40 truncate text-left text-xs text-neutral-500 hover:text-primary sm:block" @click="pick(idx)" x-text="preset.icon"></button>
                <input
                    type="text"
                    class="form-input flex-1 py-2 text-sm"
                    x-model="preset.label"
                    maxlength="40"
                    required
                    placeholder="{{ __('Name shown in the dropdown, e.g. True Size') }}"
                    :aria-label="'{{ __('Icon name') }} ' + (idx + 1)"
                >
                <input type="hidden" :name="`config[feature_icons][${idx}][icon]`" :value="preset.icon">
                <input type="hidden" :name="`config[feature_icons][${idx}][label]`" :value="preset.label">
                <button type="button" class="p-1.5 text-neutral-400 transition hover:text-red-600" @click="remove(idx)" aria-label="{{ __('Remove icon') }}">
                    <i class="ph ph-trash text-base"></i>
                </button>
            </div>
        </template>
        <p x-show="presets.length === 0" x-cloak class="rounded-lg border border-dashed border-neutral-200 px-4 py-5 text-center text-sm text-neutral-500">
            {{ __('No preset icons. Owners can still choose any icon with “More icons…” in Step 7.') }}
        </p>
    </div>

    @include('commerce::user.partials.feature-icon-picker')
</div>
