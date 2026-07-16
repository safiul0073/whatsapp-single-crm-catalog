<x-layouts.admin :title="__('Create Page')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Create Page') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Create the page and start composing it with shared frontend sections.') }}</p>
            </div>
            <x-ui.button variant="outline" href="{{ route('admin.frontend-pages.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        @include('frontend::admin.frontend-pages.partials.form', [
            'page' => null,
            'sections' => $sections,
            'attachedSectionIds' => $attachedSectionIds,
            'layoutOptions' => $layoutOptions,
            'activeThemeLabel' => $activeThemeLabel,
            'action' => route('admin.frontend-pages.store'),
            'method' => 'POST',
        ])
    </div>
</x-layouts.admin>
