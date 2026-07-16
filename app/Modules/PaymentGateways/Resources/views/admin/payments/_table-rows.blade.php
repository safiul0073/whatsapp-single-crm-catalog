@forelse($payments as $payment)
<tr>
    <td data-th="{{ __('UUID') }}" class="text-sm text-neutral-400">
        <code class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs dark:bg-neutral-800">{{ Str::limit($payment->uuid, 8, '...') }}</code>
    </td>
    <td data-th="{{ __('Amount') }}" class="text-sm font-medium text-neutral-900">
        {{ $payment->formatted_amount }}
    </td>
    <td data-th="{{ __('Gateway') }}">
        <x-ui.badge variant="neutral">{{ ucfirst($payment->gateway) }}</x-ui.badge>
    </td>
    <td data-th="{{ __('Status') }}">
        <div class="flex justify-end lg:justify-start rtl:justify-start">
            @switch($payment->status)
                @case('completed') <x-ui.badge variant="success">{{ __('Completed') }}</x-ui.badge> @break
                @case('pending')
                    @if($payment->gateway === 'manual')
                        <x-ui.badge variant="warning">{{ __('Awaiting Review') }}</x-ui.badge>
                    @else
                        <x-ui.badge variant="warning">{{ __('Pending') }}</x-ui.badge>
                    @endif
                    @break
                @case('processing') <x-ui.badge variant="neutral">{{ __('Processing') }}</x-ui.badge> @break
                @case('failed') <x-ui.badge variant="danger">{{ __('Failed') }}</x-ui.badge> @break
                @default <x-ui.badge variant="neutral">{{ ucfirst($payment->status) }}</x-ui.badge>
            @endswitch
        </div>
    </td>
    <td data-th="{{ __('Customer') }}" class="text-sm text-neutral-400">
        @if($payment->user)
            {{ $payment->user->name ?? $payment->user->email ?? __('—') }}
        @else
            {{ __('Guest') }}
        @endif
    </td>
    <td data-th="{{ __('Date') }}" class="text-sm text-neutral-400">{{ format_date($payment->created_at, true) }}</td>
    <td data-th="{{ __('Actions') }}" class="text-right">
        <x-tables.actions>
            <x-tables.action icon="eye" :href="route('admin.payments.show', $payment)" :label="__('View')" />
        </x-tables.actions>
    </td>
</tr>
@empty
<tr>
    <td colspan="7" class="py-8 text-center text-neutral-400">{{ __('No payments found.') }}</td>
</tr>
@endforelse
