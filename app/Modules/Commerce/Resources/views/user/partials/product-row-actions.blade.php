@inject('showcaseLinks', 'App\Modules\Commerce\Services\ProductShowcaseLink')
@php($showcaseUrl = $showcaseLinks->url($product))
@php($menuId = 'product-actions-'.$product->id)
<div class="flex items-center justify-end gap-1">
    @if ($product->status === 'active')
        <a href="{{ route('commerce.products.direct', ['product' => $product->slug]) }}" class="grid h-8 w-8 place-items-center rounded-md text-body transition hover:bg-neutral-100 hover:text-title" target="_blank" rel="noopener" aria-label="{{ __('Preview :product', ['product' => $product->name]) }}" title="{{ __('Preview') }}">
            <i class="ph ph-arrow-square-out text-base"></i>
        </a>
    @endif
    <a href="{{ route('user.commerce.products.edit', $product) }}" class="grid h-8 w-8 place-items-center rounded-md text-body transition hover:bg-neutral-100 hover:text-title" aria-label="{{ __('Manage :product', ['product' => $product->name]) }}" title="{{ __('Edit') }}">
        <i class="ph ph-pencil-simple text-base"></i>
    </a>
    <button type="button" class="grid h-8 w-8 place-items-center rounded-md text-body transition hover:bg-neutral-100 hover:text-title" data-floating-dropdown="{{ $menuId }}" aria-label="{{ __('More actions for :product', ['product' => $product->name]) }}" title="{{ __('More') }}">
        <i class="ph-bold ph-dots-three text-base"></i>
    </button>
    <div id="{{ $menuId }}" class="floating-dropdown-panel min-w-56">
        @if ($showcaseUrl)
            <button type="button" class="floating-dropdown-item w-full text-left" x-data="{ copied: false }" @click="navigator.clipboard?.writeText(@js($showcaseUrl)).then(() => { copied = true; setTimeout(() => copied = false, 1500); })">
                <i class="ph ph-link"></i> <span x-text="copied ? @js(__('Link copied')) : @js(__('Copy showcase link'))">{{ __('Copy showcase link') }}</span>
            </button>
            <a href="{{ $showcaseUrl }}" target="_blank" rel="noopener" class="floating-dropdown-item">
                <i class="ph ph-storefront"></i> {{ __('Open showcase') }}
            </a>
        @endif

        @if ($product->status === 'active')
            @forelse ($metaCatalogs as $metaCatalog)
                <form method="POST" action="{{ route('user.commerce.products.catalog.sync', $product) }}">
                    @csrf
                    <input type="hidden" name="catalog_id" value="{{ $metaCatalog->id }}">
                    <button type="submit" class="floating-dropdown-item w-full text-left">
                        <i class="ph ph-meta-logo"></i> {{ __('Send to :catalog', ['catalog' => $metaCatalog->channelAccount?->name ?? __('Meta catalog')]) }}
                    </button>
                </form>
            @empty
                <a href="{{ route('user.commerce.catalog') }}" class="floating-dropdown-item">
                    <i class="ph ph-meta-logo"></i> {{ __('Connect Meta catalog') }}
                </a>
            @endforelse
        @endif

        <form method="POST" action="{{ route('user.commerce.products.destroy', $product) }}" class="border-t border-neutral-100">
            @csrf
            @method('DELETE')
            <button type="button" class="floating-dropdown-item w-full text-left text-error" data-confirm data-confirm-title="{{ __('Delete product?') }}" data-confirm-body="{{ __('This product, variants, and product media links will be permanently deleted. This cannot be undone.') }}" data-confirm-label="{{ __('Delete') }}" data-confirm-variant="error">
                <i class="ph ph-trash"></i> {{ __('Delete') }}
            </button>
        </form>
    </div>
</div>
