<section class="section">
    <div class="shell">
        <p class="eyebrow">{{ __('Need To Know') }}</p>
        <h2 class="title">{{ $section->data['title'] ?? '' }}</h2>
        <div style="margin-top: 28px;">
            @foreach(($section->data['items'] ?? []) as $item)
                <article class="card faq-item">
                    <h3 style="margin: 0 0 8px; font-size: 1.15rem;">{{ $item['question'] ?? '' }}</h3>
                    <p style="margin: 0; color: #6b7280; line-height: 1.7;">{{ $item['answer'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
