@props([
    'items' => [],
])

@if(count($items) > 0)
<nav class="flex items-center gap-2 text-sm text-neutral-400" aria-label="Breadcrumb">
    @foreach($items as $index => $item)
        @if($index > 0)
            <i class="ph ph-caret-right text-xs text-neutral-300"></i>
        @endif

        @if($loop->last)
            {{-- Active (last) item --}}
            <span class="font-medium text-neutral-950" aria-current="page">
                {{ $item['label'] }}
            </span>
        @else
            {{-- Linked item --}}
            <a href="{{ $item['url'] ?? '#' }}"
               class="transition-colors hover:text-neutral-700">
                {{ $item['label'] }}
            </a>
        @endif
    @endforeach
</nav>
@endif
