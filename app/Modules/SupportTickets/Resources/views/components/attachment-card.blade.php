@props(['attachment', 'inBubble' => false])

@php
    $isImage = $attachment->isImage();
    $icon = $isImage ? 'ph-image' : 'ph-file-text';
    $sizeKb = $attachment->size > 0 ? number_format($attachment->size / 1024, 1) : '0';
@endphp

<a href="{{ $attachment->downloadUrl() }}"
   download="{{ $attachment->original_name }}"
   class="group flex items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition-colors hover:shadow-sm {{ $inBubble ? 'bg-white/90 border-white/40 text-text-strong hover:bg-white' : 'bg-bg-soft border-border-soft text-text-strong hover:bg-white hover:border-brand-blue/30' }}">
    @if ($isImage)
        <div class="w-10 h-10 rounded-lg overflow-hidden bg-neutral-100 flex-none">
            <img src="{{ $attachment->downloadUrl() }}" alt="{{ $attachment->original_name }}" class="w-full h-full object-cover">
        </div>
    @else
        <div class="w-10 h-10 rounded-lg bg-white/50 inline-flex items-center justify-center flex-none">
            <i class="ph {{ $icon }} text-xl text-text-muted"></i>
        </div>
    @endif

    <div class="flex-1 min-w-0">
        <p class="text-[12px] font-semibold truncate">{{ $attachment->original_name }}</p>
        <p class="text-[10px] text-text-muted">{{ $sizeKb }} KB · {{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }}</p>
    </div>

    <div class="text-text-muted group-hover:text-brand-blue transition-colors flex-none">
        <i class="ph ph-download-simple w-4 h-4"></i>
    </div>
</a>
