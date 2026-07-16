<x-layouts.admin :title="__('Currencies')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="heading-4 text-neutral-950">{{ __('Currencies') }}</h1>
            <div class="flex items-center gap-3">
                <form method="POST" action="{{ route('admin.currencies.sync-rates') }}">
                    @csrf
                    <x-ui.button variant="outline" type="submit">
                        <i class="ph ph-arrows-clockwise"></i> {{ __('Update Live Rates') }}
                    </x-ui.button>
                </form>
                <x-ui.button variant="primary" type="button" data-modal-open="addCurrencyModal">
                    <i class="ph ph-plus-circle"></i> {{ __('Add Currency') }}
                </x-ui.button>
            </div>
        </div>

        <div class="section-card">
            <x-tables.resource :definition="$table" :items="$currencies" />
        </div>
    </div>

    @push('modals')
        {{-- Create Currency Modal --}}
        <x-ui.modal id="addCurrencyModal" :title="__('Add Currency')">
            <form method="POST" action="{{ route('admin.currencies.store') }}" id="createCurrencyForm" class="space-y-4">
                @csrf
                <x-forms.input
                    :label="__('Code')"
                    name="code"
                    :value="session('open_modal') === 'addCurrencyModal' ? old('code') : ''"
                    required
                    maxlength="10"
                    :placeholder="__('e.g. USD')"
                    style="text-transform: uppercase"
                />
                <x-forms.input
                    :label="__('Name')"
                    name="name"
                    :value="session('open_modal') === 'addCurrencyModal' ? old('name') : ''"
                    required
                    :placeholder="__('e.g. US Dollar')"
                />
                <x-forms.input
                    :label="__('Symbol')"
                    name="symbol"
                    :value="session('open_modal') === 'addCurrencyModal' ? old('symbol') : ''"
                    required
                    maxlength="32"
                    :placeholder="__('e.g. $')"
                    :hint="__('HTML entities are accepted and will be saved as the real symbol.')"
                />
                <x-forms.input
                    :label="__('Exchange Rate')"
                    name="exchange_rate"
                    type="number"
                    step="0.00000001"
                    :value="session('open_modal') === 'addCurrencyModal' ? old('exchange_rate', 1.00000000) : 1.00000000"
                    required
                    :placeholder="__('e.g. 1.00000000')"
                />
                <x-forms.toggle
                    :label="__('Active')"
                    name="is_active"
                    :checked="session('open_modal') === 'addCurrencyModal' ? (bool) old('is_active', true) : true"
                />
                <x-forms.input
                    :label="__('Sort Order')"
                    name="sort_order"
                    type="number"
                    :value="session('open_modal') === 'addCurrencyModal' ? old('sort_order', 0) : 0"
                    required
                    :placeholder="__('e.g. 0')"
                />
            </form>
            <x-slot:footer>
                <div class="flex items-center justify-end gap-3 w-full">
                    <x-ui.button type="button" variant="ghost" data-modal-close="addCurrencyModal">{{ __('Cancel') }}</x-ui.button>
                    <x-forms.submit :label="__('Create Currency')" form="createCurrencyForm" />
                </div>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Edit Currency Modals --}}
        @foreach ($currencies as $currency)
            <x-ui.modal id="editCurrencyModal-{{ $currency->id }}" :title="__('Edit Currency')">
                <form method="POST" action="{{ route('admin.currencies.update', $currency) }}" id="editCurrencyForm-{{ $currency->id }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="currency_id" value="{{ $currency->id }}">
                    <x-forms.input
                        :label="__('Code')"
                        name="code"
                        :value="session('open_modal') === 'editCurrencyModal-' . $currency->id ? old('code', $currency->code) : $currency->code"
                        required
                        maxlength="10"
                        :placeholder="__('e.g. USD')"
                        style="text-transform: uppercase"
                    />
                    <x-forms.input
                        :label="__('Name')"
                        name="name"
                        :value="session('open_modal') === 'editCurrencyModal-' . $currency->id ? old('name', $currency->name) : $currency->name"
                        required
                        :placeholder="__('e.g. US Dollar')"
                    />
                    <x-forms.input
                        :label="__('Symbol')"
                        name="symbol"
                        :value="session('open_modal') === 'editCurrencyModal-' . $currency->id ? old('symbol', $currency->symbol) : $currency->symbol"
                        required
                        maxlength="32"
                        :placeholder="__('e.g. $')"
                        :hint="__('HTML entities are accepted and will be saved as the real symbol.')"
                    />
                    <x-forms.input
                        :label="__('Exchange Rate')"
                        name="exchange_rate"
                        type="number"
                        step="0.00000001"
                        :value="session('open_modal') === 'editCurrencyModal-' . $currency->id ? old('exchange_rate', $currency->exchange_rate) : $currency->exchange_rate"
                        required
                        :placeholder="__('e.g. 1.00000000')"
                    />
                    <x-forms.toggle
                        :label="__('Active')"
                        name="is_active"
                        :checked="session('open_modal') === 'editCurrencyModal-' . $currency->id ? (bool) old('is_active', $currency->is_active) : (bool) $currency->is_active"
                    />
                    <x-forms.input
                        :label="__('Sort Order')"
                        name="sort_order"
                        type="number"
                        :value="session('open_modal') === 'editCurrencyModal-' . $currency->id ? old('sort_order', $currency->sort_order) : $currency->sort_order"
                        required
                        :placeholder="__('e.g. 0')"
                    />
                </form>
                <x-slot:footer>
                    <div class="flex items-center justify-end gap-3 w-full">
                        <x-ui.button type="button" variant="ghost" data-modal-close="editCurrencyModal-{{ $currency->id }}">{{ __('Cancel') }}</x-ui.button>
                        <x-forms.submit :label="__('Update Currency')" form="editCurrencyForm-{{ $currency->id }}" />
                    </div>
                </x-slot:footer>
            </x-ui.modal>
        @endforeach
    @endpush

    @if (session('open_modal'))
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalId = "{{ session('open_modal') }}";
                const trigger = document.querySelector(`[data-modal-open="${modalId}"]`)
                             || document.querySelector(`[data-modal-trigger="${modalId}"]`);
                if (trigger) {
                    trigger.click();
                } else {
                    const modal = document.getElementById(modalId);
                    if (modal) {
                        modal.classList.remove("hidden");
                        modal.style.display = "flex";
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                modal.classList.add("active");
                                modal.classList.add("is-open");
                                document.body.classList.add("overflow-hidden");
                                document.body.classList.add("is-locked");
                                modal.querySelector("input, textarea, select, button")?.focus();
                            });
                        });
                    }
                }
            });
        </script>
    @endif
</x-layouts.admin>
