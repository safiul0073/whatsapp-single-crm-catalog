@php
    $d = $section->data ?? [];
    $eyebrow = $d['eyebrow'] ?? __('Our offices');
    $heading = $d['heading'] ?? __("We're distributed — but always nearby.");
    $offices = $d['offices'] ?? [];
@endphp
<section class="relative bg-bg-soft border-y border-border-default py-[clamp(48px,6vw,80px)]" aria-label="{{ __('Our offices') }}">
    <div class="section-container">
        <div class="flex items-center gap-2 mb-2">
            <span class="srv3-eyebrow-dot w-1.5 h-1.5 rounded-pill bg-brand-blue"></span>
            <span class="font-mono text-micro font-semibold uppercase tracking-[0.14em] text-brand-blue">{{ $eyebrow }}</span>
        </div>
        <h2 class="font-display text-[clamp(20px,2.5vw,30px)] font-extrabold leading-heading tracking-heading text-brand-navy-ink mb-8">{{ $heading }}</h2>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($offices as $office)
                @php
                    $isGreen = ($office['color'] ?? 'blue') === 'green';
                    $tileClass = $isGreen ? 'bg-tint-green text-brand-green' : 'bg-tint-blue text-brand-blue';
                    $metaClass = $isGreen ? 'text-brand-green' : 'text-brand-blue';
                @endphp
                <div class="rounded-2xl border border-border-soft bg-white p-6 about-value-card">
                    <div class="w-10 h-10 rounded-xl {{ $tileClass }} inline-grid place-items-center mb-4">
                        <i data-lucide="{{ $office['icon'] ?? 'building-2' }}" class="w-5 h-5 about-value-icon"></i>
                    </div>
                    <p class="font-display font-extrabold text-text-strong text-[15px] tracking-heading">{{ $office['name'] }}</p>
                    <p class="font-body text-body-sm text-text-muted mt-1 leading-relaxed-body">{!! nl2br(e($office['address'])) !!}</p>
                    <p class="font-mono text-micro {{ $metaClass }} mt-3">{{ $office['meta'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
