<section class="section">
    <div class="shell">
        @if(!empty($section->data['title']))
            <h1 class="title" style="font-size: clamp(2rem, 3vw, 3.2rem);">{{ $section->data['title'] }}</h1>
        @endif
        <div class="card" style="margin-top: 22px;">
            <div style="line-height: 1.8; color: #4b5563;">{!! $section->data['content'] ?? '' !!}</div>
        </div>
    </div>
</section>
