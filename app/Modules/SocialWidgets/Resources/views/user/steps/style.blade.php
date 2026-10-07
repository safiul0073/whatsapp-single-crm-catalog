<div class="space-y-3">
    <x-social-widgets::accordion section="theme" icon="ph-palette" :title="__('Theme Preset')">
        <div class="grid grid-cols-2 gap-2">
            @foreach ($themes as $theme)
                @php([$swatchCanvas, $swatchAccent] = $theme->swatch())
                <button type="button" class="rounded-lg border p-2.5 text-left transition" x-on:click="applyTheme('{{ $theme->value }}')" x-bind:class="settings.style.theme === '{{ $theme->value }}' ? 'border-title shadow-sm' : 'border-neutral-200 hover:border-neutral-400'" data-theme-option="{{ $theme->value }}">
                    <span class="flex gap-1">
                        <span class="sw-swatch h-4 w-4 rounded border border-neutral-200" data-swatch="{{ $swatchCanvas }}"></span>
                        <span class="sw-swatch h-4 w-4 rounded" data-swatch="{{ $swatchAccent }}"></span>
                    </span>
                    <span class="mt-2 block text-xs font-semibold text-title">{{ __($theme->label()) }}</span>
                </button>
            @endforeach
        </div>
    </x-social-widgets::accordion>

    <x-social-widgets::accordion section="custom" icon="ph-paint-brush" :title="__('Custom Styling')">
        <div class="space-y-5">
            <div class="space-y-3">
                <p class="font-mono text-[10px] font-bold uppercase tracking-[0.15em] text-body">{{ __('Typography & Shape') }}</p>
                <x-social-widgets::range model="settings.style.radius" :label="__('Border Radius')" max="40" />
                <x-social-widgets::range model="settings.style.borderWidth" :label="__('Border Width')" max="8" />
                <div>
                    <p class="text-xs font-medium text-title">{{ __('Font family') }}</p>
                    <div class="mt-1.5">
                        <x-social-widgets::segmented model="settings.style.font" :options="['sans' => __('Sans'), 'serif' => __('Serif'), 'mono' => __('Mono')]" />
                    </div>
                </div>
            </div>

            @foreach ([
                __('Widget canvas') => ['canvasBackground' => __('Background')],
                __('Header') => ['headerBackground' => __('Background'), 'headerText' => __('Text'), 'headerButton' => __('Button')],
                __('Post') => ['postBackground' => __('Background'), 'postText' => __('Text'), 'postBorder' => __('Border Color')],
                __('Popup') => ['popupBackground' => __('Background'), 'popupText' => __('Text')],
                __('Load more button') => ['loadMoreBackground' => __('Background'), 'loadMoreText' => __('Text'), 'loadMoreBorder' => __('Border Color')],
            ] as $group => $colorFields)
                <div class="space-y-2.5 border-t border-neutral-200 pt-4">
                    <p class="font-mono text-[10px] font-bold uppercase tracking-[0.15em] text-body">{{ $group }}</p>
                    @foreach ($colorFields as $colorKey => $colorLabel)
                        <x-social-widgets::color model="settings.style.colors.{{ $colorKey }}" :label="$colorLabel" />
                    @endforeach
                </div>
            @endforeach
            <x-social-widgets::range model="settings.style.loadMoreBorderWidth" :label="__('Load more border width')" max="8" />
            <x-social-widgets::range model="settings.style.loadMoreRadius" :label="__('Load more border radius')" max="40" />
        </div>
    </x-social-widgets::accordion>
</div>
