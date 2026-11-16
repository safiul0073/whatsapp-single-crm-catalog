<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        @isset($back)
            <a href="{{ $back['href'] }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-body hover:text-title"><i class="ph ph-arrow-left"></i>{{ $back['label'] }}</a>
        @endisset
        <h1 class="heading-4 text-title mt-1">{{ $heading }}</h1>
        @isset($intro)<p class="mt-1 text-sm text-body">{{ $intro }}</p>@endisset
    </div>
    @isset($actions)<div class="flex flex-wrap gap-2">{{ $actions }}</div>@endisset
</div>
