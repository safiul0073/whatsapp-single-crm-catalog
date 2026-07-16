@props([
    'id' => 'confirmModal',
    'title' => 'Delete Account?',
    'message' => 'Are you sure you want to delete your account? This action cannot be undone.',
    'confirmText' => 'Yes, Delete',
    'cancelText' => 'Cancel',
    'formId' => '',
])

<div id="{{ $id }}" class="modal modal-sm" role="dialog" aria-modal="true" aria-label="{{ $title }}">
    <div class="modal-backdrop" data-modal-close="{{ $id }}"></div>
    <div class="modal-content">
        <div class="flex flex-col items-center justify-center p-6 text-center md:p-8">
            <div class="bg-error/10 text-error mb-5 flex h-14 w-14 items-center justify-center rounded-full">
                <i class="ph-bold ph-trash text-2xl"></i>
            </div>
            <h3 class="heading-5 mb-2 text-neutral-950">{{ $title }}</h3>
            <p class="s-body text-neutral-500">{{ $message }}</p>

            <div class="mt-8 flex w-full gap-3">
                <button type="button" class="btn btn-outline w-full justify-center" data-modal-close="{{ $id }}">
                    {{ $cancelText }}
                </button>
                <button type="button" class="btn btn-danger w-full justify-center" data-confirm-btn @if($formId) data-confirm-form="{{ $formId }}" @endif>
                    {{ $confirmText }}
                </button>
            </div>
        </div>
    </div>
</div>
