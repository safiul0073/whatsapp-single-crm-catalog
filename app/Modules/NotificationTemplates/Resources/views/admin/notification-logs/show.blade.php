<x-layouts.admin :title="__('Notification Log Detail')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Notification Log') }}</h1>
            <x-ui.button variant="outline" href="{{ route('admin.notification-logs.index') }}">
                <i class="ph ph-arrow-left"></i> {{ __('Back') }}
            </x-ui.button>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="section-card space-y-4">
                <h3 class="text-base font-semibold text-neutral-900">{{ __('Details') }}</h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Template') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">
                            @if($notificationLog->template)
                                <a href="{{ route('admin.notification-templates.edit', $notificationLog->template) }}" class="text-primary hover:underline">
                                    {{ $notificationLog->template->name }}
                                </a>
                            @else
                                {{ $notificationLog->template_slug ?? __('N/A') }}
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Channel') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">
                            {{ str_replace('_', ' ', ucfirst($notificationLog->channel)) }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Recipient') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">
                            {{ class_basename($notificationLog->notifiable_type) }} #{{ $notificationLog->notifiable_id }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Status') }}</p>
                        <div class="mt-1">
                            @switch($notificationLog->status)
                                @case('sent')
                                    <x-ui.badge variant="success">{{ __('Sent') }}</x-ui.badge>
                                    @break
                                @case('failed')
                                    <x-ui.badge variant="danger">{{ __('Failed') }}</x-ui.badge>
                                    @break
                                @case('queued')
                                    <x-ui.badge variant="warning">{{ __('Queued') }}</x-ui.badge>
                                    @break
                                @default
                                    <x-ui.badge variant="neutral">{{ ucfirst($notificationLog->status) }}</x-ui.badge>
                            @endswitch
                        </div>
                    </div>

                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Created') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ format_date($notificationLog->created_at, true) }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-neutral-400 uppercase tracking-wider">{{ __('Sent At') }}</p>
                        <p class="text-sm text-neutral-900 mt-1">{{ $notificationLog->sent_at ? format_date($notificationLog->sent_at, true) : __('N/A') }}</p>
                    </div>
                </div>
            </div>

            <div class="section-card space-y-4">
                <h3 class="text-base font-semibold text-neutral-900">{{ __('Metadata') }}</h3>

                @if(!empty($notificationLog->metadata))
                    <pre class="rounded-lg bg-neutral-50 dark:bg-neutral-900 p-4 text-xs text-neutral-700 dark:text-neutral-300 overflow-x-auto">{{ json_encode($notificationLog->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <p class="text-sm text-neutral-400 italic">{{ __('No metadata available.') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
