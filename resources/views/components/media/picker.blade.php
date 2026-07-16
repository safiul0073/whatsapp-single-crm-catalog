@props(['name', 'value' => null, 'label' => '', 'accept' => 'image', 'hint' => '', 'multiple' => false])

@php
    $media = $value ? \App\Modules\Media\Models\Media::find($value) : null;
@endphp

<div class="media-picker" data-media-picker data-media-accept="{{ $accept }}" @if($multiple) data-media-multiple="true" @endif>
    @if($label)
        <div class="media-picker-header">
            <p class="form-label">{{ $label }}</p>
        </div>
    @endif

    @if($hint)
        <p class="media-picker-hint">{{ $hint }}</p>
    @endif

    {{-- Hidden Input --}}
    @unless($multiple)<input type="hidden" name="{{ $name }}" value="{{ $value }}" data-media-picker-input>@endunless

    {{-- Preview --}}
    <div class="media-picker-preview" data-media-picker-preview>
        @if($media)
            @if($media->isImage())
                <img src="{{ $media->url }}" alt="{{ $media->alt ?? $media->name }}">
            @else
                <div class="media-picker-file-icon">
                    <i class="ph ph-file-text"></i>
                    <span>{{ $media->original_name }}</span>
                </div>
            @endif
        @endif
    </div>

    {{-- Actions --}}
    <div class="media-picker-actions">
        <button type="button" class="media-picker-browse" data-media-picker-trigger>
            <i class="ph ph-folder-open"></i>
            {{ $media ? __('Change') : __('Browse Media') }}
        </button>
        @if($media)
            <button type="button" class="media-picker-remove" data-media-picker-remove>
                <i class="ph ph-x"></i>
                {{ __('Remove') }}
            </button>
        @endif
    </div>

    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>
