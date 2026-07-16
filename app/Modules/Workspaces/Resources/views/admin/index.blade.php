<x-layouts.admin :title="__('Clients')">
    <div class="space-y-6">
        <div>
            <h1 class="heading-4 text-neutral-950">{{ __('Clients') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('SaaS workspaces connected to WhatsApp Cloud API.') }}</p>
        </div>
        <div class="section-card">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-neutral-500"><th class="py-2">{{ __('Workspace') }}</th><th>{{ __('Owner') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th></tr></thead>
                    <tbody>
                        @forelse($workspaces as $workspace)
                            <tr class="border-t border-neutral-100"><td class="py-3 font-medium">{{ $workspace->name }}</td><td>{{ $workspace->owner?->email ?? __('Unassigned') }}</td><td>{{ ucfirst($workspace->status->value) }}</td><td>{{ format_date($workspace->created_at) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-neutral-500">{{ __('No clients yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $workspaces->links() }}
        </div>
    </div>
</x-layouts.admin>
