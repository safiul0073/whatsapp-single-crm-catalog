<x-layouts.admin :title="__('Edit Frontend Section')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Edit Frontend Section') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Update section content while keeping compatibility visible across themes.') }}</p>
            </div>
            <x-ui.button variant="outline" href="{{ route('admin.frontend-sections.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        @include('frontend::admin.frontend-sections.partials.form', [
            'action' => route('admin.frontend-sections.update', $section),
            'method' => 'PUT',
            'section' => $section,
            'definition' => $definition,
            'selectedType' => $section->type,
            'themeLabels' => $themeLabels,
        ])
    </div>
</x-layouts.admin>
