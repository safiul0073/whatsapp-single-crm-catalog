@props(['id', 'title' => '', 'size' => ''])

@php
$sizeClass = $size ? ' modal-' . $size : '';
@endphp

<div id="{{ $id }}" class="modal{{ $sizeClass }}" role="dialog" aria-modal="true" @if($title) aria-label="{{ $title }}" @endif>
    <div class="modal-backdrop" data-modal-close="{{ $id }}"></div>
    <div class="modal-content">
        @if($title)
        <div class="modal-header">
            <h3 class="text-xl font-bold text-neutral-900">{{ $title }}</h3>
            <button type="button" class="btn-icon h-8 w-8" data-modal-close="{{ $id }}">
                <i class="ph ph-x"></i>
            </button>
        </div>
        @else
        <div class="flex justify-end p-5 pb-0 md:p-6 md:pb-0">
            <button type="button" class="btn-icon h-8 w-8" data-modal-close="{{ $id }}">
                <i class="ph ph-x"></i>
            </button>
        </div>
        @endif
        <div class="modal-body">
            {{ $slot }}
        </div>
        @isset($footer)
        <div class="modal-footer">
            {{ $footer }}
        </div>
        @endisset
    </div>
</div>
