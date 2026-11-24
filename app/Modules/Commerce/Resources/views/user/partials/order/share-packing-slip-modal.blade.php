{{-- Share packing slip PDFs on WhatsApp. Open with $dispatch('open-packing-slip-share', { action, orderIds, label }). --}}
<div
    x-data="{
        isOpen: false,
        action: '',
        orderIds: [],
        label: '',
        open(detail) {
            this.action = detail.action;
            this.orderIds = detail.orderIds || [];
            this.label = detail.label || '';
            this.isOpen = true;
        },
    }"
    @open-packing-slip-share.window="open($event.detail)"
    @keydown.escape.window="isOpen && (isOpen = false)"
    x-show="isOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-labelledby="sharePackingSlipTitle"
>
    <div class="fixed inset-0 bg-neutral-900/45 backdrop-blur-xs" @click="isOpen = false"></div>

    <form
        method="POST"
        :action="action"
        x-show="isOpen"
        x-transition
        class="relative z-10 w-full max-w-md rounded-xl border border-border-soft bg-neutral-0 shadow-2xl overflow-hidden"
        data-packing-slip-share-form
    >
        @csrf
        <template x-for="id in orderIds" :key="id">
            <input type="hidden" name="order_ids[]" :value="id">
        </template>

        <div class="flex items-start justify-between gap-3 border-b border-border-soft px-5 py-4 bg-neutral-50/60">
            <div class="min-w-0">
                <h3 id="sharePackingSlipTitle" class="text-base font-semibold text-title flex items-center gap-2">
                    <i class="ph ph-whatsapp-logo text-primary text-lg"></i>
                    <span>{{ __('Share packing slip on WhatsApp') }}</span>
                </h3>
                <p class="mt-0.5 text-xs text-body truncate" x-text="label"></p>
            </div>
            <button type="button" @click="isOpen = false" class="rounded-md p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 transition" aria-label="{{ __('Close') }}">
                <i class="ph ph-x text-lg"></i>
            </button>
        </div>

        <div class="space-y-4 px-5 py-4">
            <fieldset class="space-y-2">
                <legend class="form-label">{{ __('Send to') }}</legend>
                @forelse($packingSlipRecipients ?? [] as $recipient)
                    <label class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-md border border-border-soft px-3 py-2">
                        <input type="checkbox" name="recipients[]" value="{{ $recipient['phone'] }}" class="app-checkbox">
                        <span class="text-sm font-medium text-title">{{ $recipient['name'] }}</span>
                        <span class="ml-auto font-mono text-xs text-body">{{ $recipient['phone'] }}</span>
                    </label>
                @empty
                    <p class="text-xs text-body">{{ __('No saved recipients yet.') }}</p>
                @endforelse
                @can('commerce.orders.settings')
                    <a href="{{ route('user.commerce.orders.settings') }}#packing-slip-recipients" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                        <i class="ph ph-gear"></i> {{ __('Manage saved recipients') }}
                    </a>
                @endcan
            </fieldset>

            <div>
                <label class="form-label" for="packing_slip_extra_phone">{{ __('Other WhatsApp number (optional)') }}</label>
                <input id="packing_slip_extra_phone" type="text" name="extra_phone" maxlength="32" inputmode="tel" autocomplete="off" placeholder="+8801711223344" class="form-input">
            </div>

            <p class="rounded-md bg-section px-3 py-2 text-xs text-body">
                <i class="ph ph-info"></i>
                {{ __('The packing slip PDF is sent as a free message when possible. If the person has not messaged you in the last 24 hours, it is sent with your approved order update template instead.') }}
            </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-border-soft px-5 py-3">
            <button type="button" class="btn btn-outline btn-sm" @click="isOpen = false">{{ __('Cancel') }}</button>
            <button type="submit" class="btn btn-primary btn-sm"><i class="ph ph-paper-plane-tilt"></i> {{ __('Send') }}</button>
        </div>
    </form>
</div>
