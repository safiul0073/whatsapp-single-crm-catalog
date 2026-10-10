<dialog x-ref="productViewer" aria-labelledby="order-product-gallery-title" class="m-auto max-h-[90dvh] w-[calc(100%_-_2rem)] max-w-4xl overflow-y-auto rounded-md border border-border bg-bg-elevated p-4 text-title shadow-xl backdrop:bg-black/70 sm:p-6" @close="restoreFocus()" @keydown.left.prevent="move(-1)" @keydown.right.prevent="move(1)">
    <div class="flex items-center justify-between gap-3">
        <h3 id="order-product-gallery-title" class="font-semibold" x-text="images[activeIndex]?.label"></h3>
        <button type="button" class="btn btn-outline" autofocus @click="$refs.productViewer.close()">{{ __('Close') }}</button>
    </div>
    <template x-for="(image, index) in images" :key="image.url">
        <div :class="activeIndex === index ? '' : 'hidden'" class="py-4">
            <img :src="image.url" :alt="image.label" class="mx-auto max-h-[65dvh] max-w-full object-contain" :class="failedImages[image.url] ? 'hidden' : ''" x-on:error="failedImages[image.url] = true">
            <p class="hidden py-16 text-center text-body" :class="failedImages[image.url] ? '!block' : ''" role="status">{{ __('Image unavailable') }}</p>
        </div>
    </template>
    <div class="flex items-center justify-between gap-3">
        <button type="button" class="btn btn-outline" :class="images.length > 1 ? '' : 'invisible'" :disabled="images.length < 2" @click="move(-1)">{{ __('Previous') }}</button>
        <p aria-live="polite" class="text-sm text-body"><span x-text="activeIndex + 1"></span> / <span x-text="images.length"></span></p>
        <button type="button" class="btn btn-outline" :class="images.length > 1 ? '' : 'invisible'" :disabled="images.length < 2" @click="move(1)">{{ __('Next') }}</button>
    </div>
</dialog>
