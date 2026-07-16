<section class="section">
    <div class="shell">
        <p class="eyebrow">{{ __('Highlights') }}</p>
        <h2 class="title">{{ $section->data['title'] ?? '' }}</h2>
        @if(!empty($section->data['subtitle']))
            <p class="lead">{{ $section->data['subtitle'] }}</p>
        @endif
        <div class="grid grid-3" style="margin-top: 28px;">
            @foreach(($section->data['items'] ?? []) as $item)
                <article class="card">
                    <h3 style="margin: 0 0 10px; font-size: 1.2rem;">{{ $item['title'] ?? '' }}</h3>
                    <p style="margin: 0; color: #6b7280; line-height: 1.7;">{{ $item['description'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
