@inject('showcaseLinks', 'App\Modules\Commerce\Services\ProductShowcaseLink')
@php($showcaseUrl = $showcaseLinks->url($product))
@if ($showcaseUrl)
    <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false, fallback: false }">
        <a href="{{ $showcaseUrl }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">{{ __('Open showcase') }}</a>
        <button type="button" class="btn btn-outline btn-sm" @click="navigator.clipboard ? navigator.clipboard.writeText(@js($showcaseUrl)).then(() => { copied = true; fallback = false; }).catch(() => { fallback = true; }) : fallback = true" x-text="copied ? @js(__('Link copied')) : @js(__('Copy showcase link'))">{{ __('Copy showcase link') }}</button>
        <input x-show="fallback" x-cloak type="text" readonly value="{{ $showcaseUrl }}" class="form-input text-xs" aria-label="{{ __('Copy this showcase link') }}" @focus="$el.select()">
    </div>
@elseif (blank(config('commerce.showcase_frontend_url')))
    <span class="text-xs text-body">{{ __('Configure the Ecommarce frontend URL to share products.') }}</span>
@else
    <span class="text-xs text-body">{{ __('Showcase unavailable: check the frontend URL, publication, and connected store.') }}</span>
@endif
