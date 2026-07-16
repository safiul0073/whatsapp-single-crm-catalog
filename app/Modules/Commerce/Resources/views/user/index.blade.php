<x-layouts.user :title="__('Commerce products')">
    <div class="space-y-6" x-data="{ view: localStorage.getItem('commerce-product-view') || 'table', setView(mode) { this.view = mode; localStorage.setItem('commerce-product-view', mode) } }">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-primary">{{ __('Apparel commerce') }}</p>
                <h1 class="heading-3 text-title">{{ __('Products and inventory') }}</h1>
                <p class="mt-1 text-sm text-body">{{ __('Manage any garment category, option, and sellable variant.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.button variant="outline" href="{{ route('user.commerce.categories.index') }}"><i class="ph ph-tree-structure"></i> {{ __('Categories') }}</x-ui.button>
                <x-ui.button variant="outline" href="{{ route('user.commerce.catalog') }}">{{ __('Meta catalog') }}</x-ui.button>
                <x-ui.button variant="outline" href="{{ route('user.commerce.orders.index') }}">{{ __('Orders') }}</x-ui.button>
                <x-ui.button variant="primary" href="{{ route('user.commerce.products.create') }}"><i class="ph ph-plus"></i> {{ __('Add product') }}</x-ui.button>
            </div>
        </header>

        @include('commerce::user.partials.help', ['helpKey' => 'products'])

        <section class="app-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-border-soft px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 class="font-semibold text-title">{{ __('All products') }}</h2>
                    <p class="text-xs text-body">{{ trans_choice(':count product|:count products', $products->total(), ['count' => $products->total()]) }}</p>
                </div>
                <div class="flex items-center gap-1 rounded-xl bg-section p-1" role="group" aria-label="{{ __('Product view') }}">
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-lg transition" :class="view === 'table' ? 'bg-neutral-0 text-primary shadow-sm' : 'text-body hover:text-primary'" :aria-pressed="view === 'table'" @click="setView('table')" title="{{ __('Table view') }}">
                        <i class="ph ph-list-bullets text-lg"></i>
                        <span class="sr-only">{{ __('Table view') }}</span>
                    </button>
                    <button type="button" class="grid h-9 w-9 place-items-center rounded-lg transition" :class="view === 'grid' ? 'bg-neutral-0 text-primary shadow-sm' : 'text-body hover:text-primary'" :aria-pressed="view === 'grid'" @click="setView('grid')" title="{{ __('Grid view') }}">
                        <i class="ph ph-squares-four text-lg"></i>
                        <span class="sr-only">{{ __('Grid view') }}</span>
                    </button>
                </div>
            </div>

            @if ($products->isNotEmpty())
                <div class="overflow-x-auto" x-show="view === 'table'" x-cloak data-product-table>
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-border-soft bg-section">
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Product') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Category') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Brand / Audience') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Variants') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('From') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Stock') }}</th>
                                <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-body">{{ __('Status') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-body">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-soft">
                            @foreach ($products as $product)
                                <tr class="transition hover:bg-section/70">
                                    <td class="px-5 py-3.5">
                                        <div class="flex min-w-0 items-center gap-3">
                                            @if ($product->primaryMedia)
                                                <img src="{{ $product->primaryMedia->url }}" alt="{{ $product->name }}" class="h-12 w-12 shrink-0 rounded-xl object-cover" loading="lazy">
                                            @else
                                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-section text-xl text-neutral-300"><i class="ph ph-t-shirt"></i></span>
                                            @endif
                                            <div class="min-w-0">
                                                <a href="{{ route('user.commerce.products.edit', $product) }}" class="block max-w-64 truncate font-semibold text-title hover:text-primary">{{ $product->name }}</a>
                                                <span class="block text-xs text-body">{{ __('Updated :time', ['time' => $product->updated_at->diffForHumans()]) }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-title">{{ $product->category?->name ?? __('Uncategorized') }}</td>
                                    <td class="px-4 py-3.5">
                                        <span class="block text-title">{{ $product->brand ?: '—' }}</span>
                                        <span class="block text-xs text-body">{{ $product->audience ?: __('All audiences') }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 font-semibold text-title">{{ $product->variants_count }}</td>
                                    <td class="px-4 py-3.5 font-semibold text-title">{{ $product->starting_price !== null ? '$'.number_format((float) $product->starting_price, 2) : '—' }}</td>
                                    <td class="px-4 py-3.5">
                                        <span class="font-semibold {{ (int) $product->stock_total > 0 ? 'text-title' : 'text-error' }}">{{ number_format((int) $product->stock_total) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5"><span class="badge badge-soft">{{ str($product->status)->replace('_', ' ')->title() }}</span></td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex justify-end gap-2">
                                            @if ($product->status === 'active')
                                                <a href="{{ route('commerce.products.public', $product->slug) }}" class="row-action" target="_blank" rel="noopener" aria-label="{{ __('Preview :product', ['product' => $product->name]) }}" title="{{ __('Preview') }}"><i class="ph ph-arrow-square-out"></i></a>
                                            @endif
                                            <a href="{{ route('user.commerce.products.edit', $product) }}" class="row-action" aria-label="{{ __('Manage :product', ['product' => $product->name]) }}" title="{{ __('Manage product') }}"><i class="ph ph-pencil-simple"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-3" x-show="view === 'grid'" x-cloak data-product-grid>
                    @foreach ($products as $product)
                        <article class="overflow-hidden rounded-2xl border border-border bg-neutral-0">
                            <div class="aspect-[4/3] bg-section">
                                @if ($product->primaryMedia)
                                    <img src="{{ $product->primaryMedia->url }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy">
                                @else
                                    <div class="grid h-full place-items-center text-4xl text-neutral-300"><i class="ph ph-t-shirt"></i></div>
                                @endif
                            </div>
                            <div class="space-y-3 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h2 class="truncate font-semibold text-title">{{ $product->name }}</h2>
                                        <p class="text-xs text-body">{{ $product->category?->name ?? __('Uncategorized') }} · {{ trans_choice(':count variant|:count variants', $product->variants_count, ['count' => $product->variants_count]) }}</p>
                                    </div>
                                    <span class="badge badge-soft">{{ str($product->status)->title() }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="font-semibold text-title">{{ $product->starting_price !== null ? __('From $:price', ['price' => number_format((float) $product->starting_price, 2)]) : __('No price') }}</span>
                                    <span class="text-body">{{ __(':count in stock', ['count' => number_format((int) $product->stock_total)]) }}</span>
                                </div>
                                <x-ui.button variant="outline" href="{{ route('user.commerce.products.edit', $product) }}" class="w-full">{{ __('Manage product') }}</x-ui.button>
                            </div>
                        </article>
                    @endforeach
                </div>

                <x-tables.pagination :paginator="$products" />
            @else
                <div class="px-6 py-16 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-primary"><i class="ph ph-shopping-bag text-2xl"></i></span>
                    <h2 class="mt-4 font-semibold text-title">{{ __('No products yet') }}</h2>
                    <p class="mt-1 text-sm text-body">{{ __('Create your first garment and its size, color, or material variants.') }}</p>
                    <x-ui.button variant="primary" href="{{ route('user.commerce.products.create') }}" class="mt-5"><i class="ph ph-plus"></i> {{ __('Add product') }}</x-ui.button>
                </div>
            @endif
        </section>
    </div>
</x-layouts.user>
