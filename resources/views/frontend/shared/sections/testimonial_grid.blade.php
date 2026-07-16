<section class="section">
    <div class="shell">
        <p class="eyebrow">{{ __('Social Proof') }}</p>
        <h2 class="title">{{ $section->data['title'] ?? '' }}</h2>
        <div class="grid grid-3" style="margin-top: 28px;">
            @foreach(($section->data['items'] ?? []) as $item)
                <article class="card">
                    <p style="font-size: 1.02rem; line-height: 1.8; margin: 0 0 20px;">“{{ $item['quote'] ?? '' }}”</p>
                    <div style="font-size: .92rem; color: #6b7280;">
                        <strong style="display:block; color:#111827;">{{ $item['name'] ?? '' }}</strong>
                        <span>{{ $item['role'] ?? '' }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
