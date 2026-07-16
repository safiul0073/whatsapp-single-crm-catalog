@php $d = $section->data ?? []; @endphp
<section class="spy-section bg-section">
    <div class="container">
        <div class="mx-auto max-w-3xl text-center">
            @if (!empty($d['eyebrow']))
                <span class="eyebrow">{{ $d['eyebrow'] }}</span>
            @endif
            @if (!empty($d['heading']))
                <h1 class="heading-1 mt-4">{{ $d['heading'] }}</h1>
            @endif
            @if (!empty($d['subheading']))
                <p class="lead-text mx-auto mt-5 max-w-2xl">
                    {{ $d['subheading'] }}
                </p>
            @endif
            @if (!empty($d['cta_primary_text']) || !empty($d['cta_secondary_text']))
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    @if (!empty($d['cta_primary_text']))
                        <a href="{{ $d['cta_primary_url'] ?? route('login') }}" class="btn btn-primary">{{ $d['cta_primary_text'] }}</a>
                    @endif
                    @if (!empty($d['cta_secondary_text']))
                        <a href="{{ $d['cta_secondary_url'] ?? route('features') }}" class="btn btn-outline">{{ $d['cta_secondary_text'] }}</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
