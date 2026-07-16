<div id="globalConfirmModal" class="modal modal-sm hidden" role="dialog" aria-modal="true" aria-label="{{ __('Confirm Action') }}">
    <div class="modal-backdrop" data-modal-close="globalConfirmModal"></div>
    <div class="modal-content">
        <div class="flex flex-col items-center justify-center p-6 text-center md:p-8">
            <div class="bg-error/10 text-error mb-5 flex h-14 w-14 items-center justify-center rounded-full">
                <i class="ph-bold ph-warning-circle text-2xl"></i>
            </div>
            <h3 class="heading-5 mb-2 text-neutral-950" data-confirm-title>{{ __('Confirm Action') }}</h3>
            <p class="s-body text-neutral-500" data-confirm-message>{{ __('Please confirm this action.') }}</p>

            <div class="mt-8 flex w-full gap-3">
                <button type="button" class="btn btn-outline w-full justify-center" data-modal-close="globalConfirmModal">
                    {{ __('Cancel') }}
                </button>
                <button
                    type="button"
                    class="btn btn-danger w-full justify-center"
                    data-confirm-btn
                    data-confirm-button-label
                >
                    {{ __('Confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>
