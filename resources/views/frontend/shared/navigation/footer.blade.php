@php
    $footerItems = $resolvedMenus['footer']['items'] ?? [];
@endphp

@if($footerItems !== [])
    <footer class="site-menu-footer">
        <div class="shell">
            <div class="site-menu-footer-inner">
                <p class="site-menu-footer-title">{{ $themeVars['logo_text'] ?? ($theme['label'] ?? config('app.name')) }}</p>
                <ul class="site-footer-nav">
                    @include('frontend.shared.navigation.items', ['items' => $footerItems, 'level' => 0, 'footer' => true])
                </ul>
            </div>
        </div>
    </footer>
@endif
