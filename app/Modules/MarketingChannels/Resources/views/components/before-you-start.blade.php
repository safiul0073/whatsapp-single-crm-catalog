@props(['items' => [], 'title' => null])

<div {{ $attributes->merge(['class' => 'card-soft p-5']) }} data-before-you-start>
    <p class="eyebrow">{{ $title ?? __('Before you start') }}</p>
    <ul class="mt-3 space-y-2.5">
        @foreach ($items as $item)
            <li class="flex items-start gap-2.5 text-sm text-body">
                <i class="ph ph-check-square-offset mt-0.5 shrink-0 text-base text-primary"></i>
                <span>{{ $item }}</span>
            </li>
        @endforeach
    </ul>
    @if ($slot->isNotEmpty())
        <div class="mt-4 border-t border-neutral-200/60 pt-4 text-sm text-body">{{ $slot }}</div>
    @endif
</div>
