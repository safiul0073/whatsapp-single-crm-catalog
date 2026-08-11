@extends($layoutView)

@section('title', __('Shops — :name', ['name' => $themeVars['logo_text'] ?? config('app.name')]))
@section('meta_description', __('Browse merchant storefronts available for WhatsApp ordering.'))

@section('main')
    <section class="shop-hero" aria-labelledby="shops-heading">
        <div class="section-container">
            <div class="shop-hero__content">
                <span>{{ __('Shops') }}</span>
                <h1 id="shops-heading">{{ __('Merchant storefronts') }}</h1>
                <p>{{ __('Choose a merchant, browse their active catalog, and continue the purchase conversation on WhatsApp.') }}</p>
            </div>
        </div>
    </section>

    <section class="shop-section" aria-labelledby="shops-list-heading">
        <div class="section-container">
            <div class="shop-section-heading">
                <div>
                    <span>{{ __('Directory') }}</span>
                    <h2 id="shops-list-heading">{{ __('Available shops') }}</h2>
                </div>
            </div>

            @if ($workspaces->isNotEmpty())
                <div class="shop-grid">
                    @foreach ($workspaces as $workspace)
                        <article class="shop-card">
                            <a href="{{ route('commerce.products.index', $workspace->slug) }}" class="shop-card__media" aria-label="{{ __('Open :shop', ['shop' => $workspace->name]) }}">
                                <span><i class="ph ph-storefront"></i></span>
                            </a>
                            <div class="shop-card__body">
                                <div class="shop-card__meta">
                                    <span>{{ __('WhatsApp shop') }}</span>
                                </div>
                                <h3><a href="{{ route('commerce.products.index', $workspace->slug) }}">{{ $workspace->name }}</a></h3>
                                <p>{{ data_get($workspace->settings, 'commerce.storefront_description', __('Browse products and order directly on WhatsApp.')) }}</p>
                                <div class="shop-card__footer">
                                    <strong>{{ __('Open shop') }}</strong>
                                    <span class="is-available">{{ strtoupper((string) data_get($workspace->settings, 'commerce.currency', 'USD')) }}</span>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($workspaces->hasPages())
                    <div class="shop-pagination">
                        {{ $workspaces->links() }}
                    </div>
                @endif
            @else
                <div class="shop-empty">
                    <span><i class="ph ph-shopping-bag-open"></i></span>
                    <h2>{{ __('No shops are public yet') }}</h2>
                    <p>{{ __('Merchant storefronts appear here after products are published.') }}</p>
                </div>
            @endif
        </div>
    </section>
@endsection
