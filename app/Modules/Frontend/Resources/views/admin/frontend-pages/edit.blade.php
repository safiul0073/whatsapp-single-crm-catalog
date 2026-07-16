<x-layouts.admin :title="__('Edit Page')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Edit Page') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Adjust page details and keep the shared section order clean and easy to manage.') }}</p>
            </div>
            <x-ui.button variant="outline" href="{{ route('admin.frontend-pages.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        @include('frontend::admin.frontend-pages.partials.form', [
            'page' => $page,
            'sections' => $sections,
            'attachedSectionIds' => $attachedSectionIds,
            'layoutOptions' => $layoutOptions,
            'activeThemeLabel' => $activeThemeLabel,
            'action' => route('admin.frontend-pages.update', $page),
            'method' => 'PUT',
        ])
    </div>
</x-layouts.admin>
