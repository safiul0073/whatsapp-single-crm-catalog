@if ($product->status === 'active')
    @if ($metaCatalogs->isNotEmpty())
        <form method="POST" action="{{ route('user.commerce.products.catalog.sync', $product) }}" class="flex flex-wrap items-center gap-2">
            @csrf
            <select name="catalog_id" class="form-input w-auto max-w-full text-sm" aria-label="{{ __('Meta catalog for :product', ['product' => $product->name]) }}" required>
                @foreach ($metaCatalogs as $metaCatalog)
                    <option value="{{ $metaCatalog->id }}">{{ $metaCatalog->channelAccount?->name }} — {{ $metaCatalog->meta_catalog_id }}</option>
                @endforeach
            </select>
            <x-forms.submit variant="outline" class="btn-sm" :label="__('Send to Meta catalog')" />
        </form>
    @else
        <x-ui.button variant="outline" href="{{ route('user.commerce.catalog') }}">{{ __('Connect Meta catalog') }}</x-ui.button>
    @endif
@endif
