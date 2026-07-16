{{-- Media Library Modal — rendered once per page, reused by all pickers --}}
<div id="mediaLibraryModal" class="modal modal-xl" role="dialog" aria-modal="true" aria-label="{{ __('Media Library') }}"
     data-browse-url="{{ route('admin.media.browse') }}"
     data-upload-url="{{ route('admin.media.upload') }}">
    <div class="modal-backdrop" data-modal-close="mediaLibraryModal"></div>
    <div class="modal-content media-modal">

        {{-- Header --}}
        <div class="modal-header">
            <h3 class="text-xl font-bold text-neutral-900">{{ __('Media Library') }}</h3>
            <button type="button" class="btn-icon h-8 w-8" data-modal-close="mediaLibraryModal">
                <i class="ph ph-x"></i>
            </button>
        </div>

        {{-- Toolbar --}}
        <div class="media-modal-toolbar">
            {{-- Type Tabs --}}
            <div class="media-type-tabs">
                <button type="button" class="media-type-tab active" data-media-type="">{{ __('All') }}</button>
                <button type="button" class="media-type-tab" data-media-type="image">{{ __('Images') }}</button>
                <button type="button" class="media-type-tab" data-media-type="document">{{ __('Documents') }}</button>
                <button type="button" class="media-type-tab" data-media-type="video">{{ __('Videos') }}</button>
            </div>

            {{-- Search --}}
            <div class="media-modal-search">
                <i class="ph ph-magnifying-glass"></i>
                <input type="text" placeholder="{{ __('Search media...') }}" data-media-search autocomplete="off">
            </div>
        </div>

        {{-- Body --}}
        <div class="modal-body media-modal-body">

            {{-- Upload Zone --}}
            <div class="media-upload-zone" data-media-upload-zone>
                <input type="file" multiple class="hidden" data-media-upload-input>
                <div class="media-upload-zone-content">
                    <i class="ph ph-cloud-arrow-up"></i>
                    <p>{{ __('Drag & drop files here or') }} <span class="text-primary font-medium cursor-pointer">{{ __('browse') }}</span></p>
                </div>
                {{-- Upload Progress --}}
                <div class="media-upload-progress hidden" data-media-upload-progress>
                    <div class="media-upload-progress-bar" data-media-upload-bar></div>
                </div>
            </div>

            {{-- Media Grid --}}
            <div class="media-grid" data-media-grid>
                {{-- Items loaded via AJAX --}}
            </div>

            {{-- Empty State --}}
            <div class="media-empty hidden" data-media-empty>
                <i class="ph ph-image-broken"></i>
                <p>{{ __('No media files found') }}</p>
            </div>

            {{-- Loading --}}
            <div class="media-loading hidden" data-media-loading>
                <div class="media-spinner"></div>
            </div>

            {{-- Load More --}}
            <div class="media-load-more hidden" data-media-load-more>
                <button type="button" class="btn btn-sm btn-ghost">{{ __('Load more') }}</button>
            </div>
        </div>

        {{-- Footer --}}
        <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-ghost" data-modal-close="mediaLibraryModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-sm btn-primary" data-media-select-btn disabled>{{ __('Select') }}</button>
        </div>
    </div>
</div>
