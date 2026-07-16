<footer class="section footer">
    <div class="shell">
        <div class="grid grid-3" style="align-items: start;">
            <div>
                <p class="eyebrow" style="color: rgba(255,255,255,.7);">{{ __('Footer') }}</p>
                <h2 style="margin: 0 0 12px; font-size: 1.6rem;">{{ $section->data['title'] ?? '' }}</h2>
                <p style="margin: 0; line-height: 1.8; opacity: .8;">{{ $section->data['body'] ?? '' }}</p>
            </div>
            <div style="grid-column: span 2;">
                <div class="grid grid-3">
                    @foreach(($section->data['links'] ?? []) as $link)
                        <a href="{{ $link['url'] ?? '#' }}" style="display:inline-block; padding: 12px 0;">{{ $link['label'] ?? '' }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</footer>
