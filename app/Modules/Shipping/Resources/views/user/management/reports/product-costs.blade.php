<x-layouts.user :title="__('Product Cost Prices')">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6">
        @include('shipping::user.management.partials.page-header', ['heading' => __('Product Cost Prices'), 'intro' => __('What each variant costs you, in :currency. Used only for the internal profit report and never shown to customers.', ['currency' => $currency]), 'back' => ['href' => route('user.shipping.reports.index'), 'label' => __('Back to Profit/Loss')]])

        <form method="GET" action="{{ route('user.shipping.product-costs.index') }}" class="section-card flex flex-wrap items-end gap-3">
            <div class="grow"><label class="form-label" for="search">{{ __('Search product or SKU') }}</label><input id="search" class="form-input" type="search" name="search" value="{{ $search }}"></div>
            <label class="inline-label flex items-center gap-2 pb-2 text-sm text-body"><input type="checkbox" name="missing" value="1" @checked($missing)> {{ __('Only variants without a cost') }}</label>
            <x-forms.submit :label="__('Filter')" />
        </form>

        <form method="POST" action="{{ route('user.shipping.product-costs.update') }}" class="section-card">
            @csrf @method('PUT')
            @if($variants->isEmpty())
                <p class="py-8 text-center text-body">{{ __('No variants found.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table w-full text-sm whitespace-nowrap">
                        <thead><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Variant') }}</th><th class="text-right">{{ __('Selling price') }}</th><th class="text-right">{{ __('Cost price') }}</th></tr></thead>
                        <tbody>
                            @foreach($variants as $variant)
                                <tr>
                                    <td class="font-semibold text-title">{{ $variant->product?->name ?? '—' }}</td>
                                    <td>{{ $variant->sku ?: '—' }}</td>
                                    <td>{{ $variant->size ?: '—' }}</td>
                                    <td class="text-right">{{ number_format((float) $variant->price, 2) }}</td>
                                    <td class="text-right"><input class="form-input form-input-sm w-28 text-right" type="number" step="0.01" min="0" name="costs[{{ $variant->id }}]" value="{{ old('costs.'.$variant->id, $variant->cost_price !== null ? number_format((float) $variant->cost_price, 2, '.', '') : '') }}" aria-label="{{ __('Cost price for :sku', ['sku' => $variant->sku ?: $variant->id]) }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('costs.*')<p class="form-hint text-error">{{ $message }}</p>@enderror
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><x-forms.submit :label="__('Save cost prices on this page')" /><div>{{ $variants->links() }}</div></div>
            @endif
        </form>
    </div>
</x-layouts.user>
