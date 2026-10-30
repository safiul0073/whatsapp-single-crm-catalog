<div id="mediaLibraryModal" class="modal modal-xl" role="dialog" aria-modal="true" aria-label="{{ __('Media Library') }}"
    data-browse-url="{{ route('user.media.browse') }}" data-upload-url="{{ route('user.media.upload') }}" data-delete-url="{{ route('user.media.bulk-destroy') }}">
    <div class="modal-backdrop" data-modal-close="mediaLibraryModal"></div>
    <div class="modal-content media-modal">
        <div class="modal-header">
            <h3 class="text-xl font-bold text-neutral-900">{{ __('Media Library') }}</h3>
            <button type="button" class="btn-icon h-8 w-8" data-modal-close="mediaLibraryModal">
                <i class="ph ph-x"></i>
            </button>
        </div>
        <div class="media-modal-toolbar">
            <div class="media-type-tabs">
                <button type="button" class="media-type-tab active" data-media-type="">{{ __('All') }}</button>
                <button type="button" class="media-type-tab" data-media-type="image">{{ __('Images') }}</button>
                <button type="button" class="media-type-tab" data-media-type="video">{{ __('Videos') }}</button>
                <button type="button" class="media-type-tab" data-media-type="document">{{ __('Documents') }}</button>
            </div>
            <div class="flex items-center gap-2.5">
                {{-- Enable or Disable Color Picker Button on Gallery Topbar --}}
                <button
                    type="button"
                    class="btn btn-xs rounded-sm px-2.5 py-1.5 flex items-center gap-1.5 font-bold transition shadow-2xs border bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50"
                    data-media-color-picker-toggle
                    title="{{ __('Toggle Color Picker: tap to enable, then hover on any image to see color code and click to copy') }}"
                >
                    <i class="ph-bold ph-eyedropper text-sm text-primary"></i>
                    <span>{{ __('Color Picker:') }}</span>
                    <span class="media-color-picker-status uppercase text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-neutral-100 text-neutral-600">{{ __('Disabled') }}</span>
                </button>

                <button type="button" class="btn btn-xs rounded-sm px-2.5 py-1.5 flex items-center gap-1.5 font-bold transition shadow-2xs border bg-white text-neutral-700 border-neutral-300 hover:bg-neutral-50" data-media-manage-toggle aria-pressed="false" title="{{ __('Select files to delete') }}">
                    <i class="ph ph-trash text-sm"></i>
                    <span>{{ __('Manage') }}</span>
                </button>

                <div class="media-modal-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" placeholder="{{ __('Search media...') }}" data-media-search autocomplete="off">
                </div>
            </div>
        </div>
        <div class="modal-body media-modal-body">
            <div class="media-upload-zone" data-media-upload-zone>
                <input type="file" multiple class="hidden" data-media-upload-input>
                <div class="media-upload-zone-content">
                    <i class="ph ph-cloud-arrow-up"></i>
                    <p>{{ __('Drag & drop files here or') }} <span
                            class="text-primary font-medium cursor-pointer">{{ __('browse') }}</span></p>
                    <p class="text-xs text-neutral-400">{{ __('Images (JPG, PNG, GIF, WEBP, ICO) up to 10 MB · Videos (MP4, MOV, WEBM) up to 200 MB · Documents (PDF, Office, CSV, ZIP) up to 25 MB') }}</p>
                </div>
                <div class="media-upload-progress hidden" data-media-upload-progress>
                    <div class="media-upload-progress-bar" data-media-upload-bar></div>
                </div>
            </div>
            <div class="media-manage-bar hidden" data-media-manage-bar>
                <div class="media-manage-select" data-media-manage-select>
                    <span><strong data-media-manage-count>0</strong> {{ __('selected') }}</span>
                    <button type="button" class="btn btn-xs btn-ghost" data-media-manage-all>{{ __('Select all') }}</button>
                    <button type="button" class="btn btn-xs btn-error" data-media-manage-delete disabled>
                        <i class="ph ph-trash"></i> {{ __('Delete selected') }}
                    </button>
                </div>
                <div class="media-manage-confirm hidden" data-media-manage-confirm>
                    <span data-media-confirm-text data-template="{{ __('Delete :count file(s)? Pages using them will lose the image. This cannot be undone.') }}"></span>
                    <button type="button" class="btn btn-xs btn-error" data-media-confirm-yes>{{ __('Delete') }}</button>
                    <button type="button" class="btn btn-xs btn-ghost" data-media-confirm-no>{{ __('Cancel') }}</button>
                </div>
            </div>
            <p class="media-upload-error hidden" data-media-upload-error role="alert"></p>
            <div class="media-grid" data-media-grid></div>
            <div class="media-empty hidden" data-media-empty>
                <i class="ph ph-image-broken"></i>
                <p>{{ __('No media files found') }}</p>
            </div>
            <div class="media-loading hidden" data-media-loading>
                <div class="media-spinner"></div>
            </div>
            <div class="media-load-more hidden" data-media-load-more>
                <button type="button" class="btn btn-sm btn-ghost">{{ __('Load more') }}</button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-ghost"
                data-modal-close="mediaLibraryModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-sm btn-primary" data-media-select-btn
                disabled>{{ __('Select') }}</button>
        </div>
    </div>
</div>
