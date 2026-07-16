@php $d = $section->data ?? []; @endphp
<section class="spy-section">
    <div class="container">
        <div class="mx-auto max-w-2xl text-center">
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h2 class="heading-1 mt-4">{{ $d['heading'] }}</h2>
            @endif
            @if (!empty($d['subheading']))
                <p class="lead-text mt-4">{{ $d['subheading'] }}</p>
            @endif
        </div>

        <div class="mx-auto mt-10 max-w-3xl space-y-12">
            @php $categories = $d['categories'] ?? []; @endphp
            @foreach ($categories as $category)
                <div>
                    @if (!empty($category['heading']) || !empty($category['name']))
                        <h2 class="heading-3">{{ $category['heading'] ?? $category['name'] ?? '' }}</h2>
                    @endif
                    @php $items = $category['items'] ?? $category['questions'] ?? []; @endphp
                    @if (!empty($items))
                        <div class="mt-6 space-y-3" data-faq-group>
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
            @endforeach
        </div>
    </div>
</section>

@if (!empty($d['cta_title']) || !empty($d['cta_subtitle']))
    <section class="spb-section">
        <div class="container">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-4 rounded-3xl border border-primary/20 bg-primary/5 px-6 py-10 text-center sm:px-10">
                @if (!empty($d['icon_class']))
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary text-neutral-0"><i class="{{ $d['icon_class'] }} text-2xl"></i></span>
                @endif
                @if (!empty($d['cta_title']))
                    <h2 class="heading-2">{{ $d['cta_title'] }}</h2>
                @endif
                @if (!empty($d['cta_subtitle']))
                    <p class="m-text max-w-xl">{{ $d['cta_subtitle'] }}</p>
                @endif
                <div class="mt-2 flex flex-wrap justify-center gap-3">
                    @if (!empty($d['cta_primary_text']))
                        <a href="{{ $d['cta_primary_url'] ?? route('contact') }}" class="btn btn-primary">{{ $d['cta_primary_text'] }}</a>
                    @endif
                    @if (!empty($d['cta_secondary_text']))
                        <a href="{{ $d['cta_secondary_url'] ?? route('login') }}" class="btn btn-outline">{{ $d['cta_secondary_text'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
