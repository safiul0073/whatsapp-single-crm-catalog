<x-layouts.admin :title="__('Audit Log Details')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Audit Log Details') }}</h1>
            <x-ui.button variant="outline" href="{{ route('admin.audit-logs.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="section-card">
            <div class="space-y-4 max-w-3xl">
                <div>
                    <span class="text-sm text-neutral-400">{{ __('User') }}</span>
                    @if($auditLog->user)
                        <div class="mt-1">
                            <p class="text-neutral-950 font-medium">{{ $auditLog->user->name }}</p>
                            <p class="text-sm text-neutral-400">{{ $auditLog->user->email }}</p>
                        </div>
                    @else
                        <p class="text-neutral-400">{{ __('System') }}</p>
                    @endif
                </div>

                <div>
                    <span class="text-sm text-neutral-400">{{ __('Action') }}</span>
                    <p class="mt-1">
                        <x-ui.badge :variant="match($auditLog->action) {
                            'created' => 'success',
                            'updated' => 'info',
                            'deleted' => 'danger',
                            default => 'default'
                        }">
                            {{ ucfirst($auditLog->action) }}
                        </x-ui.badge>
                    </p>
                </div>

                <div>
                    <span class="text-sm text-neutral-400">{{ __('Type') }}</span>
                    <p class="text-neutral-950 font-medium">{{ class_basename($auditLog->auditable_type) }}</p>
                </div>

                <div>
                    <span class="text-sm text-neutral-400">{{ __('Record ID') }}</span>
                    <p class="text-neutral-900">{{ $auditLog->auditable_id }}</p>
                </div>

                @if($auditLog->old_values && count($auditLog->old_values) > 0)
                <div>
                    <span class="text-sm text-neutral-400">{{ __('Old Values') }}</span>
                    <div class="mt-2 bg-neutral-50 border border-neutral-200 rounded-lg p-4">
                        <pre class="text-sm text-neutral-900 whitespace-pre-wrap break-words">{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
                @endif

                @if($auditLog->new_values && count($auditLog->new_values) > 0)
                <div>
                    <span class="text-sm text-neutral-400">{{ __('New Values') }}</span>
                    <div class="mt-2 bg-neutral-50 border border-neutral-200 rounded-lg p-4">
                        <pre class="text-sm text-neutral-900 whitespace-pre-wrap break-words">{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                </div>
                @endif

                <div>
                    <span class="text-sm text-neutral-400">{{ __('IP Address') }}</span>
                    <p class="text-neutral-900">{{ $auditLog->ip_address ?? __('N/A') }}</p>
                </div>

                <div>
                    <span class="text-sm text-neutral-400">{{ __('User Agent') }}</span>
                    <p class="text-neutral-900 text-sm break-words">{{ $auditLog->user_agent ?? __('N/A') }}</p>
                </div>

                <div>
                    <span class="text-sm text-neutral-400">{{ __('URL') }}</span>
                    <p class="text-neutral-900 text-sm break-words">{{ $auditLog->url ?? __('N/A') }}</p>
                </div>

                <div>
                    <span class="text-sm text-neutral-400">{{ __('Date & Time') }}</span>
                    <p class="text-neutral-900">{{ format_date($auditLog->created_at, true) }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
