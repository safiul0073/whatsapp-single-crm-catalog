<div class="flex flex-wrap items-center gap-3 px-4 py-3">
    @if($item['image'])
        <img src="{{ $item['image'] }}" alt="" class="size-12 rounded object-cover">
    @endif
    <div class="min-w-48 flex-1">
        <p class="font-medium text-title">{{ $item['name'] }}</p>
        <p class="text-xs text-body">{{ collect([$item['color_name'], $item['size'], $item['sku']])->filter()->implode(' · ') }}</p>
        <p class="text-xs text-body">{{ $money($item['price']) }} {{ __('each') }} · {{ __(':count in stock', ['count' => $item['stock']]) }}</p>
        @foreach($warningsByItem->get((string) $item['id'], []) as $warning)
            <p class="text-xs text-error">{{ $warning['message'] }}</p>
        @endforeach
    </div>
    @if($canManage)
        <form method="POST" action="{{ route('user.commerce.carts.items.update', [$cart, $item['id']]) }}" class="flex items-center gap-2">
            @csrf
            @method('PUT')
            <label class="sr-only" for="qty-{{ $item['id'] }}">{{ __('Quantity') }}</label>
            <input id="qty-{{ $item['id'] }}" type="number" name="quantity" min="1" max="10000" value="{{ $item['quantity'] }}" class="form-input w-20" required>
            <button type="submit" class="btn btn-sm btn-outline">{{ __('Update') }}</button>
        </form>
    @else
        <span class="tabular-nums">× {{ $item['quantity'] }}</span>
    @endif
    <span class="w-28 text-right font-semibold tabular-nums">{{ $money($item['line_total']) }}</span>
    @if($canManage)
        <form method="POST" action="{{ route('user.commerce.carts.items.destroy', [$cart, $item['id']]) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline text-error hover:border-error hover:text-error" title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><i class="ph ph-trash"></i></button>
        </form>
    @endif
</div>
