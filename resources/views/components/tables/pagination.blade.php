@props(['paginator'])

@if($paginator->hasPages())
<div class="flex flex-col flex-wrap gap-4 border-t border-neutral-100 p-6 md:flex-row md:items-center md:justify-between">
    {{-- Info --}}
    <div class="flex items-center gap-4">
        <span class="s-body font-medium text-neutral-600">
            {{ __('Showing') }} {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} {{ __('of') }} {{ $paginator->total() }} {{ __('items') }}
        </span>
    </div>

    {{-- Page Navigation --}}
    <div class="flex items-center gap-2">
        {{-- Previous --}}
        @if($paginator->onFirstPage())
            <span class="btn-icon h-9 w-9 cursor-not-allowed opacity-50">
                <i class="ph ph-caret-left"></i>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn-icon h-9 w-9 pagination-btn">
                <i class="ph ph-caret-left"></i>
            </a>
        @endif

        {{-- Page Numbers --}}
        @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if($page == $paginator->currentPage())
                <span class="bg-primary shadow-primary/20 flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold text-white shadow-lg">
                    {{ $page }}
                </span>
            @elseif($page == 1 || $page == $paginator->lastPage() || abs($page - $paginator->currentPage()) <= 2)
                <a href="{{ $url }}" class="flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold text-neutral-600 transition-colors hover:bg-neutral-50 pagination-btn">
                    {{ $page }}
                </a>
            @elseif(abs($page - $paginator->currentPage()) == 3)
                <span class="px-1 text-neutral-500">&hellip;</span>
            @endif
        @endforeach

        {{-- Next --}}
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn-icon h-9 w-9 pagination-btn">
                <i class="ph ph-caret-right"></i>
            </a>
        @else
            <span class="btn-icon h-9 w-9 cursor-not-allowed opacity-50">
                <i class="ph ph-caret-right"></i>
            </span>
        @endif
    </div>
</div>
@endif
