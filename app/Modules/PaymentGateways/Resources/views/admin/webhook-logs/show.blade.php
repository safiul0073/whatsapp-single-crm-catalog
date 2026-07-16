<x-layouts.admin :title="__('Webhook Log Detail')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Webhook Log') }}</h1>
            <x-ui.button variant="outline" href="{{ route('admin.webhook-logs.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="section-card space-y-4">
                <h3 class="text-base font-semibold text-neutral-900">{{ __('Details') }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Gateway') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ ucfirst($webhookLog->gateway) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Event Type') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ $webhookLog->event_type }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Event ID') }}</p>
                        <p class="text-sm text-neutral-900 mt-1 break-all">{{ $webhookLog->gateway_event_id ?? __('—') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Processed') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ $webhookLog->processed_at ? format_date($webhookLog->processed_at, true) : __('Not yet') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Received') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ format_date($webhookLog->created_at, true) }}</p>
                    </div>
                </div>
            </div>

            <div class="section-card space-y-4">
                <h3 class="text-base font-semibold text-neutral-900">{{ __('Payload') }}</h3>
                <pre class="rounded-lg bg-neutral-50 dark:bg-neutral-900 p-4 text-xs text-neutral-700 dark:text-neutral-300 overflow-x-auto max-h-96">{{ json_encode($webhookLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    </div>
</x-layouts.admin>
