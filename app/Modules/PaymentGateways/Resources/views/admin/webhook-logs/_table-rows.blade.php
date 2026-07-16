@forelse($webhookLogs as $log)
<tr>
    <td data-th="{{ __('Gateway') }}">
        <x-ui.badge variant="neutral">{{ ucfirst($log->gateway) }}</x-ui.badge>
    </td>
    <td data-th="{{ __('Event') }}" class="text-sm text-neutral-900">{{ $log->event_type }}</td>
    <td data-th="{{ __('Event ID') }}" class="text-sm text-neutral-400">
        {{ $log->gateway_event_id ? Str::limit($log->gateway_event_id, 20, '...') : __('—') }}
    </td>
    <td data-th="{{ __('Processed') }}">
        <div class="flex justify-end lg:justify-start rtl:justify-start">
            @if($log->processed_at)
                <x-ui.badge variant="success">{{ __('Yes') }}</x-ui.badge>
            @else
                <x-ui.badge variant="warning">{{ __('Pending') }}</x-ui.badge>
            @endif
        </div>
    </td>
    <td data-th="{{ __('Received') }}" class="text-sm text-neutral-400">{{ format_date($log->created_at, true) }}</td>
    <td data-th="{{ __('Actions') }}" class="text-right">
        <x-tables.actions>
            <x-tables.action icon="eye" :href="route('admin.webhook-logs.show', $log)" :label="__('View')" />
        </x-tables.actions>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="py-8 text-center text-neutral-400">{{ __('No webhook logs found.') }}</td>
</tr>
@endforelse
