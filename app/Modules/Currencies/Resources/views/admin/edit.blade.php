<x-layouts.admin :title="__('Edit Currency')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Edit Currency') }}</h1>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('admin.currencies.set-default', $currency) }}">
                    @csrf
                    <x-ui.button variant="outline" type="submit">
                        <i class="ph ph-star"></i> {{ $currency->isDefault() ? __('Default Currency') : __('Set Default') }}
                    </x-ui.button>
                </form>
                <x-ui.button variant="outline" href="{{ route('admin.currencies.index') }}">
                    <i class="ph ph-arrow-left"></i> {{ __('Back') }}
                </x-ui.button>
            </div>
        </div>

        <div class="section-card">
            <form method="POST" action="{{ route('admin.currencies.update', $currency) }}" class="space-y-4 max-w-2xl">
                @csrf
                @method('PUT')
                <x-forms.input :label="__('Code')" name="code" :value="$currency->code" required maxlength="10" :placeholder="__('e.g. USD')" style="text-transform: uppercase" />
                <x-forms.input :label="__('Name')" name="name" :value="$currency->name" required :placeholder="__('e.g. US Dollar')" />
                <x-forms.input :label="__('Symbol')" name="symbol" :value="$currency->symbol" required maxlength="32" :placeholder="__('e.g. $')" :hint="__('HTML entities are accepted and will be saved as the real symbol.')" />
                <x-forms.input :label="__('Exchange Rate')" name="exchange_rate" type="number" step="0.00000001" :value="$currency->exchange_rate" :placeholder="__('e.g. 1.00000000')" />
                <x-forms.toggle :label="__('Active')" name="is_active" :checked="$currency->is_active" />
                <x-forms.input :label="__('Sort Order')" name="sort_order" type="number" :value="$currency->sort_order" :placeholder="__('e.g. 0')" />
                <div class="flex items-center gap-3 pt-4 border-t border-neutral-100">
                    <x-forms.submit :label="__('Update Currency')" />
                    <x-ui.button variant="ghost" href="{{ route('admin.currencies.index') }}">{{ __('Cancel') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
