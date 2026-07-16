@php
    $d = $section->data ?? [];
    $title = $d['heading'] ?? $d['title'] ?? '';
    $subtitle = $d['subheading'] ?? $d['subtitle'] ?? '';
@endphp
<section class="spy-section">
    <div class="container">
        <div
            class="mx-auto flex max-w-3xl flex-col items-center gap-4 rounded-3xl border border-primary/20 bg-primary/5 px-6 py-10 text-center sm:px-10"
        >
            @if (!empty($d['icon_class']))
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary text-neutral-0">
                    <i class="{{ $d['icon_class'] }} text-2xl"></i>
                </span>
            @endif
            @if (!empty($title))
                <h2 class="heading-2">{{ $title }}</h2>
            @endif
            @if (!empty($subtitle))
                <p class="m-text max-w-xl">{{ $subtitle }}</p>
            @endif
            <div class="mt-2 flex flex-wrap justify-center gap-3">
                @if (!empty($d['cta_primary_text']))
                    <a href="{{ $d['cta_primary_url'] ?? route('login') }}" class="btn btn-primary">{{ $d['cta_primary_text'] }}</a>
                @endif
                @if (!empty($d['cta_secondary_text']))
                    <a href="{{ $d['cta_secondary_url'] ?? route('features') }}" class="btn btn-outline">{{ $d['cta_secondary_text'] }}</a>
                @endif
            </div>
        </div>
    </div>
</section>
