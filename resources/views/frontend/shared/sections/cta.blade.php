<section class="section">
    <div class="shell">
        <div class="card" style="padding: 32px;">
            <p class="eyebrow">{{ __('Next Step') }}</p>
            <h2 class="title" style="font-size: clamp(1.8rem, 3vw, 3rem);">{{ $section->data['title'] ?? '' }}</h2>
            @if(!empty($section->data['body']))
                <p class="lead">{{ $section->data['body'] }}</p>
            @endif
            @if(!empty($section->data['button_text']))
                <div class="btn-row">
                    <a href="{{ $section->data['button_link'] ?: '#' }}" class="btn btn-primary">{{ $section->data['button_text'] }}</a>
                </div>
            @endif
        </div>
    </div>
</section>
