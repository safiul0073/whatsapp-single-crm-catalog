@php($productImages = $item->productImages())
<div class="h-20 w-20 shrink-0 overflow-hidden rounded-sm border border-border-soft bg-section" x-data="{ imageFailed: false }">
    @if($productImages)
        <button type="button" class="relative flex h-full w-full items-center justify-center p-1 transition hover:bg-primary/5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary" @click="openProduct(@js($productImages), $event.currentTarget)" aria-label="{{ __('View images for').' '.$productImages[0]['label'] }}">
            <img src="{{ $productImages[0]['url'] }}" alt="{{ $productImages[0]['label'] }}" class="h-full w-full object-contain" loading="lazy" :class="imageFailed ? 'hidden' : ''" x-on:error="imageFailed = true">
            <span x-cloak x-show="imageFailed" class="text-center text-xs text-body">{{ __('Image unavailable') }}</span>
            <i class="ph ph-magnifying-glass-plus absolute bottom-1 right-1 rounded-sm bg-bg-elevated p-0.5 text-sm text-body" aria-hidden="true"></i>
        </button>
    @else
        <span class="flex h-full items-center justify-center p-2 text-center text-xs text-body">{{ __('No image') }}</span>
    @endif
</div>
