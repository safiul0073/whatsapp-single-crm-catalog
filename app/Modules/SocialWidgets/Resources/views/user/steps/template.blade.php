<div class="space-y-3">
    <x-social-widgets::accordion section="title" icon="ph-text-t" :title="__('Feed Title')">
        <div class="space-y-4">
            <label class="block">
                <span class="font-mono text-[10px] font-bold uppercase tracking-[0.15em] text-body">{{ __('Title text') }}</span>
                <input type="text" class="form-input mt-1.5 w-full text-sm" maxlength="120" x-model="settings.title.text">
            </label>
            <x-social-widgets::toggle model="settings.title.show" :label="__('Show Title')" />
        </div>
    </x-social-widgets::accordion>

    <x-social-widgets::accordion section="header" icon="ph-layout" :title="__('Header')">
        <div class="space-y-4">
            <x-social-widgets::toggle model="settings.header.show" :label="__('Show Header')" :hint="__('Display profile information at the top of the feed')" />
            <div x-show="settings.header.show">
                <p class="text-xs font-semibold text-title">{{ __('Header Elements') }}</p>
                <div class="mt-2 grid grid-cols-2 gap-2.5">
                    <x-social-widgets::checkbox model="settings.header.elements.profilePicture" :label="__('Profile Picture')" />
                    <x-social-widgets::checkbox model="settings.header.elements.fullName" :label="__('Full Name')" />
                    <x-social-widgets::checkbox model="settings.header.elements.username" :label="__('Username')" />
                    <x-social-widgets::checkbox model="settings.header.elements.verifiedBadge" :label="__('Verified Badge')" />
                    <x-social-widgets::checkbox model="settings.header.elements.stats" :label="__('Stats')" />
                    <x-social-widgets::checkbox model="settings.header.elements.followButton" :label="__('Follow Button')" />
                </div>
                <div class="mt-4">
                    <x-social-widgets::range model="settings.header.gap" :label="__('Gap Below Header')" max="64" />
                </div>
            </div>
        </div>
    </x-social-widgets::accordion>

    <x-social-widgets::accordion section="layout" icon="ph-squares-four" :title="__('Layout Type')">
        <div class="grid grid-cols-2 gap-2">
            @foreach ($layouts as $layoutOption)
                <button type="button" class="flex h-20 flex-col justify-between rounded-sm border bg-white p-3 text-left transition" x-on:click="layout = '{{ $layoutOption->value }}'" x-bind:class="layout === '{{ $layoutOption->value }}' ? 'border-title shadow-sm' : 'border-neutral-200 hover:border-neutral-400'" data-layout-option="{{ $layoutOption->value }}">
                    <i class="ph {{ $layoutOption->icon() }} text-lg text-body"></i>
                    <span class="text-sm font-semibold text-title">{{ __($layoutOption->label()) }}</span>
                </button>
            @endforeach
        </div>
    </x-social-widgets::accordion>

    <x-social-widgets::accordion section="columns" icon="ph-grid-four" :title="__('Columns & Rows')">
        <div class="space-y-4">
            <div>
                <p class="font-mono text-[10px] font-bold uppercase tracking-[0.15em] text-body">{{ __('Columns mode') }}</p>
                <div class="mt-1.5">
                    <x-social-widgets::segmented model="settings.columns.mode" :options="['auto' => __('Auto'), 'manual' => __('Manual')]" />
                </div>
            </div>
            <div x-show="settings.columns.mode === 'manual'" x-cloak>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-title">{{ __('Columns') }}</span>
                    <span class="rounded-md border border-neutral-200 bg-white px-2 py-0.5 font-mono text-[11px] text-title" x-text="settings.columns.count"></span>
                </div>
                <input type="range" min="1" max="8" class="mt-2 w-full accent-title" x-model.number="settings.columns.count">
            </div>
            <x-social-widgets::range model="settings.columns.gap" :label="__('Gap (px)')" max="48" />
            <x-social-widgets::range model="settings.columns.width" :label="__('Width (px)')" min="320" max="1200" />
        </div>
    </x-social-widgets::accordion>
</div>
