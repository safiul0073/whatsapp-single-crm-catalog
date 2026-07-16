<x-layouts.admin :title="__('Create Frontend Section')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Create Frontend Section') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Choose a section type, then fill the schema-driven fields below.') }}</p>
            </div>
            <x-ui.button variant="outline" href="{{ route('admin.frontend-sections.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <form method="GET" action="{{ route('admin.frontend-sections.create') }}" class="max-w-lg">
                <x-forms.select :label="__('Section Type')" name="type" :selected="$selectedType" :options="$sectionTypes" />
                <div class="mt-4">
                    <x-forms.submit :label="__('Load Type')" />
                </div>
            </form>
        </div>

        @include('frontend::admin.frontend-sections.partials.form', [
            'action' => route('admin.frontend-sections.store'),
            'method' => 'POST',
            'section' => null,
            'definition' => $definition,
            'selectedType' => $selectedType,
            'themeLabels' => [],
        ])
    </div>
</x-layouts.admin>
