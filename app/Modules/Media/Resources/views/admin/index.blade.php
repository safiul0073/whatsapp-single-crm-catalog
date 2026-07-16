<x-layouts.admin :title="__('Media Library')">
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="heading-4 text-neutral-950">{{ __('Media Library') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Browse uploaded files and upload new media assets.') }}</p>
            </div>
            <x-ui.button variant="primary" data-modal-open="mediaLibraryModal">
                <i class="ph ph-images"></i> {{ __('Open Library') }}
            </x-ui.button>
        </div>

        <div class="section-card space-y-4">
            <p class="text-sm text-neutral-500">
                {{ __('The media picker is shared across settings, pages, and payment gateway screens. Open the library to upload or browse assets.') }}
            </p>
            <div class="flex gap-3">
                <x-ui.button variant="primary" data-modal-open="mediaLibraryModal">
                    <i class="ph ph-upload"></i> {{ __('Upload or Select Media') }}
                </x-ui.button>
            </div>
        </div>
    </div>

    <x-media.modal />
</x-layouts.admin>
