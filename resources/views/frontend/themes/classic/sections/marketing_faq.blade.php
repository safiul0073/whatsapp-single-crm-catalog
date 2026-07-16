@php $d = $section->data ?? []; @endphp
<section class="spy-section bg-section">
    <div class="container max-w-3xl">
        <div class="text-center">
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            @endif
        </div>
        @php $items = $d['items'] ?? []; @endphp
        @if (!empty($items))
            <div class="mt-10 space-y-3" data-faq-group>
                @foreach ($items as $item)
                    <div class="faq-item {{ $loop->first ? 'is-open' : '' }} rounded-2xl border border-neutral-200 bg-neutral-0">
                        <button data-faq-toggle aria-expanded="{{ $loop->first ? 'true' : 'false' }}" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                            <span class="font-title text-base font-bold text-title sm:text-lg">{{ $item['question'] ?? '' }}</span>
                            <svg class="faq-icon h-5 w-5 shrink-0 text-primary transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        </button>
                        <div class="faq-answer">
                            <div><p class="m-text px-5 pb-5">{{ $item['answer'] ?? '' }}</p></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
