@forelse($currencies as $currency)
<tr>
    <td data-th="{{ __('Code') }}" class="text-sm font-semibold text-neutral-900">{{ $currency->code }}</td>
    <td data-th="{{ __('Name') }}" class="text-sm text-neutral-900">{{ $currency->name }}</td>
    <td data-th="{{ __('Symbol') }}" class="text-sm text-neutral-900">{{ $currency->symbol }}</td>
    <td data-th="{{ __('Exchange Rate') }}" class="text-sm text-neutral-600">{{ number_format($currency->exchange_rate, 8) }}</td>
    <td data-th="{{ __('Status') }}">
        <div class="flex justify-end lg:justify-start rtl:justify-start">
            <x-ui.badge :variant="$currency->is_active ? 'success' : 'danger'">
                {{ $currency->is_active ? __('Active') : __('Inactive') }}
            </x-ui.badge>
        </div>
    </td>
    <td data-th="{{ __('Actions') }}" class="text-right">
        <x-tables.actions>
            <x-tables.action icon="pencil-simple" :href="route('admin.currencies.edit', $currency)" :label="__('Edit')" />
            <x-tables.action icon="trash" :label="__('Delete')" variant="danger" data-modal-trigger="confirmDeleteCurrency-{{ $currency->id }}" />
        </x-tables.actions>
    </td>
</tr>
@empty
<tr>
    <td colspan="6" class="py-8 text-center text-neutral-400">{{ __('No currencies found.') }}</td>
</tr>
@endforelse
