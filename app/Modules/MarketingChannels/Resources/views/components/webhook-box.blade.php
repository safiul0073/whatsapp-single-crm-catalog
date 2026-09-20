@props(['url', 'label' => null, 'hint' => null, 'verifyToken' => null])

<div {{ $attributes->merge(['class' => 'card-soft p-5']) }} data-webhook-box>
    <p class="eyebrow">{{ $label ?? __('Webhook URL') }}</p>
    @if ($hint)
        <p class="form-hint mt-1">{{ $hint }}</p>
    @endif
    <div class="mt-3 flex flex-wrap items-center gap-2">
        <div class="code-box min-w-0 flex-1 basis-56 break-all">{{ $url }}</div>
        <button type="button" class="btn-sm btn-outline shrink-0" data-copy="{{ $url }}" aria-label="{{ __('Copy webhook URL') }}">
            <i class="ph ph-copy text-base"></i>
            <span data-copy-label>{{ __('Copy') }}</span>
        </button>
    </div>

    @if (filled($verifyToken))
        <p class="eyebrow mt-4">{{ __('Verify token') }}</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
            <div class="code-box min-w-0 flex-1 basis-56 break-all">{{ $verifyToken }}</div>
            <button type="button" class="btn-sm btn-outline shrink-0" data-copy="{{ $verifyToken }}" aria-label="{{ __('Copy verify token') }}">
                <i class="ph ph-copy text-base"></i>
                <span data-copy-label>{{ __('Copy') }}</span>
            </button>
        </div>
    @endif
</div>
