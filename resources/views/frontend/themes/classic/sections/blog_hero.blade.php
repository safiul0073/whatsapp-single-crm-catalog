@php
    $d = $section->data ?? [];
@endphp

<section class="blog-hero" aria-labelledby="blog-hero-heading">
    <div class="section-container">
        <div class="blog-hero__content">
            <span>{{ $d['eyebrow_text'] ?? __('WaPro Blog') }}</span>
            <h1 id="blog-hero-heading">{{ $d['heading'] ?? __('WhatsApp marketing insights for growing teams') }}</h1>
            <p>{{ $d['subheading'] ?? __('Guides on automation, broadcasts, chatbots, CRM workflows, and customer messaging that feels personal at scale.') }}</p>
        </div>
    </div>
</section>
