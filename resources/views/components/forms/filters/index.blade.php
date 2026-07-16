@props(['action', 'gridClass' => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'])

<form method="GET" action="{{ $action }}" class="mb-5 rounded-2xl border border-neutral-200 bg-section/70 p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)]">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
        <div class="grid flex-1 {{ $gridClass }} gap-3">
            {{ $slot }}
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <button type="submit" class="btn-sm btn-primary h-[46px] min-h-[46px] whitespace-nowrap px-4 py-0 text-sm leading-none">
                <i class="ph ph-funnel text-xs"></i> {{ __('Apply') }}
            </button>
            <a href="{{ $action }}" class="btn-sm btn-ghost h-[46px] min-h-[46px] whitespace-nowrap px-4 py-0 text-sm leading-none">
                <i class="ph ph-x text-xs"></i> {{ __('Clear') }}
            </a>
        </div>
    </div>
</form>
