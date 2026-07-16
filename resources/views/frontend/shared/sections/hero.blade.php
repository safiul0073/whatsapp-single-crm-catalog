<section class="section">
    <div class="shell">
        @if(($section->data['eyebrow'] ?? null) && !empty($themeVars['show_hero_kicker']))
            <p class="eyebrow">{{ $section->data['eyebrow'] }}</p>
        @endif
        <h1 class="title">{{ $section->data['title'] ?? '' }}</h1>
        @if(!empty($section->data['subtitle']))
            <p class="lead">{{ $section->data['subtitle'] }}</p>
        @endif
        <div class="btn-row">
            @if(!empty($section->data['primary_button_text']))
                <a href="{{ $section->data['primary_button_link'] ?: '#' }}" class="btn btn-primary">{{ $section->data['primary_button_text'] }}</a>
            @endif
            @if(!empty($section->data['secondary_button_text']))
                <a href="{{ $section->data['secondary_button_link'] ?: '#' }}" class="btn btn-secondary">{{ $section->data['secondary_button_text'] }}</a>
            @endif
        </div>
    </div>
</section>
