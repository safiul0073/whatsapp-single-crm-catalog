@props(['open' => true, 'title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'section-card']) }} x-data="{ open: @js((bool) $open) }" data-credentials-panel>
    <button type="button" class="flex w-full items-center justify-between gap-3 rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/20" @click="open = ! open" :aria-expanded="open.toString()">
        <span class="min-w-0">
            <span class="heading-5 block">{{ $title ?? __('Credentials') }}</span>
            @if ($description)
                <span class="mt-0.5 block text-sm text-body">{{ $description }}</span>
            @endif
        </span>
        <i class="ph ph-caret-down shrink-0 text-lg text-neutral-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''"></i>
    </button>
    <div class="mt-5" x-show="open" @if (! $open) x-cloak @endif>
        {{ $slot }}
    </div>
</section>
