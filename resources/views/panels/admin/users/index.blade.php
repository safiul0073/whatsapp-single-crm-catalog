<x-layouts.admin :title="__('Users')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Users') }}</h1>
            <x-ui.button variant="primary" href="{{ route('admin.users.create') }}">
                <i class="ph ph-plus-circle"></i> {{ __('Add User') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <x-tables.resource :definition="$table" :items="$users" />
        </div>
    </div>
</x-layouts.admin>
