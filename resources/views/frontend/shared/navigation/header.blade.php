@php
    $productsUrl = route('commerce.products.shortcut');
    $appendProductsLink = function (array $items) use ($productsUrl): array {
        $hasProductsLink = collect($items)->contains(fn (array $item): bool => ($item['url'] ?? null) === $productsUrl);

        if (! $hasProductsLink) {
            $items[] = [
                'label' => __('Products'),
                'url' => $productsUrl,
                'target' => '_self',
                'is_visible' => true,
                'children' => [],
            ];
        }

        return $items;
    };

    $headerItems = $appendProductsLink($resolvedMenus['header']['items'] ?? []);
    $mobileItems = $appendProductsLink($resolvedMenus['mobile']['items'] ?? []);
@endphp

<header class="site-header">
    <div class="shell site-header-shell">
        <a href="{{ route('home') }}" class="site-brand">
            {{ $themeVars['logo_text'] ?? ($theme['label'] ?? config('app.name')) }}
        </a>

        @if($headerItems !== [])
            <nav class="site-nav" aria-label="{{ __('Primary navigation') }}">
                <ul class="site-nav-list">
                    @include('frontend.shared.navigation.items', ['items' => $headerItems, 'level' => 0, 'footer' => false])
                </ul>
            </nav>
        @endif

        @if($mobileItems !== [])
            <details class="site-mobile-nav">
                <summary>{{ __('Menu') }}</summary>
                <ul class="site-mobile-nav-list">
                    @include('frontend.shared.navigation.items', ['items' => $mobileItems, 'level' => 0, 'footer' => false])
                </ul>
            </details>
        @endif
    </div>
</header>
