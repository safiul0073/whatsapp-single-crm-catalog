@php($fieldId = $fieldId ?? 'category_image')
<div
    class="md:col-span-full"
    x-data="{
        image: {{ Illuminate\Support\Js::from($image ?? '') }},
        previewUrl: {{ Illuminate\Support\Js::from($previewUrl ?? '') }},
        choose() {
            if (typeof window.openMediaLibrary !== 'function') {
                return;
            }
            window.openMediaLibrary((media) => {
                if (media && media.url) {
                    this.image = media.url;
                    this.previewUrl = media.url;
                }
            }, { type: 'image' });
        },
        clear() {
            this.image = '';
            this.previewUrl = '';
        },
    }"
>
    <span class="form-label">{{ __('Category image') }}</span>
    <input type="hidden" name="image" :value="image">
    <div class="flex flex-wrap items-center gap-3">
        <div class="grid h-20 w-16 shrink-0 place-items-center overflow-hidden rounded-md border border-dashed border-neutral-300 bg-white text-body">
            <img x-show="previewUrl" x-cloak :src="previewUrl" alt="" class="h-full w-full object-cover">
            <i x-show="! previewUrl" class="ph ph-image text-2xl"></i>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="{{ $fieldId }}" class="btn btn-outline rounded-sm!" @click="choose()">
                <i class="ph ph-upload-simple"></i>
                <span x-text="previewUrl ? '{{ __('Change image') }}' : '{{ __('Choose or upload image') }}'"></span>
            </button>
            <button type="button" class="btn btn-outline rounded-sm!" x-show="previewUrl" x-cloak @click="clear()">{{ __('Remove') }}</button>
        </div>
        <p class="text-xs text-body">{{ __('Shown on the storefront Shop by Category tile.') }}</p>
    </div>
</div>
