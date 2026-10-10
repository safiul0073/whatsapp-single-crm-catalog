@php
    $receipts = collect($order->payment_evidence['receipts'] ?? []);
    if ($receipts->isEmpty() && filled($order->payment_evidence['receipt_path'] ?? null)) {
        $receipts = collect([['path' => $order->payment_evidence['receipt_path']]]);
    }
    $receiptImages = $receipts->keys()->map(fn ($index) => [
        'url' => route('user.commerce.orders.receipt', ['order' => $order, 'index' => $index, 'preview' => 1]),
        'label' => __('Payment screenshot').' '.($index + 1),
    ])->values();
@endphp

@if($receiptImages->isNotEmpty())
    <div x-data="commercePaymentGallery(@js($receiptImages))" class="space-y-3 pt-2">
        <p class="font-semibold text-title">{{ __('Payment screenshots') }} ({{ $receiptImages->count() }})</p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-2">
            @foreach($receiptImages as $index => $image)
                <button type="button" class="overflow-hidden rounded-xl border border-border text-start hover:border-primary focus-visible:outline-2 focus-visible:outline-primary" @click="open({{ $index }}, $event.currentTarget)" aria-label="{{ __('View').' '.$image['label'] }}">
                    <div class="flex aspect-square items-center justify-center bg-bg-elevated p-2">
                        <img src="{{ $image['url'] }}" alt="{{ $image['label'] }}" class="h-full w-full object-contain" loading="lazy" :class="failedImages[{{ $index }}] ? 'hidden' : ''" x-on:error="failedImages[{{ $index }}] = true">
                        <span class="hidden p-2 text-center text-xs text-body" :class="failedImages[{{ $index }}] ? '!block' : ''">{{ __('Image unavailable') }}</span>
                    </div>
                    <span class="block p-2 text-xs font-semibold text-primary">{{ $image['label'] }}</span>
                </button>
            @endforeach
        </div>
        <dialog x-ref="viewer" aria-labelledby="payment-gallery-title" class="m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-4xl overflow-y-auto rounded-2xl border border-border bg-bg-elevated p-4 text-title shadow-xl backdrop:bg-black/70 sm:p-6" @close="restoreFocus()" @keydown.left.prevent="move(-1)" @keydown.right.prevent="move(1)">
            <div class="flex items-center justify-between gap-3">
                <h3 id="payment-gallery-title" class="font-semibold">{{ __('Payment screenshots') }}</h3>
                <button type="button" class="btn btn-outline" autofocus @click="$refs.viewer.close()">{{ __('Close') }}</button>
            </div>
            <template x-for="(image, index) in images" :key="image.url">
                <div :class="activeIndex === index ? '' : 'hidden'" class="py-4">
                    <img :src="image.url" :alt="image.label" class="mx-auto max-h-[65dvh] max-w-full object-contain" :class="failedImages[index] ? 'hidden' : ''" x-on:error="failedImages[index] = true">
                    <p class="hidden py-16 text-center text-body" :class="failedImages[index] ? '!block' : ''" role="status">{{ __('Image unavailable') }}</p>
                </div>
            </template>
            <div class="flex items-center justify-between gap-3">
                <button type="button" class="btn btn-outline" :class="images.length > 1 ? '' : 'invisible'" :disabled="images.length < 2" @click="move(-1)">{{ __('Previous') }}</button>
                <p aria-live="polite" class="text-sm text-body"><span x-text="activeIndex + 1"></span> / {{ $receiptImages->count() }}</p>
                <button type="button" class="btn btn-outline" :class="images.length > 1 ? '' : 'invisible'" :disabled="images.length < 2" @click="move(1)">{{ __('Next') }}</button>
            </div>
        </dialog>
    </div>
@endif
