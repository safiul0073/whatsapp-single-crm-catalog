@forelse($notificationLogs as $log)
<tr>
    <td data-th="{{ __('Template') }}" class="text-sm text-neutral-900">
        {{ $log->template_slug ?? __('N/A') }}
    </td>
    <td data-th="{{ __('Channel') }}">
        @php
            $channelIcons = [
                'email' => 'ph-envelope',
                'sms' => 'ph-chat-text',
                'in_app' => 'ph-bell',
                'web_push' => 'ph-broadcast',
                'mobile_push' => 'ph-device-mobile',
            ];
        @endphp
        <span class="inline-flex items-center gap-1 text-sm text-neutral-600">
            <i class="ph {{ $channelIcons[$log->channel] ?? 'ph-paper-plane-tilt' }}"></i>
            {{ str_replace('_', ' ', ucfirst($log->channel)) }}
        </span>
    </td>
    <td data-th="{{ __('Recipient') }}" class="text-sm text-neutral-400">
        {{ class_basename($log->notifiable_type) }} #{{ $log->notifiable_id }}
    </td>
    <td data-th="{{ __('Status') }}">
        <div class="flex justify-end lg:justify-start rtl:justify-start">
            @switch($log->status)
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
                    <x-ui.badge variant="neutral">{{ ucfirst($log->status) }}</x-ui.badge>
            @endswitch
        </div>
    </td>
    <td data-th="{{ __('Sent At') }}" class="text-sm text-neutral-400">
        {{ $log->sent_at ? format_date($log->sent_at, true) : format_date($log->created_at, true) }}
    </td>
    <td data-th="{{ __('Actions') }}" class="text-right">
        <x-tables.actions>
            <x-tables.action icon="eye" :href="route('admin.notification-logs.show', $log)" :label="__('View')" />
        </x-tables.actions>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="py-8 text-center text-neutral-400">{{ __('No notification logs found.') }}</td>
</tr>
@endforelse
