@if ($product->status === 'active')
    @if ($metaCatalogs->isNotEmpty())
        <form method="POST" action="{{ route('user.commerce.products.catalog.sync', $product) }}" class="flex flex-nowrap items-center gap-2">
            @csrf
            <select name="catalog_id" class="form-input w-auto min-w-0 max-w-[15rem] truncate text-sm" aria-label="{{ __('Meta catalog for :product', ['product' => $product->name]) }}" required>
                @foreach ($metaCatalogs as $metaCatalog)
                    <option value="{{ $metaCatalog->id }}">{{ $metaCatalog->channelAccount?->name }} — {{ $metaCatalog->meta_catalog_id }}</option>
                @endforeach
            </select>
            <x-forms.submit variant="outline" class="btn-sm whitespace-nowrap" :label="__('Send to Meta catalog')" />
        </form>
    @else
        <x-ui.button variant="outline" size="sm" class="whitespace-nowrap" href="{{ route('user.commerce.catalog') }}">{{ __('Connect Meta catalog') }}</x-ui.button>
    @endif
@endif
