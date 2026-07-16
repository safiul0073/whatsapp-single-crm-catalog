<x-layouts.admin :title="__('Subscribers')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Subscribers') }}</h1>
            <x-ui.button variant="primary" href="{{ route('admin.subscribers.send.create') }}">
                <i class="ph ph-paper-plane-tilt"></i> {{ __('Send Newsletter') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <x-tables.resource :definition="$table" :items="$subscribers" />
        </div>
    </div>
</x-layouts.admin>
