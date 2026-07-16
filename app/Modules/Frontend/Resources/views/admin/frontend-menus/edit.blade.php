<x-layouts.admin :title="__('Edit Frontend Menu')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Edit Frontend Menu') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Adjust the shared menu tree once, then reuse it across theme slots.') }}</p>
            </div>
            <x-ui.button variant="outline" href="{{ route('admin.frontend-menus.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        @include('frontend::admin.frontend-menus.partials.form-tree', [
            'menu' => $menu,
            'pageOptions' => $pageOptions,
            'editorItems' => $editorItems,
            'slotDefinitions' => $slotDefinitions,
            'usage' => $usage,
            'action' => route('admin.frontend-menus.update', $menu),
            'method' => 'PUT',
        ])
    </div>
</x-layouts.admin>
